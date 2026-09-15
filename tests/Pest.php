<?php

declare(strict_types=1);

use App\Enums\CutStyle;
use App\Enums\LotStatus;
use App\Enums\NotificationEvent;
use App\Enums\PackageTemperature;
use App\Enums\Role;
use App\Enums\Species;
use App\Enums\StorageLocation;
use App\Models\Animal;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CutOption;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Lot;
use App\Models\NotificationTemplate;
use App\Models\OffalOption;
use App\Models\PackingOption;
use App\Models\PackingRule;
use App\Models\Product;
use App\Models\ServiceZip;
use App\Models\StoreCreditAccount;
use App\Models\User;
use App\Payments\FakePaymentGateway;
use App\Payments\PaymentGateway;
use App\Payments\PayPalGateway;
use App\Shipping\FakeShippingGateway;
use App\Shipping\ShippingGateway;
use App\Sms\FakeSmsGateway;
use App\Sms\SmsGateway;
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

/**
 * Swaps the real Stripe *and* PayPal clients for one in-memory fake and returns it so a test can
 * inspect/drive it — both gateways share the same interface (App\Payments\PaymentGateway), so one
 * fake instance can stand in for either, or both at once, in a test.
 */
function fakePayments(): FakePaymentGateway
{
    $fake = new FakePaymentGateway;
    app()->instance(PaymentGateway::class, $fake);
    app()->instance(PayPalGateway::class, $fake);

    return $fake;
}

/** Swaps the real Twilio client for the in-memory fake and returns it. */
function fakeSms(): FakeSmsGateway
{
    $fake = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $fake);

    return $fake;
}

/** Swaps the real EasyPost client for the in-memory fake and returns it. */
function fakeShipping(): FakeShippingGateway
{
    $fake = new FakeShippingGateway;
    app()->instance(ShippingGateway::class, $fake);

    return $fake;
}

/** An active notification template for the given event/channel — most tests don't need real copy. */
function notificationTemplate(NotificationEvent $event, string $channel = 'sms', string $body = 'Order {{order_number}}: {{tracking_url}}'): NotificationTemplate
{
    return NotificationTemplate::query()->create([
        'event' => $event,
        'channel' => $channel,
        'subject' => $channel === 'email' ? 'Order {{order_number}}' : null,
        'body' => $body,
        'active' => true,
    ]);
}

/** An active coupon, e.g. coupon(type: 'percent', value: 10). */
function coupon(string $code = 'SAVE10', string $type = 'percent', int $value = 10): Coupon
{
    return Coupon::query()->create(['code' => $code, 'type' => $type, 'value' => $value, 'active' => true]);
}

/** Issues store credit directly (bypassing IssueStoreCredit's audit trail) for test setup. */
function storeCredit(string $email, int $balanceCents): StoreCreditAccount
{
    return StoreCreditAccount::query()->create(['customer_email' => $email, 'balance_cents' => $balanceCents]);
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

/** A delivery zone with one zip code already in its service area. */
function deliveryZoneForZip(string $zip = '19050', int $feeCents = 500): DeliveryZone
{
    $zone = new DeliveryZone;
    $zone->name = 'Zone '.$zip;
    $zone->flat_fee_cents = $feeCents;
    $zone->is_active = true;
    $zone->save();

    $serviceZip = new ServiceZip;
    $serviceZip->zip_code = $zip;
    $serviceZip->delivery_zone_id = $zone->id;
    $serviceZip->save();

    return $zone;
}

/** An upcoming delivery slot, e.g. deliverySlot(daysAhead: 2, capacity: 1). */
function deliverySlot(int $daysAhead = 1, int $capacity = 1): DeliverySlot
{
    $slot = new DeliverySlot;
    $slot->date = now()->addDays($daysAhead)->toDateString();
    $slot->start_time = '16:00';
    $slot->end_time = '18:00';
    $slot->capacity = $capacity;
    $slot->save();

    return $slot;
}

/** An active packing rule covering a weight band, e.g. packingRule(maxWeightLb: '10.00'). */
function packingRule(string $minWeightLb = '0.00', string $maxWeightLb = '20.00', PackageTemperature $temperature = PackageTemperature::Frozen): PackingRule
{
    $rule = new PackingRule;
    $rule->name = 'Test rule '.Str::random(4);
    $rule->temperature = $temperature;
    $rule->min_weight_lb = Weight::pounds($minWeightLb);
    $rule->max_weight_lb = Weight::pounds($maxWeightLb);
    $rule->box_length_in = '12.00';
    $rule->box_width_in = '10.00';
    $rule->box_height_in = '8.00';
    $rule->tare_weight_lb = Weight::pounds('3.000');
    $rule->dry_ice_lb = Weight::pounds('5.000');
    $rule->is_active = true;
    $rule->save();

    return $rule;
}
