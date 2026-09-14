<?php

declare(strict_types=1);

/*
 * Sprint 02: the catalog moves from the marketing homepage's demo data to real category/product pages.
 */

it('lists categories with their product counts', function () {
    $goat = category('Goat');
    product(name: 'Goat, Whole', category: $goat);
    product(name: 'Inactive goat item', category: $goat)->update(['is_active' => false]);

    $this->get('/categories')->assertOk()->assertSee('Goat')->assertSee($goat->species->label());
});

it('lists only active products in a category', function () {
    $goat = category('Goat');
    $active = product(name: 'Goat, Whole', category: $goat);
    $inactive = product(name: 'Retired cut', category: $goat);
    $inactive->update(['is_active' => false]);

    $this->get("/categories/{$goat->slug}")
        ->assertOk()
        ->assertSee($active->name)
        ->assertDontSee($inactive->name);
});

it('shows cut/offal/packing choices on a product in a cuttable category, but not on a plain one', function () {
    $goat = category('Goat');
    $cut = cutOption($goat, 'Boneless', extraPriceCents: 400);
    $cuttable = product(name: 'Goat, Whole', category: $goat);

    $plainCategory = category('Deli', supportsCustomCuts: false);
    $plain = product(name: 'Sausage', category: $plainCategory);

    $this->get("/products/{$cuttable->slug}")->assertOk()->assertSee('Boneless');
    $this->get("/products/{$plain->slug}")->assertOk()->assertDontSee('Boneless');
});
