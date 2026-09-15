<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/*
 * Owner decisions (guideline ch. 7, S05): the service area is an explicit zip list; delivery is a
 * flat fee per zone; a slot's driver capacity is real, checked against real bookings, not just the
 * scheduler in isolation.
 */

beforeEach(function () {
    fakePayments();
    $this->product = product(priceCents: 500, estLb: '2.000');   // 500 × 2 = 1000/piece
});

it('adds the zone flat fee to the estimate and hold when delivery is chosen', function () {
    $zone = deliveryZoneForZip('19050', feeCents: 500);
    $slot = deliverySlot();

    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + 500,
        fulfilment: [
            'method' => 'delivery',
            'address_line1' => '123 Main St',
            'city' => 'Lansdowne',
            'state' => 'PA',
            'zip' => '19050',
            'delivery_slot_id' => $slot->id,
        ],
    );

    expect($order->fulfilment)->toBe('delivery')
        ->and($order->delivery_zone_id)->toBe($zone->id)
        ->and($order->delivery_slot_id)->toBe($slot->id)
        ->and($order->delivery_fee_cents)->toBe(500)
        ->and($order->estimated_cents)->toBe(1500)   // 1000 + 500
        ->and($order->hold_cents)->toBe($quote['hold_cents'] + 500);
});

it('rejects delivery to a zip outside the service area, and creates nothing', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot();

    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        fulfilment: ['method' => 'delivery', 'address_line1' => 'x', 'city' => 'x', 'state' => 'PA', 'zip' => '99999', 'delivery_slot_id' => $slot->id],
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('rejects a delivery slot that is already fully booked', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot(capacity: 1);

    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    $fulfilment = ['method' => 'delivery', 'address_line1' => 'x', 'city' => 'x', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $slot->id];

    // Fills the only slot
    app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + 500,
        fulfilment: $fulfilment,
    );

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'B', 'customer_email' => 'b@example.com', 'customer_phone' => '2675550124'],
        expectedHoldCents: $quote['hold_cents'] + 500,
        fulfilment: $fulfilment,
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(1);
});

it('defaults to free store pickup when no fulfilment is specified (Sprint 01-04 behavior unchanged)', function () {
    $quote = app(QuoteCart::class)->handle([$this->product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($order->fulfilment)->toBe('pickup')
        ->and($order->delivery_fee_cents)->toBe(0)
        ->and($order->estimated_cents)->toBe($quote['estimated_cents']);
});

it('shows the delivery fee and lets the customer check a zip from the checkout page', function () {
    deliveryZoneForZip('19050', feeCents: 500);
    deliverySlot();
    $this->withSession(['cart.lines' => [$this->product->id => 1]]);

    $this->get('/checkout?fulfilment=delivery&zip=19050')
        ->assertOk()
        ->assertSee('$5.00', false)
        ->assertSee('Delivery window');

    $this->get('/checkout?fulfilment=delivery&zip=00000')
        ->assertOk()
        ->assertSee('don&#039;t deliver to 00000', false);
});
