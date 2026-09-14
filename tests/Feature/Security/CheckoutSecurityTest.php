<?php

declare(strict_types=1);

use App\Models\Order;
use Tests\TestCase;

/*
 * Gap 02 (client-supplied prices) and gap 11 (rate limiting) at the HTTP boundary —
 * the same guarantees PlaceOrderTest checks at the action level, exercised through the real route.
 */

beforeEach(function () {
    fakePayments();
    $this->product = product(priceCents: 500, estLb: '2.000');
    $this->validPayload = [
        'customer_name' => 'Test Buyer',
        'customer_email' => 'buyer@example.com',
        'customer_phone' => '267-555-0123',
        'agree_catch_weight' => '1',
        'expected_hold_cents' => 1100,
    ];
});

function withCartLine(object $ctx): TestCase
{
    return test()->withSession(['cart.lines' => [$ctx->product->id => 1]]);
}

it('rejects a request that tries to smuggle a price field into checkout', function () {
    foreach (['amount' => 1, 'price' => 1, 'hold_cents' => 1, 'total' => 1, 'estimated_cents' => 1] as $field => $value) {
        withCartLine($this)
            ->post('/checkout', $this->validPayload + [$field => $value])
            ->assertSessionHasErrors($field);
    }

    expect(Order::query()->count())->toBe(0);
});

it('rejects checkout when the posted hold no longer matches the server price', function () {
    withCartLine($this)
        ->post('/checkout', ['customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.com', 'customer_phone' => '267-555-0123', 'agree_catch_weight' => '1', 'expected_hold_cents' => 1])
        ->assertSessionHasErrors('cart');

    expect(Order::query()->count())->toBe(0);
});

it('requires the catch-weight acknowledgement checkbox', function () {
    withCartLine($this)
        ->post('/checkout', collect($this->validPayload)->except('agree_catch_weight')->all())
        ->assertSessionHasErrors('agree_catch_weight');
});

it('throttles repeated checkout attempts from the same client', function () {
    // The rate limit fires from middleware before the controller runs, so the payload's validity
    // doesn't matter here — only that six POSTs within a minute trip the 5-per-minute limit.
    for ($i = 0; $i < 5; $i++) {
        $this->post('/checkout', [])->assertStatus(302);
    }

    $this->post('/checkout', [])->assertStatus(429);
});
