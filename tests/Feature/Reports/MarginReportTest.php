<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\AnimalCostSource;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Filament\Pages\Reports\MarginReport;
use App\Models\Order;
use App\Models\Product;
use App\Support\Weight;
use Filament\Facades\Filament;

/*
 * Owner decision (guideline ch. 7, S07, recorded 2026-09-15): own-farm animals have no purchase
 * invoice, so their cost is always a manual estimate, flagged as such — never blended in as real cost.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
    $this->butcher = staff(Role::Butcher);
    $this->frontDesk = staff(Role::FrontDesk);
    Filament::setCurrentPanel('admin');
});

function settledOrderAgainstLot(object $ctx, Product $product): Order
{
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $intent = $ctx->gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $ctx->butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $ctx->butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $ctx->frontDesk);

    return $order->fresh();
}

it('computes margin for a purchased animal and flags an own-farm animal\'s margin as estimated', function () {
    $purchased = animal();
    $purchased->cost_source = AnimalCostSource::Purchased;
    $purchased->cost_cents = 40000;
    $purchased->save();
    $ownFarm = animal();
    $ownFarm->cost_source = AnimalCostSource::OwnFarm;
    $ownFarm->cost_cents = 30000;
    $ownFarm->save();
    $uncosted = animal();

    $productA = product(priceCents: 500, estLb: '2.000');
    lot($productA, onHandLb: '20.000', animal: $purchased);
    settledOrderAgainstLot($this, $productA);

    $productB = product(priceCents: 500, estLb: '2.000');
    lot($productB, onHandLb: '20.000', animal: $ownFarm);
    settledOrderAgainstLot($this, $productB);

    $page = new MarginReport;
    $page->mount();
    $rows = $page->rows;
    $uncostedList = $page->uncosted;

    $purchasedRow = $rows->first(fn (array $r) => $r['animal']->is($purchased));
    $ownFarmRow = $rows->first(fn (array $r) => $r['animal']->is($ownFarm));

    expect($purchasedRow['revenue_cents'])->toBe(1000)
        ->and($purchasedRow['margin_cents'])->toBe(1000 - 40000)
        ->and($purchasedRow['animal']->isCostEstimated())->toBeFalse()
        ->and($ownFarmRow['revenue_cents'])->toBe(1000)
        ->and($ownFarmRow['animal']->isCostEstimated())->toBeTrue()
        ->and($uncostedList->pluck('id'))->toContain($uncosted->id);
});

it('lets only Owner and Manager view the margin report', function () {
    expect(staff(Role::Owner)->can('reports.view'))->toBeTrue()
        ->and(staff(Role::Manager)->can('reports.view'))->toBeTrue()
        ->and(staff(Role::Butcher)->can('reports.view'))->toBeFalse();
});
