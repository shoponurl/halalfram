<?php

declare(strict_types=1);

use App\Enums\CutStyle;
use App\Enums\LotStatus;
use App\Enums\Role;
use App\Enums\Species;
use App\Enums\StorageLocation;
use App\Models\Animal;
use App\Models\Category;
use App\Models\CutOption;
use App\Models\Lot;
use App\Models\OffalOption;
use App\Models\PackingOption;
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
function product(string $name = 'Whole Chicken', int $priceCents = 349, string $estLb = '3.500', ?string $tolerancePct = null, ?Category $category = null): Product
{
    $product = new Product;
    $product->slug = Str::slug($name).'-'.Str::random(6);
    $product->name = $name;
    $product->category_id = $category?->id;
    $product->price_per_lb_cents = $priceCents;
    $product->estimated_weight_lb = Weight::pounds($estLb);
    $product->tolerance_pct = $tolerancePct;
    $product->is_active = true;
    $product->save();

    return $product;
}

/** A category that offers cut/offal options unless $supportsCustomCuts is false. */
function category(string $name = 'Goat', bool $supportsCustomCuts = true): Category
{
    $category = new Category;
    $category->slug = Str::slug($name).'-'.Str::random(6);
    $category->name = $name;
    $category->species = Species::Goat;
    $category->supports_custom_cuts = $supportsCustomCuts;
    $category->save();

    return $category;
}

function cutOption(Category $category, string $name = 'Boneless', int $extraPriceCents = 0, int $extraLeadTimeDays = 0, ?string $rawYieldPct = null, ?int $estimatedMinutes = null): CutOption
{
    $option = new CutOption;
    $option->category_id = $category->id;
    $option->name = $name;
    $option->cut_style = CutStyle::Boneless;
    $option->extra_price_cents = $extraPriceCents;
    $option->extra_lead_time_days = $extraLeadTimeDays;
    $option->raw_yield_pct = $rawYieldPct;
    $option->estimated_minutes = $estimatedMinutes;
    $option->is_active = true;
    $option->save();

    return $option;
}

function offalOption(Category $category, string $name = 'Separate pack', int $extraPriceCents = 0): OffalOption
{
    $option = new OffalOption;
    $option->category_id = $category->id;
    $option->name = $name;
    $option->extra_price_cents = $extraPriceCents;
    $option->is_active = true;
    $option->save();

    return $option;
}

function packingOption(string $name = 'Vacuum pack', int $surchargeCents = 0, int $extraLeadTimeDays = 0): PackingOption
{
    $option = new PackingOption;
    $option->name = $name.' '.Str::random(4);
    $option->surcharge_cents = $surchargeCents;
    $option->extra_lead_time_days = $extraLeadTimeDays;
    $option->is_active = true;
    $option->save();

    return $option;
}

function animal(string $tagId = 'F145', ?string $liveLb = '150.000', ?string $dressedLb = '90.000'): Animal
{
    $animal = new Animal;
    $animal->tag_id = $tagId.'-'.Str::random(4);
    $animal->species = Species::Goat;
    $animal->slaughter_date = now()->subDay();
    $animal->live_weight_lb = $liveLb === null ? null : Weight::pounds($liveLb);
    $animal->dressed_weight_lb = $dressedLb === null ? null : Weight::pounds($dressedLb);
    $animal->recorded_by = User::factory()->create()->id;
    $animal->save();

    return $animal;
}

/** An active lot for a product, e.g. lot($product, onHandLb: '20.000', useByDaysFromNow: 5). */
function lot(Product $product, string $onHandLb = '20.000', int $useByDaysFromNow = 5, ?Animal $animal = null, StorageLocation $storageLocation = StorageLocation::Chiller): Lot
{
    $lotModel = new Lot;
    $lotModel->product_id = $product->id;
    $lotModel->animal_id = $animal?->id;
    $lotModel->storage_location = $storageLocation;
    $lotModel->pack_date = now();
    $lotModel->use_by_date = now()->addDays($useByDaysFromNow);
    $lotModel->status = LotStatus::Active;
    $lotModel->on_hand_weight_lb = Weight::pounds($onHandLb);
    $lotModel->reserved_weight_lb = Weight::zero();
    $lotModel->received_by = User::factory()->create()->id;
    $lotModel->save();
    $lotModel->lot_number = 'LOT-'.str_pad((string) $lotModel->id, 6, '0', STR_PAD_LEFT);
    $lotModel->save();

    return $lotModel;
}
