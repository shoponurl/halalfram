<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Models\Product;
use App\Support\CatchWeightPricing;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/**
 * The single source of cart pricing, used by the cart page, checkout page and PlaceOrder,
 * so the hold the customer sees is exactly the hold that gets authorized.
 */
final class QuoteCart extends Action
{
    public const MAX_QUANTITY = 50;

    /**
     * @param  array<int, int>  $lines  product id => quantity
     * @return array{lines: list<array{product: Product, quantity: int, weight: Weight, estimated_cents: int, hold_cents: int}>, estimated_cents: int, hold_cents: int}
     */
    public function handle(array $lines, bool $lockProducts = false): array
    {
        $query = Product::query()->active()->whereKey(array_keys($lines));
        if ($lockProducts) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id');

        $quoted = [];
        $estimated = 0;
        $hold = 0;
        foreach ($lines as $productId => $quantity) {
            $product = $products->get($productId);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages(['cart' => 'An item in your cart is no longer available.']);
            }
            if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
                throw ValidationException::withMessages(['cart' => "Invalid quantity for {$product->name}."]);
            }

            $weight = $product->estimated_weight_lb->times($quantity);
            $lineCents = CatchWeightPricing::lineCents($product->price_per_lb_cents, $weight);
            $lineHold = CatchWeightPricing::applyPercent($lineCents, $product->holdTolerancePct());

            $quoted[] = ['product' => $product, 'quantity' => $quantity, 'weight' => $weight, 'estimated_cents' => $lineCents, 'hold_cents' => $lineHold];
            $estimated += $lineCents;
            $hold += $lineHold;
        }

        return ['lines' => $quoted, 'estimated_cents' => $estimated, 'hold_cents' => $hold];
    }
}
