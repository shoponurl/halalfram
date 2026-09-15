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
use App\Payments\Data\IntentState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * The card hold succeeded. Called from the Stripe webhook and from the customer's return page (or,
 * for PayPal, from the return page only — see App\Http\Controllers\Shop\PayPalReturnController).
 * Whichever caller arrives first wins; the other is a no-op.
 */
final class MarkOrderAuthorized extends Action
{
    public function handle(Order $order, IntentState $intent): bool
    {
        $authorized = $this->transaction(function () use ($order, $intent): bool {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::PendingPayment || ! $intent->isAuthorized()) {
                return false;
            }
            if ($intent->amountCapturableCents !== $locked->hold_cents) {
                Log::warning('Authorization does not match order', ['order' => $locked->number, 'intent' => $intent->id]);

                return false;
            }
            if ($locked->payment_method === 'card' && $intent->id !== $locked->stripe_payment_intent_id) {
                Log::warning('Authorization does not match order', ['order' => $locked->number, 'intent' => $intent->id]);

                return false;
            }

            $locked->status = OrderStatus::Authorized;
            if ($locked->payment_method === 'paypal') {
                // confirmAuthorization() just exchanged the order id for an authorization id — that's
                // the id capture()/cancel() need next (see Order::gatewayIntentId()).
                $locked->payment_reference = $intent->id;
            } else {
                $locked->stripe_payment_method_id = $intent->paymentMethodId;
            }
            $locked->authorized_at = now();
            $locked->authorization_expires_at = $intent->captureBefore !== null
                ? Carbon::instance($intent->captureBefore->toDateTime())
                : now()->addDays((int) config('catchweight.authorization_fallback_days'));
            $locked->save();

            $transaction = new PaymentTransaction;
            $transaction->fill([
                'type' => PaymentTransactionType::Authorization,
                'status' => PaymentTransaction::SUCCEEDED,
                'amount_cents' => $locked->hold_cents,
                'stripe_object_id' => $intent->id,
                'idempotency_key' => $locked->idempotencyKey('hold-authorized'),
            ]);
            $locked->transactions()->save($transaction);

            $order->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($authorized) {
            DispatchOrderNotification::dispatch($order->id, NotificationEvent::PaymentAuthorized->value);
        }

        return $authorized;
    }
}
