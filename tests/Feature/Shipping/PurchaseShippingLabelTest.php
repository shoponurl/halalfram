<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Actions\Shipping\ComputeShipDate;
use App\Enums\FulfilmentStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Jobs\PurchaseShippingLabel;
use App\Models\Order;
use App\Shipping\ShippingGateway;
use App\Support\Weight;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
 * Guideline DoD (S08): "the system itself blocks shipping on the wrong day" — this job refuses to
 * call the carrier except on a valid ship date, redispatching itself for the next one instead.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->shipping = fakeShipping();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
    packingRule();
});

function settledShippingOrder(object $ctx): Order
{
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] + $ctx->shipping->rateCents,
        fulfilment: ['method' => 'shipping', 'address_line1' => '123 Main St', 'city' => 'New York', 'state' => 'NY', 'zip' => '10001'],
    );
    $intent = $ctx->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $ctx->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');

    return $order->fresh();
}

it('buys the label immediately on a valid ship day', function () {
    test()->travelTo(Carbon::parse('2026-09-15 10:00:00'));   // a Tuesday
    $order = settledShippingOrder($this);

    app(FinalizeOrder::class)->handle($order, $this->frontDesk);

    expect($this->shipping->labelsBought)->toHaveCount(1)
        ->and($order->fresh()->fulfilment_status)->toBe(FulfilmentStatus::Shipped);
});

it('redispatches instead of buying a label on an invalid ship day', function () {
    test()->travelTo(Carbon::parse('2026-09-18 10:00:00'));   // a Friday
    $order = settledShippingOrder($this);
    Queue::fake();

    (new PurchaseShippingLabel($order->id))->handle(app(ShippingGateway::class), app(ComputeShipDate::class));

    expect($this->shipping->labelsBought)->toBeEmpty();
    Queue::assertPushed(PurchaseShippingLabel::class);
});

it('never buys a second label for the same order', function () {
    test()->travelTo(Carbon::parse('2026-09-15 10:00:00'));
    $order = settledShippingOrder($this);
    app(FinalizeOrder::class)->handle($order, $this->frontDesk);
    expect($this->shipping->labelsBought)->toHaveCount(1);

    (new PurchaseShippingLabel($order->id))->handle(app(ShippingGateway::class), app(ComputeShipDate::class));

    expect($this->shipping->labelsBought)->toHaveCount(1);
});
