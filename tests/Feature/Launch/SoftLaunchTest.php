<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/*
 * Guideline ch. 6, Sprint 09: the soft launch is store pickup plus a few delivery zip codes, and no
 * nationwide shipping — enforced in PlaceOrder, not just hidden in the checkout page.
 */

beforeEach(function () {
    fakePayments();
    $this->shipping = fakeShipping();
    packingRule();
    $this->product = product(priceCents: 500, estLb: '2.000');
    $this->hold = app(QuoteCart::class)->handle([$this->product->id => 1])['hold_cents'];
    $this->customer = ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'];
    config(['launch.soft_launch.enabled' => true, 'launch.soft_launch.delivery_zips' => ['19050']]);
});

it('still takes pickup orders', function () {
    ['order' => $order] = app(PlaceOrder::class)->handle(lines: [$this->product->id => 1], customer: $this->customer, expectedHoldCents: $this->hold);

    expect($order->fulfilment)->toBe('pickup');
});

it('delivers only to the soft-launch zips, even inside an active delivery zone', function () {
    deliveryZoneForZip('19050', feeCents: 500);
    deliveryZoneForZip('19018', feeCents: 500);
    $slot = deliverySlot(capacity: 5);
    $delivery = fn (string $zip) => ['method' => 'delivery', 'address_line1' => '1 Main St', 'city' => 'Lansdowne', 'state' => 'PA', 'zip' => $zip, 'delivery_slot_id' => $slot->id];

    ['order' => $order] = app(PlaceOrder::class)->handle(lines: [$this->product->id => 1], customer: $this->customer, expectedHoldCents: $this->hold + 500, fulfilment: $delivery('19050'));
    expect($order->fulfilment)->toBe('delivery');

    expect(fn () => app(PlaceOrder::class)->handle(lines: [$this->product->id => 1], customer: $this->customer, expectedHoldCents: $this->hold + 500, fulfilment: $delivery('19018')))
        ->toThrow(ValidationException::class, 'opening delivery gradually');
    expect(Order::query()->count())->toBe(1);
});

it('refuses nationwide shipping during the soft launch', function () {
    $shipping = ['method' => 'shipping', 'address_line1' => '123 Main St', 'city' => 'New York', 'state' => 'NY', 'zip' => '10001'];

    expect(fn () => app(PlaceOrder::class)->handle(lines: [$this->product->id => 1], customer: $this->customer, expectedHoldCents: $this->hold + $this->shipping->rateCents, fulfilment: $shipping))
        ->toThrow(ValidationException::class, 'shipping isn\'t available yet');
    expect(Order::query()->count())->toBe(0);
});

it('hides shipping, and hides delivery entirely when no zips are open', function () {
    config(['launch.soft_launch.delivery_zips' => []]);

    $this->withSession(['cart.lines' => [$this->product->id => 1]])
        ->get(route('checkout.create', ['fulfilment' => 'shipping']))
        ->assertOk()
        ->assertDontSee('Ship nationwide')
        ->assertDontSee('Local delivery');
});

it('offers everything again once the soft launch is switched off', function () {
    config(['launch.soft_launch.enabled' => false]);

    $this->withSession(['cart.lines' => [$this->product->id => 1]])
        ->get(route('checkout.create'))
        ->assertSee('Ship nationwide')
        ->assertSee('Local delivery');
});
