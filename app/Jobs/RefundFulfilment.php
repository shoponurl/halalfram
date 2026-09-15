<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\PaymentTransactionType;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Payments\PaymentGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Issues the refund for a missed pickup or a returned (twice-failed) delivery (guideline ch. 7 S05).
 * Safe to retry: keyed by order + reason + amount, recorded before it can run twice.
 */
final class RefundFulfilment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $orderId,
        public readonly int $amountCents,
        public readonly string $reason,
    ) {}

    public function handle(PaymentGateway $payments): void
    {
        Cache::lock("refund-order:{$this->orderId}", 60)->block(30, function () use ($payments): void {
            $order = Order::query()->findOrFail($this->orderId);
            if ($this->amountCents <= 0 || $order->stripe_payment_intent_id === null) {
                return;
            }

            $key = $order->idempotencyKey("refund:{$this->reason}");
            if (PaymentTransaction::query()->where('order_id', $order->id)->where('idempotency_key', $key)->where('status', PaymentTransaction::SUCCEEDED)->exists()) {
                return;
            }

            $result = $payments->refund((string) $order->stripe_payment_intent_id, $this->amountCents, $key);

            $transaction = new PaymentTransaction;
            $transaction->fill([
                'type' => PaymentTransactionType::Refund,
                'status' => $result->succeeded ? PaymentTransaction::SUCCEEDED : PaymentTransaction::FAILED,
                'amount_cents' => $this->amountCents,
                'stripe_object_id' => $result->refundId,
                'idempotency_key' => $key,
                'failure_message' => $result->failureMessage,
            ]);
            $order->transactions()->save($transaction);

            if (! $result->succeeded) {
                Log::critical('Fulfilment refund failed — needs manual attention', ['order_id' => $order->id, 'reason' => $this->reason, 'error' => $result->failureMessage]);

                return;
            }

            $order->refunded_cents += $this->amountCents;
            $order->fulfilment_status = FulfilmentStatus::Refunded;
            $order->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::Refunded, 'note' => $this->reason]);
            $order->deliveryEvents()->save($event);
        });
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Fulfilment refund job failed after retries', ['order_id' => $this->orderId, 'error' => $e->getMessage()]);
    }
}
