<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Log;

/** The customer paid the balance link (Stripe Checkout). Called only from a verified webhook. */
final class MarkBalancePaid extends Action
{
    public function handle(Order $order, string $checkoutSessionId, int $amountPaidCents): bool
    {
        return $this->transaction(function () use ($order, $checkoutSessionId, $amountPaidCents): bool {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::AwaitingBalance) {
                return false;
            }
            if ($amountPaidCents !== $locked->balance_due_cents) {
                Log::warning('Balance payment amount differs from balance due', ['order' => $locked->number, 'paid' => $amountPaidCents, 'due' => $locked->balance_due_cents]);

                return false;
            }

            $transaction = new PaymentTransaction;
            $transaction->fill([
                'type' => PaymentTransactionType::BalancePaid,
                'status' => PaymentTransaction::SUCCEEDED,
                'amount_cents' => $amountPaidCents,
                'stripe_object_id' => $checkoutSessionId,
                'idempotency_key' => $locked->idempotencyKey('balance-paid'),
            ]);
            $locked->transactions()->save($transaction);

            $locked->balance_paid_cents = $amountPaidCents;
            $locked->balance_due_cents = 0;
            $locked->status = OrderStatus::Completed;
            $locked->settled_at = now();
            $locked->save();

            return true;
        });
    }
}
