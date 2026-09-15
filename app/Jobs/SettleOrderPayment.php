<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Payments\Exceptions\PaymentMethodNotSupported;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGatewayFactory;
use App\Support\Cents;
use App\Support\SettlementPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Executes the settlement plan against Stripe. Safe to retry: every step has its own idempotency key,
 * is recorded in payment_transactions, and is skipped if it already succeeded.
 */
final class SettleOrderPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public readonly int $orderId) {}

    public function handle(PaymentGatewayFactory $gateways): void
    {
        Cache::lock("settle-order:{$this->orderId}", 120)->block(30, function () use ($gateways): void {
            $order = Order::query()->with(['items', 'transactions'])->findOrFail($this->orderId);
            if ($order->status !== OrderStatus::Settling) {
                return;   // already settled by an earlier attempt
            }

            /** @var PaymentGateway $payments */
            $payments = $gateways->for($order->payment_method);

            $plan = SettlementPlan::for(
                estimatedCents: $order->estimated_cents,
                holdCents: $order->hold_cents,
                finalCents: (int) $order->final_cents,
                overageAutochargePct: $order->overage_autocharge_pct,
                underweightReviewPct: $order->underweight_review_pct,
                underweightApproved: true,   // FinalizeOrder already enforced the review gate before locking weights
                minimumChargeCents: (int) config('catchweight.minimum_charge_cents'),
            );

            match ($plan->action) {
                SettlementPlan::CANCEL => $this->cancelHold($order, $payments, $plan),
                SettlementPlan::CAPTURE => $this->captureOnly($order, $payments, $plan),
                SettlementPlan::CAPTURE_AND_CHARGE => $this->captureAndCharge($order, $payments, $plan),
                SettlementPlan::CAPTURE_AND_LINK => $this->captureAndLink($order, $payments, $plan),
                default => throw new \LogicException("Unexpected settlement action {$plan->action}"),
            };
        });
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Order settlement failed after retries — needs manual attention', ['order_id' => $this->orderId, 'error' => $e->getMessage()]);
    }

    private function cancelHold(Order $order, PaymentGateway $payments, SettlementPlan $plan): void
    {
        $key = $order->idempotencyKey('cancel');
        if (! $this->succeeded($order, $key)) {
            $intent = $payments->cancel((string) $order->gatewayIntentId(), $key);
            $this->record($order, PaymentTransactionType::Cancel, PaymentTransaction::SUCCEEDED, 0, $intent->id, $key);
        }
        $this->writeOff($order, $plan->writeOffCents);
        $this->complete($order);
    }

    private function captureOnly(Order $order, PaymentGateway $payments, SettlementPlan $plan): void
    {
        $this->capture($order, $payments, $plan->captureCents);
        $this->writeOff($order, $plan->writeOffCents);
        $this->complete($order);
    }

    private function captureAndCharge(Order $order, PaymentGateway $payments, SettlementPlan $plan): void
    {
        $this->capture($order, $payments, $plan->captureCents);

        $key = $order->idempotencyKey("extra-charge:{$plan->balanceCents}");
        if (! $this->succeeded($order, $key)) {
            try {
                $result = $payments->chargeOffSession(
                    (string) $order->stripe_customer_id,
                    (string) $order->stripe_payment_method_id,
                    $plan->balanceCents,
                    "Halal Brothers order {$order->number} — actual weight above estimate",
                    ['order_uuid' => $order->uuid, 'order_number' => (string) $order->number, 'purpose' => 'extra_charge'],
                    $key,
                );
            } catch (PaymentMethodNotSupported) {
                // PayPal without Vault approval (guideline S06) can't recharge off-session at all —
                // same fallback as a declined card.
                $this->sendBalanceLink($order, $payments, $plan->balanceCents);

                return;
            }

            if (! $result->succeeded) {
                // Declined or needs 3-D Secure: fall back to a payment link rather than failing the order
                $this->record($order, PaymentTransactionType::ExtraCharge, PaymentTransaction::FAILED, $plan->balanceCents, $result->intentId, $key.':failed', $result->failureMessage);
                $this->sendBalanceLink($order, $payments, $plan->balanceCents);

                return;
            }

            $this->record($order, PaymentTransactionType::ExtraCharge, PaymentTransaction::SUCCEEDED, $plan->balanceCents, $result->intentId, $key);
            $order->extra_charged_cents = $plan->balanceCents;
        }

        $this->complete($order);
    }

    private function captureAndLink(Order $order, PaymentGateway $payments, SettlementPlan $plan): void
    {
        $this->capture($order, $payments, $plan->captureCents);
        $this->sendBalanceLink($order, $payments, $plan->balanceCents);
    }

    private function capture(Order $order, PaymentGateway $payments, int $amount): void
    {
        $key = $order->idempotencyKey('capture');
        if ($this->succeeded($order, $key)) {
            return;
        }
        $intent = $payments->capture((string) $order->gatewayIntentId(), $amount, $key);
        $this->record($order, PaymentTransactionType::Capture, PaymentTransaction::SUCCEEDED, $amount, $intent->id, $key);
        if ($order->payment_method === 'paypal') {
            // The capture id, not the authorization id, is what a later refund needs (Order::gatewayIntentId()).
            $order->payment_reference = $intent->id;
        }
        $order->captured_cents = $amount;
        $order->save();
    }

    private function sendBalanceLink(Order $order, PaymentGateway $payments, int $amount): void
    {
        $key = $order->idempotencyKey("balance-link:{$amount}");
        $link = $payments->createPaymentLink(
            (string) $order->stripe_customer_id,
            $amount,
            "Halal Brothers order {$order->number} — balance for actual weight (".Cents::format($amount).')',
            route('orders.balance-paid', $order),
            ['order_uuid' => $order->uuid, 'order_number' => (string) $order->number, 'purpose' => 'balance'],
            $key,
        );
        if (! $this->succeeded($order, $key)) {
            $this->record($order, PaymentTransactionType::BalanceLink, PaymentTransaction::SUCCEEDED, $amount, $link->id, $key);
        }

        $order->balance_due_cents = $amount;
        $order->balance_payment_url = $link->url;
        $order->status = OrderStatus::AwaitingBalance;
        $order->save();

        DispatchOrderNotification::dispatch($order->id, NotificationEvent::BalanceDue->value);
    }

    private function writeOff(Order $order, int $cents): void
    {
        if ($cents <= 0) {
            return;
        }
        $key = $order->idempotencyKey("write-off:{$cents}");
        if (! $this->succeeded($order, $key)) {
            $this->record($order, PaymentTransactionType::WriteOff, PaymentTransaction::SUCCEEDED, $cents, null, $key, 'Below the card minimum charge');
        }
        $order->written_off_cents = $cents;
    }

    private function complete(Order $order): void
    {
        $order->status = OrderStatus::Completed;
        $order->settled_at = now();
        $order->save();

        DispatchOrderNotification::dispatch($order->id, NotificationEvent::Completed->value);
    }

    private function succeeded(Order $order, string $key): bool
    {
        return PaymentTransaction::query()->where('order_id', $order->id)->where('idempotency_key', $key)->where('status', PaymentTransaction::SUCCEEDED)->exists();
    }

    private function record(Order $order, PaymentTransactionType $type, string $status, int $amount, ?string $stripeId, string $key, ?string $failure = null): void
    {
        $transaction = new PaymentTransaction;
        $transaction->fill([
            'type' => $type,
            'status' => $status,
            'amount_cents' => $amount,
            'stripe_object_id' => $stripeId,
            'idempotency_key' => $key,
            'failure_message' => $failure !== null ? mb_substr($failure, 0, 500) : null,
        ]);
        $order->transactions()->save($transaction);
    }
}
