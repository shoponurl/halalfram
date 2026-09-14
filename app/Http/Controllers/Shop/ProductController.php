<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\CatchWeightPricing;
use Illuminate\View\View;

final class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $estimate = $product->estimatedPieceCents();

        return view('shop.product', [
            'product' => $product,
            'estimateCents' => $estimate,
            'holdCents' => CatchWeightPricing::applyPercent($estimate, $product->holdTolerancePct()),
        ]);
    }
}
