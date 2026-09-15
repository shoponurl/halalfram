<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Actions\Delivery\ReserveDeliverySlot;
use App\Actions\Inventory\ReleaseStock;
use App\Actions\Inventory\ReserveStock;
use App\Actions\Payments\RedeemStoreCredit;
use App\Actions\Production\ScheduleOrder;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Jobs\DispatchOrderNotification;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Payments\PaymentGatewayFactory;
use App\Payments\TaxCalculator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

/**
 * Checkout: prices the cart on the server, saves the order, and (for card/PayPal) opens the hold.
 *
 * Creating the hold is synchronous on purpose (the browser needs it to show the payment form); every
 * call after authorization runs on the queue (rule 08). Cash on pickup (guideline ch. 7, S06) skips
 * the hold entirely — there's no card, so the order goes straight to Authorized once placed.
 */
final class PlaceOrder extends Action
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly QuoteCart $quote,
        private readonly ReserveStock $reserveStock,
        private readonly ReleaseStock $releaseStock,
        private readonly ScheduleOrder $scheduleOrder,
        private readonly ReserveDeliverySlot $reserveDeliverySlot,
        private readonly RedeemStoreCredit $redeemStoreCredit,
        private readonly TaxCalculator $tax,
    ) {}

    /**
     * @param  array<int|string, int|array<string, int|null>>  $lines  raw cart lines, straight from the session
     * @param  array{customer_name: string, customer_email: string, customer_phone: string, notes?: string|null, marketing_sms_opt_in?: bool, marketing_email_opt_in?: bool}  $customer
     * @param  int  $expectedHoldCents  the hold the customer saw (including delivery fee, tax and any store credit); any difference means prices changed or were tampered with
     * @param  array{method?: string, address_line1?: string, address_line2?: string|null, city?: string, state?: string, zip?: string, delivery_slot_id?: int}  $fulfilment  defaults to store pickup
     * @param  string  $paymentMethod  card | paypal | cash — cash is pickup-only, capped at config('catchweight.cod_max_order_cents') (owner decision, guideline ch. 7, S06)
     * @return array{order: Order, token: string}
     */
    public function handle(
        array $lines,
        array $customer,
        int $expectedHoldCents,
        array $fulfilment = ['method' => 'pickup'],
        ?User $user = null,
        string $paymentMethod = 'card',
        ?string $couponCode = null,
        bool $applyStoreCredit = false,
    ): array {
        if ($lines === []) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        $token = Str::random(48);

        $order = $this->transaction(function () use ($lines, $customer, $fulfilment, $expectedHoldCents, $user, $token, $paymentMethod, $couponCode, $applyStoreCredit): Order {
            $quote = $this->quote->handle($lines, lockProducts: true);

            $isDelivery = ($fulfilment['method'] ?? 'pickup') === 'delivery';
            if ($paymentMethod === 'cash' && $isDelivery) {
                throw ValidationException::withMessages(['payment_method' => 'Cash is only available for store pickup.']);
            }

            $deliveryZone = null;
            $deliverySlot = null;
            if ($isDelivery) {
                // Owner decision (guideline ch. 7, S05): the service area is an explicit zip list.
                $deliveryZone = $this->reserveDeliverySlot->zoneForZip((string) ($fulfilment['zip'] ?? ''));
                $deliverySlot = $this->reserveDeliverySlot->handle((int) ($fulfilment['delivery_slot_id'] ?? 0));
            }
            $deliveryFeeCents = $deliveryZone->flat_fee_cents ?? 0;

            $taxCents = $this->tax->calculate($quote['estimated_cents'] + $deliveryFeeCents);
            $totalEstimatedCents = $quote['estimated_cents'] + $deliveryFeeCents + $taxCents;
            $preCreditHoldCents = $quote['hold_cents'] + $deliveryFeeCents + $taxCents;

            $email = strtolower(trim((string) $customer['customer_email']));
            $minimumChargeCents = (int) config('catchweight.minimum_charge_cents');
            // Same floor for every payment method, cash included — keeps this figure trivial for the
            // checkout page to reproduce exactly (rule 02's tamper cross-check needs an exact match).
            $creditRequested = $applyStoreCredit ? max(0, $preCreditHoldCents - $minimumChargeCents) : 0;
            $creditAppliedCents = $creditRequested > 0 ? $this->redeemStoreCredit->handle($email, $creditRequested) : 0;
            $totalHoldCents = $preCreditHoldCents - $creditAppliedCents;

            // Owner decision (guideline ch. 7, S06): cash on pickup is capped so a no-show can't leave
            // too much wasted, never-billed product on the books.
            if ($paymentMethod === 'cash' && $totalHoldCents > (int) config('catchweight.cod_max_order_cents')) {
                throw ValidationException::withMessages(['payment_method' => 'This order is too large for cash on pickup — please pay by card instead.']);
            }

            // Rule 02: amounts are computed here; the browser's number is only a cross-check
            if ($totalHoldCents !== $expectedHoldCents) {
                throw ValidationException::withMessages([
                    'cart' => 'Prices changed since you opened checkout. Please review the new total and try again.',
                ]);
            }
            if ($paymentMethod !== 'cash' && $totalHoldCents < $minimumChargeCents) {
                throw ValidationException::withMessages(['cart' => 'The order total is below the card minimum.']);
            }

            // Owner decision (guideline ch. 7, S06): a coupon discounts the final amount, not this
            // estimate — checkout only confirms the code is currently usable; FinalizeOrder locks in
            // the actual discount against the real weighed total.
            $coupon = null;
            if ($couponCode !== null && $couponCode !== '') {
                $coupon = Coupon::query()->where('code', $couponCode)->first();
                if ($coupon === null) {
                    throw ValidationException::withMessages(['coupon_code' => 'That coupon code isn\'t valid.']);
                }
                $coupon->assertUsableFor($quote['estimated_cents']);
            }

            $defaultMinutes = (int) config('catchweight.default_processing_minutes');
            $totalMinutes = array_sum(array_map(
                fn (array $line) => ($line['cut_option']?->minutesPerPiece() ?? $defaultMinutes) * $line['quantity'],
                $quote['lines'],
            ));
            $scheduledDate = $this->scheduleOrder->handle($totalMinutes);

            $order = new Order;
            $order->fill($customer);
            $order->customer_email = $email;   // one normalized form, for every caller
            $order->uuid = (string) Str::uuid();
            $order->public_token_hash = hash('sha256', $token);
            $order->user_id = $user?->id;
            $order->payment_method = $paymentMethod;
            $order->status = $paymentMethod === 'cash' ? OrderStatus::Authorized : OrderStatus::PendingPayment;
            $order->fulfilment = $isDelivery ? 'delivery' : 'pickup';
            $order->fulfilment_status = FulfilmentStatus::AwaitingFulfilment;
            $order->lead_time_days = $quote['lead_time_days'];
            $order->scheduled_date = $scheduledDate;
            $order->estimated_cents = $totalEstimatedCents;
            $order->hold_cents = $totalHoldCents;
            $order->currency = (string) config('catchweight.currency');
            $order->delivery_fee_cents = $deliveryFeeCents;   // 0 for pickup — set unconditionally so it's never left null in-memory
            $order->tax_cents = $taxCents;
            $order->coupon_id = $coupon?->id;
            $order->store_credit_applied_cents = $creditAppliedCents;
            if ($isDelivery) {
                $order->delivery_zone_id = $deliveryZone?->id;
                $order->delivery_slot_id = $deliverySlot?->id;
                $order->delivery_address_line1 = (string) ($fulfilment['address_line1'] ?? '');
                $order->delivery_address_line2 = $fulfilment['address_line2'] ?? null;
                $order->delivery_city = (string) ($fulfilment['city'] ?? '');
                $order->delivery_state = (string) ($fulfilment['state'] ?? 'PA');
                $order->delivery_zip = (string) ($fulfilment['zip'] ?? '');
            }
            if ($paymentMethod === 'cash') {
                $order->authorized_at = now();
            }
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
                $item->cut_option_id = $line['cut_option']?->id;
                $item->cut_option_name = $line['cut_option']?->name;
                $item->cut_option_price_cents = $line['cut_option']->extra_price_cents ?? 0;
                $item->offal_option_id = $line['offal_option']?->id;
                $item->offal_option_name = $line['offal_option']?->name;
                $item->offal_option_price_cents = $line['offal_option']->extra_price_cents ?? 0;
                $item->packing_option_id = $line['packing_option']?->id;
                $item->packing_option_name = $line['packing_option']?->name;
                $item->packing_option_price_cents = $line['packing_option']->surcharge_cents ?? 0;
                $item->lead_time_days = $line['lead_time_days'];
                $item->estimated_minutes = ($line['cut_option']?->minutesPerPiece() ?? $defaultMinutes) * $line['quantity'];
                $order->items()->save($item);

                // Sprint 03 (guideline ch. 6): reserves raw stock FEFO; a no-op for products not yet
                // under inventory tracking, so Sprint 01/02 checkout is unaffected either way.
                $this->reserveStock->handle($item, $line['product'], $line['cut_option']);
            }

            return $order;
        });

        if ($order->payment_method !== 'cash') {
            $gateway = $this->gateways->for($order->payment_method);
            if ($gateway === null) {
                throw new LogicException("Payment method \"{$order->payment_method}\" has no gateway.");
            }

            try {
                $metadata = ['order_uuid' => $order->uuid, 'order_number' => (string) $order->number, 'purpose' => 'hold'];
                if ($order->payment_method === 'paypal') {
                    $metadata['return_url'] = route('checkout.paypal.return', $order);
                    $metadata['cancel_url'] = route('checkout.paypal.cancel', $order);
                }
                $customerId = $gateway->createCustomer($order->customer_name, $order->customer_email, $order->idempotencyKey('customer'));
                $intent = $gateway->createHold(
                    $order->hold_cents,
                    $customerId,
                    "Halal Brothers order {$order->number} (hold: estimate + {$order->hold_tolerance_pct}%)",
                    $metadata,
                    $order->idempotencyKey('hold'),
                );
            } catch (Throwable $e) {
                $order->status = OrderStatus::PaymentFailed;
                $order->save();
                $this->releaseStock->handle($order);

                throw $e;
            }

            if ($order->payment_method === 'paypal') {
                $order->payment_reference = $intent->id;
            } else {
                $order->stripe_customer_id = $customerId;
                $order->stripe_payment_intent_id = $intent->id;
            }
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
        }

        DispatchOrderNotification::dispatch($order->id, NotificationEvent::OrderPlaced->value);

        return ['order' => $order, 'token' => $token];
    }

    private static function pct(string $key): string
    {
        return number_format((float) config($key), 2, '.', '');
    }
}
