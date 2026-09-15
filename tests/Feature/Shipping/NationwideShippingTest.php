<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\PackageTemperature;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Support\Weight;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/*
 * Owner decisions (guideline ch. 7, S08, recorded 2026-09-15): interstate shipping confirmed;
 * overnight only; frozen by default (chilled only where a product needs it); never cash.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->shipping = fakeShipping();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
    packingRule(minWeightLb: '0.00', maxWeightLb: '20.00', temperature: PackageTemperature::Frozen);
    packingRule(minWeightLb: '0.00', maxWeightLb: '20.00', temperature: PackageTemperature::Chilled);
});

function shippingAddress(): array
{
    return [
        'method' => 'shipping',
        'address_line1' => '123 Main St',
        'city' => 'New York',
        'state' => 'NY',
        'zip' => '10001',
    ];
}

it('includes the shipping rate in both the estimate and the hold', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    $this->shipping->rateCents = 4500;
    $expectedHold = $quote['hold_cents'] + 4500;

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $expectedHold,
        fulfilment: shippingAddress(),
    );

    expect($order->fulfilment)->toBe('shipping')
        ->and($order->shipping_rate_cents)->toBe(4500)
        ->and($order->hold_cents)->toBe($expectedHold)
        ->and($order->package_temperature)->toBe(PackageTemperature::Frozen)
        ->and($order->packing_rule_id)->not->toBeNull();
});

it('rejects cash for a shipping order', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + 4500,
        fulfilment: shippingAddress(),
        paymentMethod: 'cash',
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('ships chilled when any item in the order requires it', function () {
    $chilledProduct = product(priceCents: 500, estLb: '2.000');
    $chilledProduct->requires_chilled_shipping = true;
    $chilledProduct->save();
    $quote = app(QuoteCart::class)->handle([$chilledProduct->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$chilledProduct->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + $this->shipping->rateCents,
        fulfilment: shippingAddress(),
    );

    expect($order->package_temperature)->toBe(PackageTemperature::Chilled);
});

it('rejects a shipping quote when no packing rule covers the order weight', function () {
    $product = product(priceCents: 500, estLb: '100.000');   // way over any packing rule's max
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        fulfilment: shippingAddress(),
    ))->toThrow(ValidationException::class);
});

it('buys the shipping label only after settlement completes', function () {
    $this->travelTo(Carbon::parse('2026-09-15 10:00:00'));   // a Tuesday — a valid ship day regardless of when the suite runs
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + $this->shipping->rateCents,
        fulfilment: shippingAddress(),
    );
    $intent = $this->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $this->butcher, '38.0');

    expect($this->shipping->labelsBought)->toBeEmpty();
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);   // dispatches SettleOrderPayment on the sync queue, which completes and buys the label

    expect($this->shipping->labelsBought)->toHaveCount(1)
        ->and($order->fresh()->tracking_number)->not->toBeNull();
});
