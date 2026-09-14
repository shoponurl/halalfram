<?php

declare(strict_types=1);

/*
 * HTTP-boundary guarantees for Sprint 02 options, matching CheckoutSecurityTest's pattern for Sprint 01:
 * the server never trusts an option id it didn't validate against the product's own category.
 */

beforeEach(function () {
    fakePayments();
    $this->category = category('Goat');
    $this->product = product(priceCents: 899, estLb: '2.000', category: $this->category);
});

it('adds a valid cut/offal/packing combination to the cart', function () {
    $cut = cutOption($this->category, 'Boneless', extraPriceCents: 400);
    $offal = offalOption($this->category, 'Discard all offal');
    $packing = packingOption('Vacuum pack', surchargeCents: 150);

    $this->post('/cart', [
        'product_id' => $this->product->id,
        'quantity' => 1,
        'cut_option_id' => $cut->id,
        'offal_option_id' => $offal->id,
        'packing_option_id' => $packing->id,
    ])->assertRedirect(route('cart.show'));

    // 899×2 + 400 (cut) + 0 (offal) + 150 (packing) = 2348
    $this->get('/cart')->assertOk()->assertSee('Boneless')->assertSee('$23.48', false);
});

it('rejects a cut option that belongs to a different category', function () {
    $wrongCategoryCut = cutOption(category('Beef'), 'Steaks');

    $this->post('/cart', [
        'product_id' => $this->product->id,
        'quantity' => 1,
        'cut_option_id' => $wrongCategoryCut->id,
    ])->assertStatus(422);
});

it('rejects a cut option on a product whose category takes no cut options', function () {
    $plainCategory = category('Processed', supportsCustomCuts: false);
    $plainProduct = product(name: 'Sausage', category: $plainCategory);
    $cutFromSomewhereElse = cutOption($this->category, 'Boneless');

    $this->post('/cart', [
        'product_id' => $plainProduct->id,
        'quantity' => 1,
        'cut_option_id' => $cutFromSomewhereElse->id,
    ])->assertStatus(422);
});

it('rejects an option id that does not exist', function () {
    $this->post('/cart', [
        'product_id' => $this->product->id,
        'quantity' => 1,
        'cut_option_id' => 999999,
    ])->assertSessionHasErrors('cut_option_id');
});
