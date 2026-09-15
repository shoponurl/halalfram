<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Pages\PhoneOrder;
use App\Models\Order;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * Guideline ch. 6, Sprint 07: phone/counter order entry with customer lookup — reuses PlaceOrder,
 * pickup only for v1.
 */

beforeEach(function () {
    fakePayments();
    Filament::setCurrentPanel('admin');
});

it('places a cash phone order for a walk-in customer', function () {
    $frontDesk = staff(Role::FrontDesk);
    $product = product(priceCents: 500, estLb: '2.000');
    test()->actingAs($frontDesk);

    Livewire::test(PhoneOrder::class)
        ->set('customerName', 'Walk-in Customer')
        ->set('customerEmail', 'walkin@example.com')
        ->set('customerPhone', '2675550199')
        ->set('paymentMethod', 'cash')
        ->set('lines.0.product_id', (string) $product->id)
        ->set('lines.0.quantity', '1')
        ->call('submit');

    $order = Order::query()->where('customer_email', 'walkin@example.com')->firstOrFail();
    expect($order->payment_method)->toBe('cash')
        ->and($order->fulfilment)->toBe('pickup')
        ->and($order->items)->toHaveCount(1);
});

it('prefills the customer from their most recent order by phone', function () {
    $frontDesk = staff(Role::FrontDesk);
    $product = product(priceCents: 500, estLb: '2.000');
    test()->actingAs($frontDesk);

    Livewire::test(PhoneOrder::class)
        ->set('customerName', 'Returning Customer')
        ->set('customerEmail', 'returning@example.com')
        ->set('customerPhone', '2675550188')
        ->set('paymentMethod', 'cash')
        ->set('lines.0.product_id', (string) $product->id)
        ->set('lines.0.quantity', '1')
        ->call('submit');

    Livewire::test(PhoneOrder::class)
        ->set('lookupPhone', '2675550188')
        ->call('lookupCustomer')
        ->assertSet('customerName', 'Returning Customer')
        ->assertSet('customerEmail', 'returning@example.com');
});

it('is only reachable by roles with orders.manage', function () {
    expect(staff(Role::FrontDesk)->can('orders.manage'))->toBeTrue()
        ->and(staff(Role::Driver)->can('orders.manage'))->toBeFalse();
});
