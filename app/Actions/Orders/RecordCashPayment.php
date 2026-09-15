<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Jobs\DispatchOrderNotification;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Guideline ch. 7, S06: cash on pickup — front desk collects and records the amount at handoff. */
final class RecordCashPayment extends Action
{
    public function handle(Order $order, int $amountTenderedCents, User $by): Order
    {
        $order = $this->transaction(function () use ($order, $amountTenderedCents, $by): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->payment_method !== 'cash' || $locked->status !== OrderStatus::AwaitingCashPayment) {
                throw ValidationException::withMessages(['payment' => 'This order isn\'t awaiting a cash payment.']);
            }
            if ($amountTenderedCents < (int) $locked->final_cents) {
                throw ValidationException::withMessages(['amount' => 'That\'s less than the amount due.']);
            }

            $locked->captured_cents = (int) $locked->final_cents;
            $locked->status = OrderStatus::Completed;
            $locked->settled_at = now();
            $locked->save();

            $transaction = new PaymentTransaction;
            $transaction->fill([
                'type' => PaymentTransactionType::CashReceived,
                'status' => PaymentTransaction::SUCCEEDED,
                'amount_cents' => (int) $locked->final_cents,
                'idempotency_key' => $locked->idempotencyKey('cash-received'),
            ]);
            $transaction->recorded_by = $by->id;
            $locked->transactions()->save($transaction);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });

        DispatchOrderNotification::dispatch($order->id, NotificationEvent::Completed->value);

        return $order;
    }
}
