<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Enums\OrderStatus;
use App\Enums\PaymentTransactionType;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->product = product(priceCents: 349, estLb: '3.500');   // 349 × 3.5 = 1221.5 → 1222
});

it('prices the cart on the server and opens a card hold for estimate + tolerance', function () {
    $quote = app(QuoteCart::class)->handle([$this->product->id => 2]);
    // Rounds once on the line's total weight, not per piece: 349¢ × 7.000 lb = 2443.0 → 2443
    // hold = 2443 × 1.10 = 2687.3 → 2687
    expect($quote['estimated_cents'])->toBe(2443)->and($quote['hold_cents'])->toBe(2687);

    ['order' => $order, 'token' => $token] = app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 2],
        customer: ['customer_name' => 'Amina Khan', 'customer_email' => 'AMINA@Example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: 2687,
    );

    expect($order->status)->toBe(OrderStatus::PendingPayment)
        ->and($order->estimated_cents)->toBe(2443)
        ->and($order->hold_cents)->toBe(2687)
        ->and($order->customer_email)->toBe('amina@example.com')   // normalized, never trusted verbatim for uniqueness
        ->and($order->number)->toStartWith('HB-')
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->quantity)->toBe(2)
        ->and(strlen($token))->toBeGreaterThan(30);

    $hold = $this->gateway->callsFor('hold');
    expect($hold)->toHaveCount(1)->and($hold[0]['amount'])->toBe(2687);
    expect($order->transactions()->where('type', PaymentTransactionType::Authorization)->where('status', 'pending')->exists())->toBeTrue();
});

it('rejects checkout when the price the browser saw no longer matches the server price (gap 02)', function () {
    expect(fn () => app(PlaceOrder::class)->handle(
        lines: [$this->product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: 1,   // tampered
    ))->toThrow(ValidationException::class);

    expect(Order::query()->count())->toBe(0)
        ->and($this->gateway->calls)->toBeEmpty();   // no Stripe call for a rejected order
});

it('rejects an empty cart', function () {
    expect(fn () => app(PlaceOrder::class)->handle([], ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'], 0))
        ->toThrow(ValidationException::class);
});

it('rejects a quantity above the maximum', function () {
    expect(fn () => app(QuoteCart::class)->handle([$this->product->id => QuoteCart::MAX_QUANTITY + 1]))
        ->toThrow(ValidationException::class);
});

it('never lets the deactivated or unknown product into an order', function () {
    $this->product->is_active = false;
    $this->product->save();

    expect(fn () => app(QuoteCart::class)->handle([$this->product->id => 1]))->toThrow(ValidationException::class);
    expect(fn () => app(QuoteCart::class)->handle([999999 => 1]))->toThrow(ValidationException::class);
});
