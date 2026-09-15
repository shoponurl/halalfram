<?php

declare(strict_types=1);

use App\Actions\Delivery\MarkMissedPickup;
use App\Actions\Delivery\MarkReadyForPickup;
use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordCashPayment;
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
 * Owner decision (guideline ch. 7, S06): cash on pickup only, capped so a no-show can't leave too
 * much wasted, never-billed product on the books.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->manager = staff(Role::Manager);
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
});

it('places a cash order with no card hold at all and moves straight to Authorized', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        paymentMethod: 'cash',
    );

    expect($order->payment_method)->toBe('cash')
        ->and($order->status)->toBe(OrderStatus::Authorized)
        ->and($order->stripe_payment_intent_id)->toBeNull()
        ->and($order->stripe_customer_id)->toBeNull();
});

it('rejects cash for a delivery order', function () {
    deliveryZoneForZip('19050');
    $slot = deliverySlot();
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + 500,
        fulfilment: ['method' => 'delivery', 'address_line1' => 'x', 'city' => 'x', 'state' => 'PA', 'zip' => '19050', 'delivery_slot_id' => $slot->id],
        paymentMethod: 'cash',
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('rejects a cash order over the configured cap', function () {
    config(['catchweight.cod_max_order_cents' => 500]);
    $product = product(priceCents: 5000, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        paymentMethod: 'cash',
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('finalizes a cash order to AwaitingCashPayment, skipping the card settlement queue entirely', function () {
    $order = placeCashOrder($this);

    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $this->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $this->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);

    $order = $order->fresh();
    expect($order->status)->toBe(OrderStatus::AwaitingCashPayment)
        ->and($order->final_cents)->toBe(1000)
        ->and($this->gateway->callsFor('capture'))->toBeEmpty();
});

it('lets front desk record the cash payment and complete the order, but not for less than what\'s owed', function () {
    $order = settledCashOrder($this);

    expect(fn () => app(RecordCashPayment::class)->handle($order, 500, $this->frontDesk))->toThrow(ValidationException::class);

    $completed = app(RecordCashPayment::class)->handle($order->fresh(), 1000, $this->frontDesk);
    expect($completed->status)->toBe(OrderStatus::Completed)
        ->and($completed->captured_cents)->toBe(1000)
        ->and($completed->totalChargedCents())->toBe(1000);
});

it('allows marking a cash order ready for pickup even though cash hasn\'t been collected yet', function () {
    $order = settledCashOrder($this);

    expect($this->manager->can('manageFulfilment', $order))->toBeTrue();
    app(MarkReadyForPickup::class)->handle($order, $this->manager);

    expect($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::ReadyForPickup);
});

it('writes off an unpaid cash order on a missed pickup, with no refund transaction since nothing was ever charged', function () {
    $order = settledCashOrder($this);
    app(MarkReadyForPickup::class)->handle($order, $this->manager);

    app(MarkMissedPickup::class)->handle($order->fresh());

    $order = $order->fresh();
    expect($order->status)->toBe(OrderStatus::Cancelled)
        ->and($order->written_off_cents)->toBe($order->final_cents)
        ->and($order->refunded_cents)->toBe(0)
        ->and($this->gateway->callsFor('refund'))->toBeEmpty();
});

/** Places a cash order, ready for weighing. */
function placeCashOrder(object $ctx): Order
{
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        paymentMethod: 'cash',
    );

    return $order;
}

/** Places, weighs, QCs and finalizes a cash order, ready for pickup/payment. */
function settledCashOrder(object $ctx): Order
{
    $order = placeCashOrder($ctx);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $ctx->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $ctx->frontDesk);

    return $order->fresh();
}
