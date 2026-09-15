<?php

declare(strict_types=1);

use App\Actions\Compliance\AnonymizeCustomerData;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Enums\PrivacyRequestStatus;
use App\Enums\Role;
use App\Models\PrivacyRequest;

/*
 * Owner decision (guideline ch. 7, S07, recorded 2026-09-15): a CCPA deletion request is fulfilled by
 * anonymizing — scrub the customer's name/contact info, keep the order/lot/animal traceability link.
 */

it('files a public CCPA request without requiring an account', function () {
    test()->post('/privacy/requests', [
        'type' => 'delete',
        'customer_email' => 'buyer@example.com',
        'customer_name' => 'Test Buyer',
    ])->assertRedirect(route('privacy-requests.create'));

    $request = PrivacyRequest::query()->where('customer_email', 'buyer@example.com')->first();
    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(PrivacyRequestStatus::Pending);
});

it('anonymizes every order for the email but keeps the lot/order link intact', function () {
    fakePayments();
    $manager = staff(Role::Manager);
    $product = product(priceCents: 500, estLb: '2.000');
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'Real Name', 'customer_email' => 'real@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $itemId = $order->items->first()->id;

    $request = PrivacyRequest::query()->create(['type' => 'delete', 'customer_email' => 'real@example.com']);
    app(AnonymizeCustomerData::class)->handle($request, $manager);

    $order = $order->fresh();
    expect($order->customer_name)->toBe(AnonymizeCustomerData::SCRUBBED_NAME)
        ->and($order->customer_email)->toBe(AnonymizeCustomerData::SCRUBBED_EMAIL)
        ->and($order->customer_phone)->toBe('')
        ->and($order->items->first()->id)->toBe($itemId)
        ->and($request->fresh()->status)->toBe(PrivacyRequestStatus::Fulfilled);
});

it('only lets Owner/Manager fulfil a privacy request', function () {
    $request = PrivacyRequest::query()->create(['type' => 'delete', 'customer_email' => 'x@example.com'])->fresh();
    $frontDesk = staff(Role::FrontDesk);
    $manager = staff(Role::Manager);

    expect($frontDesk->can('fulfill', $request))->toBeFalse()
        ->and($manager->can('fulfill', $request))->toBeTrue();
});
