<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Payments\IssueStoreCredit;
use App\Actions\Payments\RedeemStoreCredit;
use App\Enums\Role;
use App\Models\Order;
use App\Models\StoreCreditAccount;

/*
 * Owner decision (guideline ch. 7, S06): store credit is store-use-only, never expires, and is
 * tracked as a liability (a cache over an append-only ledger, locked at spend time).
 */

beforeEach(function () {
    fakePayments();
    $this->manager = staff(Role::Manager);
});

it('issues credit that increases the cached balance and logs a ledger event', function () {
    $account = app(IssueStoreCredit::class)->handle('buyer@example.com', 1000, 'Goodwill gesture', $this->manager);

    expect($account->balance_cents)->toBe(1000)
        ->and($account->fresh()->events()->count())->toBe(1)
        ->and($account->events()->first()->type)->toBe('issue');
});

it('applies available store credit to the checkout hold, capped at the card minimum', function () {
    storeCredit('buyer@example.com', 100_000);   // way more than the order total
    $product = product(priceCents: 500, estLb: '2.000');   // 1000 cents
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    $minimum = (int) config('catchweight.minimum_charge_cents');

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $minimum,
        applyStoreCredit: true,
    );

    expect($order->store_credit_applied_cents)->toBe($quote['hold_cents'] - $minimum)
        ->and($order->hold_cents)->toBe($minimum)
        ->and(StoreCreditAccount::query()->find('buyer@example.com')->balance_cents)->toBe(100_000 - ($quote['hold_cents'] - $minimum));
});

it('applies only what\'s available when the balance is less than the full hold', function () {
    storeCredit('buyer@example.com', 300);
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'] - 300,
        applyStoreCredit: true,
    );

    expect($order->store_credit_applied_cents)->toBe(300)
        ->and(StoreCreditAccount::query()->find('buyer@example.com')->balance_cents)->toBe(0);
});

it('never redeems more than the available balance, even if asked to', function () {
    storeCredit('buyer@example.com', 100);

    $applied = app(RedeemStoreCredit::class)->handle('buyer@example.com', 100_000);

    expect($applied)->toBe(100)
        ->and(StoreCreditAccount::query()->find('buyer@example.com')->balance_cents)->toBe(0);
});

it('leaves the order at the full price when no credit is requested, even with a balance available', function () {
    storeCredit('buyer@example.com', 100_000);
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'buyer@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    expect($order->store_credit_applied_cents)->toBe(0)
        ->and($order->hold_cents)->toBe($quote['hold_cents'])
        ->and(StoreCreditAccount::query()->find('buyer@example.com')->balance_cents)->toBe(100_000);
});
