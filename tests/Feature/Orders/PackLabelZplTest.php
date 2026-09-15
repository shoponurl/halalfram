<?php

declare(strict_types=1);

use App\Actions\Orders\MarkOrderAuthorized;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Orders\RecordWeight;
use App\Enums\Role;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Models\Product;
use App\Support\PackLabelZpl;
use App\Support\Weight;

/*
 * Owner decision (guideline ch. 7, S04): GS1-128, carrying the lot's use-by date and lot number so it
 * matches Sprint 03's traceability. Content only — verify on a real printer/scanner before production.
 */

beforeEach(function () {
    $this->gateway = fakePayments();
});

/** Places and authorizes a single-item order for the given product. */
function authorizedOrderFor(object $ctx, Product $product): Order
{
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );
    app(MarkOrderAuthorized::class)->handle($order, $ctx->gateway->authorize($order->stripe_payment_intent_id));

    return $order->fresh();
}

it('includes the weight, lot number, use-by date and a GS1-128 barcode field', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    $lot = lot($product, onHandLb: '20.000', useByDaysFromNow: 5);
    $order = authorizedOrderFor($this, $product);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('1.900'), WeightSource::Manual, staff(Role::Butcher));

    $zpl = app(PackLabelZpl::class)->render($order->items->first()->fresh());

    expect($zpl)->toContain('^XA')->toContain('^XZ')
        ->toContain('1.9 lb')
        ->toContain($lot->lot_number)
        ->toContain($lot->use_by_date->format('ymd'))   // AI (17), fixed 6 digits
        ->toContain('(17)')->toContain('(10)');
});

it('refuses to print a label for an item with no lot', function () {
    $product = product(priceCents: 500, estLb: '2.000');   // no lot — untracked
    $order = authorizedOrderFor($this, $product);
    app(RecordWeight::class)->handle($order->items->first(), Weight::pounds('1.900'), WeightSource::Manual, staff(Role::Butcher));

    expect(fn () => app(PackLabelZpl::class)->render($order->items->first()->fresh()))->toThrow(InvalidArgumentException::class);
});

it('refuses to print a label before the item is weighed', function () {
    $product = product(priceCents: 500, estLb: '2.000');
    lot($product, onHandLb: '20.000');
    $order = authorizedOrderFor($this, $product);

    expect(fn () => app(PackLabelZpl::class)->render($order->items->first()))->toThrow(InvalidArgumentException::class);
});
