<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/*
 * Owner decision (guideline ch. 7, S06): a coupon discounts the final, actual-weight total — never
 * the checkout estimate.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
});

it('accepts a valid coupon code at checkout without changing the hold', function () {
    coupon('SAVE10', 'percent', 10);
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        couponCode: 'SAVE10',
    );

    expect($order->coupon_id)->not->toBeNull()
        ->and($order->hold_cents)->toBe($quote['hold_cents']);
});

it('rejects an unknown coupon code, creating nothing', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        couponCode: 'NOPE',
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0);
});

it('applies a percent discount against the final weighed total, not the estimate', function () {
    coupon('SAVE10', 'percent', 10);
    $order = settledOrderWithCoupon($this, 'SAVE10');

    // 2.000 lb x 500c/lb = 1000c final, minus 10% = 900c
    expect($order->discount_cents)->toBe(100)
        ->and($order->final_cents)->toBe(900)
        ->and($this->gateway->callsFor('capture')[0]['amount'])->toBe(900);
});

it('applies a fixed discount, never exceeding the total itself', function () {
    coupon('BIGOFF', 'fixed', 5000);   // $50 off a much smaller order
    $order = settledOrderWithCoupon($this, 'BIGOFF');

    expect($order->discount_cents)->toBe(1000)   // capped at the $10 total
        ->and($order->final_cents)->toBe(0);
});

it('rejects placing a second order once a coupon hits its max redemptions', function () {
    $c = coupon('ONE-USE');
    $c->update(['max_redemptions' => 1]);
    settledOrderWithCoupon($this, 'ONE-USE');
    expect($c->fresh()->redeemed_count)->toBe(1);

    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'B', 'customer_email' => 'b@example.com', 'customer_phone' => '2675550124'],
        expectedHoldCents: $quote['hold_cents'],
        couponCode: 'ONE-USE',
    ))->toThrow(ValidationException::class);
});

it('re-checks max redemptions at finalize, in case another order used the last slot in between', function () {
    $c = coupon('RACE');
    $c->update(['max_redemptions' => 1]);
    $order = settledOrderWithCoupon($this, 'RACE', finalize: false);

    // Simulates a concurrent order finalizing first and using the coupon's only slot
    $c->forceFill(['redeemed_count' => 1])->save();

    app(FinalizeOrder::class)->handle($order->fresh(), $this->frontDesk);
    expect($order->fresh()->discount_cents)->toBe(0);
});

/** Places, pays and (unless $finalize is false) weighs, QCs and finalizes an order with the given coupon applied. */
function settledOrderWithCoupon(object $ctx, string $code, bool $finalize = true): Order
{
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        couponCode: $code,
    );
    $intent = $ctx->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $ctx->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');

    if ($finalize) {
        app(FinalizeOrder::class)->handle($order->fresh(), $ctx->frontDesk);
    }

    return $order->fresh();
}
