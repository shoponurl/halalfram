<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;

/*
 * Security test matrix (guideline ch. 5, layer 4) for the Orders and Products Filament resources:
 * every role × every route. Matches the pattern in StaffManagementAuthorizationTest.
 */

beforeEach(function () {
    fakePayments();
    $this->product = product();
    ['order' => $this->order] = app(PlaceOrder::class)->handle(
        [$this->product->id => 1],
        ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        (int) round($this->product->estimatedPieceCents() * 1.10),
    );
});

$orderRoutes = [
    'list' => fn () => '/admin/orders',
    'view' => fn (object $ctx) => "/admin/orders/{$ctx->order->number}",
];
$productRoutes = [
    'list' => fn () => '/admin/products',
    'create' => fn () => '/admin/products/create',
    'edit' => fn (object $ctx) => "/admin/products/{$ctx->product->slug}/edit",
];
$catalogOptionRoutes = [
    'categories.list' => fn () => '/admin/categories',
    'categories.create' => fn () => '/admin/categories/create',
    'cut-options.list' => fn () => '/admin/cut-options',
    'cut-options.create' => fn () => '/admin/cut-options/create',
    'offal-options.list' => fn () => '/admin/offal-options',
    'offal-options.create' => fn () => '/admin/offal-options/create',
    'packing-options.list' => fn () => '/admin/packing-options',
    'packing-options.create' => fn () => '/admin/packing-options/create',
];

// Only Owner, Manager, Front desk, Butcher and Accountant have orders.view (App\Enums\Role::permissions())
$canViewOrders = [Role::Owner, Role::Manager, Role::FrontDesk, Role::Butcher, Role::Accountant];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canViewOrders, true) ? 200 : 403;
    foreach ($orderRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on order {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner and Manager have catalog.manage
$canManageCatalog = [Role::Owner, Role::Manager];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageCatalog, true) ? 200 : 403;
    foreach ($productRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on product {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
    foreach ($catalogOptionRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on catalog {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

it('lets a butcher print the cutting sheet once the order is authorized, but not before or for other staff', function () {
    $butcher = staff(Role::Butcher);
    $accountant = staff(Role::Accountant);

    expect($butcher->can('printCuttingSheet', $this->order))->toBeFalse()   // still pending payment
        ->and($accountant->can('printCuttingSheet', $this->order))->toBeFalse();

    $this->order->status = OrderStatus::Authorized;
    $this->order->save();

    expect($butcher->can('printCuttingSheet', $this->order))->toBeTrue()
        ->and($accountant->can('printCuttingSheet', $this->order))->toBeFalse();
});

it('lets a butcher record a weight but not finalize the order', function () {
    $butcher = staff(Role::Butcher);
    $frontDesk = staff(Role::FrontDesk);

    expect($butcher->can('recordWeight', $this->order))->toBeFalse()   // order isn't authorized yet
        ->and($butcher->can('finalize', $this->order))->toBeFalse()
        ->and($frontDesk->can('finalize', $this->order))->toBeFalse();  // ditto — not authorized yet

    $this->order->status = OrderStatus::Authorized;
    $this->order->save();

    expect($butcher->can('recordWeight', $this->order))->toBeTrue()
        ->and($butcher->can('finalize', $this->order))->toBeFalse()
        ->and($frontDesk->can('recordWeight', $this->order))->toBeFalse()
        ->and($frontDesk->can('finalize', $this->order))->toBeTrue();
});
