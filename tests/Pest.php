<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Product;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Support\Weight;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

/**
 * A staff member with a role. 2FA is already set up unless $withTwoFactor is false.
 */
function staff(Role $role, bool $withTwoFactor = true, bool $active = true): User
{
    $user = User::factory()->create();
    $user->is_active = $active;
    if ($withTwoFactor) {
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');   // any valid base32 secret
    }
    $user->save();
    $user->assignRole($role->value);

    // Reload like a real sign-in would, so strict mode sees every column
    return $user->fresh();
}

/** Swaps the real Stripe client for the in-memory fake and returns it so a test can inspect/drive it. */
function fakePayments(): FakePaymentGateway
{
    $fake = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $fake);

    return $fake;
}

/** A minimal active catch-weight product, e.g. product(priceCents: 349, estLb: '3.5'). */
function product(string $name = 'Whole Chicken', int $priceCents = 349, string $estLb = '3.500', ?string $tolerancePct = null): Product
{
    $product = new Product;
    $product->slug = Str::slug($name).'-'.Str::random(6);
    $product->name = $name;
    $product->price_per_lb_cents = $priceCents;
    $product->estimated_weight_lb = Weight::pounds($estLb);
    $product->tolerance_pct = $tolerancePct;
    $product->is_active = true;
    $product->save();

    return $product;
}
