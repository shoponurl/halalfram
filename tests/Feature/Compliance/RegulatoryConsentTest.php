<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;

/*
 * Owner decision (guideline ch. 7, S07, recorded 2026-09-15): USDA-inspected facility. Checkout
 * must show the disclosure and record exactly which version of it a customer agreed to.
 */

beforeEach(function () {
    fakePayments();
});

it('records the current regulatory notice version on a normally-placed order', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($order->regulatory_consent_at)->not->toBeNull()
        ->and($order->regulatory_consent_version)->toBe((string) config('catchweight.regulatory_notice_version'));
});

it('records no consent when explicitly told none was given', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
        regulatoryConsent: false,
    );

    expect($order->regulatory_consent_at)->toBeNull()
        ->and($order->regulatory_consent_version)->toBeNull();
});

it('rejects web checkout when the USDA notice checkbox is not checked', function () {
    $product = product(priceCents: 500, estLb: '2.000');

    test()->withSession(['cart.lines' => [$product->id => 1]])
        ->post('/checkout', [
            'customer_name' => 'Test Buyer',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '267-555-0123',
            'agree_catch_weight' => '1',
            // agree_regulatory_notice deliberately omitted
            'expected_hold_cents' => 1100,
            'fulfilment_method' => 'pickup',
            'payment_method' => 'card',
        ])
        ->assertSessionHasErrors('agree_regulatory_notice');
});
