<?php

declare(strict_types=1);

use App\Actions\Orders\FinalizeOrder;
use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordQcCheck;
use App\Actions\Orders\RecordWeight;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Filament\Pages\Reports\YieldReport;
use App\Support\Weight;

/*
 * Guideline ch. 6, Sprint 07: actual finished-vs-raw yield, against the modeled yield% used to size
 * holds. For a stock-tracked lot with no cut option, the raw weight consumed equals the weighed
 * finished weight (no separate cutting stage is modeled), so "actual" is always 100% — comparing it
 * to the product's own modeled yield_pct (e.g. a Half/Quarter share) still flags a mismatch worth a
 * second look.
 */
it('reports actual yield against the product\'s modeled yield%', function () {
    $gateway = fakePayments();
    $butcher = staff(Role::Butcher);
    $frontDesk = staff(Role::FrontDesk);
    $product = product(priceCents: 500, estLb: '2.000');
    $product->yield_pct = '50.00';
    $product->save();
    lot($product, onHandLb: '20.000');

    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    $intent = $gateway->authorize($order->stripe_payment_intent_id);
    app(MarkOrderAuthorized::class)->handle($order, $intent);
    app(RecordWeight::class)->handle($order->fresh()->items->first(), Weight::pounds('2.000'), WeightSource::Manual, $butcher);
    app(RecordQcCheck::class)->handle($order->fresh(), true, ['weight_matches' => true], $butcher, '38.0');
    app(FinalizeOrder::class)->handle($order->fresh(), $frontDesk);

    $page = new YieldReport;
    $page->mount();

    $row = $page->rows->first();
    expect($row['product'])->toBe($product->name)
        ->and($row['count'])->toBe(1)
        ->and($row['actual_pct'])->toBe('100.00')
        ->and($row['modeled_pct'])->toBe('50.00');
});
