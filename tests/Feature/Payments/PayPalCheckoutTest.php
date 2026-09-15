<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Support\Weight;

/*
 * Guideline ch. 6, Sprint 06: PayPal joins the Stripe card rail. Unlike Stripe's client-side
 * confirmation, PayPal needs an explicit confirmAuthorization() call once the customer returns from
 * approving on paypal.com — see App\Http\Controllers\Shop\PayPalReturnController.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
});

it('places a PayPal order with no Stripe fields set at all', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        paymentMethod: 'paypal',
    );

    expect($order->payment_method)->toBe('paypal')
        ->and($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->payment_reference)->not->toBeNull()
        ->and($order->stripe_payment_intent_id)->toBeNull();
});

it('authorizes once the customer returns from approving on PayPal', function () {
    $order = pendingPayPalOrder($this);

    $intent = $this->gateway->confirmAuthorization($order->payment_reference, 'test-key');
    $authorized = app(MarkOrderAuthorized::class)->handle($order, $intent);

    expect($authorized)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::Authorized)
        ->and($order->fresh()->payment_reference)->toBe($intent->id);
});

it('settles a PayPal order through capture, using gatewayIntentId() not the Stripe column', function () {
    $order = settledPayPalOrder($this, estLb: '1.900');   // under the hold

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and($this->gateway->callsFor('capture'))->toHaveCount(1);
});

it('falls back to a payment link for PayPal overage, since off-session recharge needs Vault approval it doesn\'t have', function () {
    $this->gateway->offSessionUnsupported = true;
    $order = settledPayPalOrder($this, estLb: '2.400');   // above the hold (1100c), within estimate(1000c)+25%=1250c

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingBalance)
        ->and($order->fresh()->balance_payment_url)->not->toBeNull()
        ->and($this->gateway->callsFor('off_session'))->toBeEmpty();
});

/** Places a PayPal order, still awaiting the customer's return from paypal.com. */
function pendingPayPalOrder(object $ctx): Order
{
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        paymentMethod: 'paypal',
    );

    return $order;
}

/** Places, authorizes, weighs, QCs and finalizes a PayPal order at the given actual weight. */
function settledPayPalOrder(object $ctx, string $estLb): Order
{
    $order = pendingPayPalOrder($ctx);
    $intent = $ctx->gateway->confirmAuthorization($order->payment_reference, 'confirm-key');
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds($estLb), WeightSource::Manual, $ctx->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $ctx->frontDesk);   // dispatches SettleOrderPayment on the sync queue

    return $order->fresh();
}
