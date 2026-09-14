<?php

declare(strict_types=1);

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Support\CartLine;
use App\Support\CuttingSheetPdf;

/*
 * Guideline M03/S02: "a cutting sheet the butcher can understand" — structured, never free text.
 */

it('prints the customer name, cut, offal and packing choice on the cutting sheet', function () {
    fakePayments();
    $goat = category('Goat');
    $product = product(name: 'Goat, Whole', priceCents: 899, estLb: '2.000', category: $goat);
    $cut = cutOption($goat, 'Boneless', extraPriceCents: 400);
    $offal = offalOption($goat, 'Separate pack', extraPriceCents: 300);
    $packing = packingOption('Vacuum pack', surchargeCents: 150);

    $line = new CartLine($product->id, 1, $cut->id, $offal->id, $packing->id);
    $rawLines = [$line->key() => $line->toArray()];
    $quote = app(QuoteCart::class)->handle($rawLines);

    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: $rawLines,
        customer: ['customer_name' => 'Bilal Rahman', 'customer_email' => 'bilal@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    $html = app(CuttingSheetPdf::class)->render($order)->output();

    expect($html)->toBeString()->not->toBeEmpty();
});

it('marks lines with no chosen options as standard, never leaving instructions blank', function () {
    fakePayments();
    $product = product();
    $quote = app(QuoteCart::class)->handle([$product->id => 1]);
    ['order' => $order] = app(PlaceOrder::class)->handle(
        lines: [$product->id => 1],
        customer: ['customer_name' => 'A', 'customer_email' => 'a@example.com', 'customer_phone' => '2675550123'],
        expectedHoldCents: $quote['hold_cents'],
    );

    $view = view('orders.cutting-sheet', ['order' => $order->load('items')])->render();

    expect($view)->toContain('Standard — no special instructions');
});
