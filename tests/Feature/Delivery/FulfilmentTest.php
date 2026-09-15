<?php

declare(strict_types=1);

use App\Actions\Delivery\MarkDelivered;
use App\Actions\Delivery\MarkDeliveryFailed;
use App\Actions\Delivery\MarkOutForDelivery;
use App\Actions\Delivery\MarkPickedUp;
use App\Actions\Delivery\MarkReadyForPickup;
use App\Actions\Delivery\RescheduleDelivery;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/*
 * Owner decisions (guideline ch. 7, S05): no one home → return to store → one free re-attempt →
 * refund minus the delivery fee; proof of delivery is an OTP or a photo.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->manager = staff(Role::Manager);
    $this->driver = staff(Role::Driver);
    $this->frontDesk = staff(Role::FrontDesk);
    $this->butcher = staff(Role::Butcher);
});

/** Places, pays and fully settles a single-item order, ready for fulfilment. */
function settledOrder(object $ctx, array $fulfilment = ['method' => 'pickup']): Order
{
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    $fee = $fulfilment['method'] === 'delivery' ? 500 : 0;

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + $fee,
        fulfilment: $fulfilment,
    );
    $intent = $ctx->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $ctx->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $ctx->frontDesk);

    return $order->fresh();
}

it('marks a pickup order ready, then picked up', function () {
    $order = settledOrder($this);
    expect($order->status)->toBe(OrderStatus::Completed);

    app(MarkReadyForPickup::class)->handle($order, $this->manager);
    $order = $order->fresh();
    expect($order->fulfilment_status)->toBe(FulfilmentStatus::ReadyForPickup)
        ->and($order->ready_notified_at)->not->toBeNull()
        ->and($order->deliveryEvents)->toHaveCount(1);

    app(MarkPickedUp::class)->handle($order, $this->manager);
    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::PickedUp);
});

it('runs a delivery from dispatch to delivered with the customer\'s OTP', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot();
    $order = settledOrder($this, ['method' => 'delivery', 'address_line1' => '1 Main St', 'city' => 'Lansdowne', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $slot->id]);

    $updated = app(MarkOutForDelivery::class)->handle($order, $this->driver);
    expect($updated->fulfilment_status)->toBe(FulfilmentStatus::OutForDelivery)
        ->and($updated->delivery_otp)->toMatch('/^\d{6}$/')
        ->and($updated->driver_id)->toBe($this->driver->id);

    expect(fn () => app(MarkDelivered::class)->handle($updated, $this->driver, otp: 'wrong'))->toThrow(ValidationException::class);

    app(MarkDelivered::class)->handle($updated->fresh(), $this->driver, otp: $updated->delivery_otp);
    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Delivered);
});

it('accepts a photo instead of an OTP for proof of delivery', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot();
    $order = settledOrder($this, ['method' => 'delivery', 'address_line1' => '1 Main St', 'city' => 'Lansdowne', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $slot->id]);
    app(MarkOutForDelivery::class)->handle($order, $this->driver);

    app(MarkDelivered::class)->handle($order->fresh(), $this->driver, proofPhotoPath: 'delivery-proof/door.jpg');

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Delivered)
        ->and($order->fresh()->deliveryEvents->last()->proof_photo_path)->toBe('delivery-proof/door.jpg');
});

it('refuses to mark delivered without an OTP or a photo', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot();
    $order = settledOrder($this, ['method' => 'delivery', 'address_line1' => '1 Main St', 'city' => 'Lansdowne', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $slot->id]);
    app(MarkOutForDelivery::class)->handle($order, $this->driver);

    expect(fn () => app(MarkDelivered::class)->handle($order->fresh(), $this->driver))->toThrow(ValidationException::class);
});

it('allows one free re-attempt after a failed delivery, then returns and refunds minus the delivery fee on the second failure', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot();
    $order = settledOrder($this, ['method' => 'delivery', 'address_line1' => '1 Main St', 'city' => 'Lansdowne', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $slot->id]);
    $finalTotal = $order->final_cents;

    app(MarkOutForDelivery::class)->handle($order, $this->driver);
    app(MarkDeliveryFailed::class)->handle($order->fresh(), $this->driver, 'No answer');
    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::DeliveryFailed)
        ->and($order->fresh()->delivery_attempts)->toBe(1)
        ->and($this->gateway->callsFor('refund'))->toBeEmpty();   // still one free re-attempt left

    // Re-attempt
    app(MarkOutForDelivery::class)->handle($order->fresh(), $this->driver);
    app(MarkDeliveryFailed::class)->handle($order->fresh(), $this->driver, 'No answer again');

    $order = $order->fresh();
    expect($order->fulfilment_status)->toBe(FulfilmentStatus::Refunded)
        ->and($order->delivery_attempts)->toBe(2)
        ->and($order->refunded_cents)->toBe($finalTotal - 500)   // minus the delivery fee
        ->and($this->gateway->callsFor('refund'))->toHaveCount(1);
});

it('lets staff reschedule a failed delivery to a new open slot', function () {
    deliveryZoneForZip('19050');
    $firstSlot = deliverySlot(daysAhead: 1);
    $secondSlot = deliverySlot(daysAhead: 2);
    $order = settledOrder($this, ['method' => 'delivery', 'address_line1' => '1 Main St', 'city' => 'Lansdowne', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $firstSlot->id]);

    app(MarkOutForDelivery::class)->handle($order, $this->driver);
    app(MarkDeliveryFailed::class)->handle($order->fresh(), $this->driver, 'No answer');

    app(RescheduleDelivery::class)->handle($order->fresh(), $secondSlot, $this->manager);
    $order = $order->fresh();

    expect($order->delivery_slot_id)->toBe($secondSlot->id)
        ->and($order->fulfilment_status)->toBe(FulfilmentStatus::AwaitingFulfilment);
});

it('refuses fulfilment actions before the order is actually paid', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($this->manager->can('manageFulfilment', $order))->toBeFalse();
    expect(fn () => app(MarkReadyForPickup::class)->handle($order, $this->manager))->toThrow(ValidationException::class);
});
