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
    $this->lot = lot($this->product);
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
$inventoryRoutes = [
    'animals.list' => fn () => '/admin/animals',
    'animals.create' => fn () => '/admin/animals/create',
    'lots.list' => fn () => '/admin/lots',
    'lots.create' => fn () => '/admin/lots/create',
    'lots.edit' => fn (object $ctx) => "/admin/lots/{$ctx->lot->lot_number}/edit",
    'recall-report' => fn () => '/admin/recall-report',
];
$productionDayRoutes = [
    'production-days.list' => fn () => '/admin/production-days',
    'production-days.create' => fn () => '/admin/production-days/create',
];
$productionBoardRoutes = [
    'production-board' => fn () => '/admin/production-board',
];
$deliveryConfigRoutes = [
    'delivery-zones.list' => fn () => '/admin/delivery-zones',
    'delivery-zones.create' => fn () => '/admin/delivery-zones/create',
    'delivery-slots.list' => fn () => '/admin/delivery-slots',
    'delivery-slots.create' => fn () => '/admin/delivery-slots/create',
];
$driverRouteRoutes = [
    'driver-route' => fn () => '/admin/driver-route',
];
$paymentsConfigRoutes = [
    'coupons.list' => fn () => '/admin/coupons',
    'coupons.create' => fn () => '/admin/coupons/create',
    'store-credit.list' => fn () => '/admin/store-credit-accounts',
    'notification-templates.list' => fn () => '/admin/notification-templates',
];
$reportRoutes = [
    'reports.yield' => fn () => '/admin/yield-report',
    'reports.margin' => fn () => '/admin/margin-report',
    'reports.wastage' => fn () => '/admin/wastage-report',
    'reports.sales' => fn () => '/admin/sales-report',
    'reports.stock-aging' => fn () => '/admin/stock-aging-report',
    'audit-log.list' => fn () => '/admin/audit-logs',
];
$complianceOnlyRoutes = [
    'privacy-requests.list' => fn () => '/admin/privacy-requests',
    'inspection-pack' => fn () => '/admin/inspection-pack',
];
$phoneOrderRoutes = [
    'phone-order' => fn () => '/admin/phone-order',
];
$shippingConfigRoutes = [
    'packing-rules.list' => fn () => '/admin/packing-rules',
    'packing-rules.create' => fn () => '/admin/packing-rules/create',
    'ship-blackout-dates.list' => fn () => '/admin/ship-blackout-dates',
    'ship-blackout-dates.create' => fn () => '/admin/ship-blackout-dates/create',
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

// Owner, Manager and Butcher have inventory.manage (App\Enums\Role::permissions())
$canManageInventory = [Role::Owner, Role::Manager, Role::Butcher];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageInventory, true) ? 200 : 403;
    foreach ($inventoryRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on inventory {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner and Manager have catalog.manage — production day budgets are a manager-level setting
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageCatalog, true) ? 200 : 403;
    foreach ($productionDayRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Owner, Manager and Butcher have weights.record — the board is a butcher-station tool
$canRecordWeights = [Role::Owner, Role::Manager, Role::Butcher];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canRecordWeights, true) ? 200 : 403;
    foreach ($productionBoardRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner and Manager have catalog.manage — the service area/fees/slots are a manager-level setting
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageCatalog, true) ? 200 : 403;
    foreach ($deliveryConfigRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner, Manager and Driver have deliveries.manage (App\Enums\Role::permissions())
$canManageDeliveries = [Role::Owner, Role::Manager, Role::Driver];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageDeliveries, true) ? 200 : 403;
    foreach ($driverRouteRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner and Manager have catalog.manage — coupons, store credit and notification copy are
// manager-level settings (guideline ch. 6, Sprint 06)
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageCatalog, true) ? 200 : 403;
    foreach ($paymentsConfigRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Owner, Manager and Accountant have reports.view and audit.view (App\Enums\Role::permissions())
$canViewReports = [Role::Owner, Role::Manager, Role::Accountant];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canViewReports, true) ? 200 : 403;
    foreach ($reportRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner and Manager have compliance.manage — privacy requests and the inspection pack are
// manager-level, guideline ch. 6, Sprint 07
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageCatalog, true) ? 200 : 403;
    foreach ($complianceOnlyRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Owner, Manager and Front desk have orders.manage — phone/counter order entry, guideline S07
$canManageOrders = [Role::Owner, Role::Manager, Role::FrontDesk];
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageOrders, true) ? 200 : 403;
    foreach ($phoneOrderRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
            $this->actingAs(staff($role))->get($url($this))->assertStatus($expected);
        });
    }
}

// Only Owner and Manager have catalog.manage — packing rules and ship blackout dates, guideline S08
foreach (Role::cases() as $role) {
    $expected = in_array($role, $canManageCatalog, true) ? 200 : 403;
    foreach ($shippingConfigRoutes as $name => $url) {
        it("returns {$expected} for {$role->label()} on {$name}", function () use ($role, $url, $expected) {
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

it('lets a butcher record a weight and QC it, but only front desk finalizes — and only after QC passes', function () {
    $butcher = staff(Role::Butcher);
    $frontDesk = staff(Role::FrontDesk);

    expect($butcher->can('recordWeight', $this->order))->toBeFalse()   // order isn't authorized yet
        ->and($butcher->can('finalize', $this->order))->toBeFalse()
        ->and($frontDesk->can('finalize', $this->order))->toBeFalse();  // ditto — not authorized yet

    $this->order->status = OrderStatus::Authorized;
    $this->order->save();

    expect($butcher->can('recordWeight', $this->order))->toBeTrue()
        ->and($frontDesk->can('recordWeight', $this->order))->toBeFalse()
        ->and($butcher->can('recordQcCheck', $this->order))->toBeFalse()   // not all items weighed yet
        ->and($frontDesk->can('finalize', $this->order))->toBeFalse();     // owner decision S04: no finalize before QC

    $item = $this->order->items->first();
    $item->actual_weight_lb = '1.000';
    $item->save();

    expect($butcher->can('recordQcCheck', $this->order->fresh()))->toBeTrue()
        ->and($frontDesk->can('recordQcCheck', $this->order->fresh()))->toBeFalse();

    $this->order->status = OrderStatus::QcPassed;
    $this->order->save();

    expect($butcher->can('finalize', $this->order))->toBeFalse()
        ->and($frontDesk->can('finalize', $this->order))->toBeTrue();
});
