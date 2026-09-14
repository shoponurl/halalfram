<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Actions\Action;
use App\Actions\Orders\MarkBalancePaid;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Payments\Data\WebhookEvent;
use App\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Applies a signature-verified Stripe event exactly once. The dedupe row and the effects commit together,
 * so if handling throws, Stripe's retry gets a clean second attempt.
 */
final class HandleStripeWebhook extends Action
{
    public function __construct(
        private readonly PaymentGateway $payments,
        private readonly MarkOrderAuthorized $markAuthorized,
        private readonly MarkBalancePaid $markBalancePaid,
    ) {}

    public function handle(WebhookEvent $event): string
    {
        return $this->transaction(function () use ($event): string {
            $isNew = DB::table('stripe_webhook_events')->insertOrIgnore(['id' => $event->id, 'type' => $event->type, 'processed_at' => now()]) === 1;
            if (! $isNew) {
                return 'duplicate';
            }

            $metadata = $event->metadata();
            $order = isset($metadata['order_uuid']) ? Order::query()->where('uuid', $metadata['order_uuid'])->first() : null;
            if ($order === null) {
                return 'ignored';
            }

            return match ([$event->type, $metadata['purpose'] ?? null]) {
                ['payment_intent.amount_capturable_updated', 'hold'] => $this->markAuthorized->handle($order, $this->payments->retrieveIntent((string) $event->object['id'])) ? 'authorized' : 'noop',
                ['payment_intent.payment_failed', 'hold'] => $this->recordFailedAttempt($order, $event),
                ['payment_intent.canceled', 'hold'] => $this->holdCanceled($order),
                ['checkout.session.completed', 'balance'] => ($event->object['payment_status'] ?? null) === 'paid'
                    && $this->markBalancePaid->handle($order, (string) $event->object['id'], (int) ($event->object['amount_total'] ?? 0)) ? 'balance_paid' : 'noop',
                default => 'ignored',
            };
        });
    }

    /** The customer can retry with another card on the same PaymentIntent, so the order stays pending. */
    private function recordFailedAttempt(Order $order, WebhookEvent $event): string
    {
        $error = $event->object['last_payment_error'] ?? [];
        $transaction = new PaymentTransaction;
        $transaction->fill([
            'type' => PaymentTransactionType::Authorization,
            'status' => PaymentTransaction::FAILED,
            'amount_cents' => $order->hold_cents,
            'stripe_object_id' => (string) $event->object['id'],
            'idempotency_key' => null,
            'failure_message' => is_array($error) ? mb_substr((string) ($error['message'] ?? 'Card declined'), 0, 500) : null,
        ]);
        $order->transactions()->save($transaction);

        return 'authorization_failed';
    }

    /** Stripe cancels a hold that was never captured when it expires. */
    private function holdCanceled(Order $order): string
    {
        if (in_array($order->status, [OrderStatus::Authorized, OrderStatus::NeedsReview], true)) {
            $order->status = OrderStatus::AuthorizationExpired;
            $order->save();

            return 'authorization_expired';
        }

        return 'noop';
    }
}
