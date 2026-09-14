<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\PaymentGateway;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Checkout: prices the cart on the server, saves the order, and opens the card hold.
 *
 * Creating the PaymentIntent is synchronous on purpose (the browser needs its client secret to show the card form);
 * every call after authorization runs on the queue (rule 08).
 */
final class PlaceOrder extends Action
{
    public function __construct(
        private readonly PaymentGateway $payments,
        private readonly QuoteCart $quote,
    ) {}

    /**
     * @param  array<int, int>  $lines  product id => quantity (pieces), straight from the session cart
     * @param  array{customer_name: string, customer_email: string, customer_phone: string, notes?: string|null}  $customer
     * @param  int  $expectedHoldCents  the hold the customer saw; any difference means prices changed or were tampered with
     * @return array{order: Order, token: string}
     */
    public function handle(array $lines, array $customer, int $expectedHoldCents, ?User $user = null): array
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $token = Str::random(48);

        $order = $this->transaction(function () use ($lines, $customer, $expectedHoldCents, $user, $token): Order {
            $quote = $this->quote->handle($lines, lockProducts: true);

            // Rule 02: amounts are computed here; the browser's number is only a cross-check
            if ($quote['hold_cents'] !== $expectedHoldCents) {
                throw ValidationException::withMessages([
                    'cart' => 'Prices changed since you opened checkout. Please review the new total and try again.',
                ]);
            }
            if ($quote['hold_cents'] < (int) config('catchweight.minimum_charge_cents')) {
                throw ValidationException::withMessages(['cart' => 'The order total is below the card minimum.']);
            }

            $order = new Order;
            $order->fill($customer);
            $order->customer_email = strtolower(trim($order->customer_email));   // one normalized form, for every caller
            $order->uuid = (string) Str::uuid();
            $order->public_token_hash = hash('sha256', $token);
            $order->user_id = $user?->id;
            $order->status = OrderStatus::PendingPayment;
            $order->fulfilment = 'pickup';
            $order->estimated_cents = $quote['estimated_cents'];
            $order->hold_cents = $quote['hold_cents'];
            $order->currency = (string) config('catchweight.currency');
            $order->hold_tolerance_pct = self::pct('catchweight.hold_tolerance_pct');
            $order->overage_autocharge_pct = self::pct('catchweight.overage_autocharge_pct');
            $order->underweight_review_pct = self::pct('catchweight.underweight_review_pct');
            $order->save();

            $order->number = 'HB-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($quote['lines'] as $line) {
                $item = new OrderItem;
                $item->product_id = $line['product']->id;
                $item->product_name = $line['product']->name;
                $item->quantity = $line['quantity'];
                $item->price_per_lb_cents = $line['product']->price_per_lb_cents;
                $item->estimated_weight_lb = $line['weight'];
                $item->estimated_cents = $line['estimated_cents'];
                $order->items()->save($item);
            }

            return $order;
        });

        try {
            $customerId = $this->payments->createCustomer($order->customer_name, $order->customer_email, $order->idempotencyKey('customer'));
            $intent = $this->payments->createHold(
                $order->hold_cents,
                $customerId,
                "Halal Brothers order {$order->number} (hold: estimate + {$order->hold_tolerance_pct}%)",
                ['order_uuid' => $order->uuid, 'order_number' => (string) $order->number, 'purpose' => 'hold'],
                $order->idempotencyKey('hold'),
            );
        } catch (Throwable $e) {
            $order->status = OrderStatus::PaymentFailed;
            $order->save();

            throw $e;
        }

        $order->stripe_customer_id = $customerId;
        $order->stripe_payment_intent_id = $intent->id;
        $order->save();

        $transaction = new PaymentTransaction;
        $transaction->fill([
            'type' => PaymentTransactionType::Authorization,
            'status' => PaymentTransaction::PENDING,
            'amount_cents' => $order->hold_cents,
            'stripe_object_id' => $intent->id,
            'idempotency_key' => $order->idempotencyKey('hold'),
        ]);
        $order->transactions()->save($transaction);

        return ['order' => $order, 'token' => $token];
    }

    private static function pct(string $key): string
    {
        return number_format((float) config($key), 2, '.', '');
    }
}
