<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Models\CutOption;
use App\Models\OffalOption;
use App\Models\PackingOption;
use App\Models\Product;
use App\Support\CartLine;
use App\Support\CatchWeightPricing;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/**
 * The single source of cart pricing, used by the cart page, checkout page and PlaceOrder, so the hold
 * the customer sees is exactly the hold that gets authorized. A line's price is the catch-weight base
 * price plus any cut/offal/packing surcharge (guideline S02: "choosing cut + offal + packing correctly
 * increases the price").
 */
final class QuoteCart extends Action
{
    public const MAX_QUANTITY = 50;

    /**
     * @param  array<int|string, int|array<string, int|null>>  $rawLines  raw cart/session shape
     * @return array{lines: list<array{line_key: string, product: Product, quantity: int, weight: Weight, cut_option: ?CutOption, offal_option: ?OffalOption, packing_option: ?PackingOption, lead_time_days: int, estimated_cents: int, hold_cents: int}>, estimated_cents: int, hold_cents: int, lead_time_days: int}
     */
    public function handle(array $rawLines, bool $lockProducts = false): array
    {
        $lines = [];
        foreach ($rawLines as $key => $value) {
            $lines[(string) $key] = CartLine::fromRaw($key, $value);
        }

        $productIds = array_unique(array_map(fn (CartLine $l) => $l->productId, $lines));
        $productQuery = Product::query()->active()->whereKey($productIds);
        if ($lockProducts) {
            $productQuery->lockForUpdate();
        }
        $products = $productQuery->get()->keyBy('id');

        $cutOptionIds = array_filter(array_unique(array_map(fn (CartLine $l) => $l->cutOptionId, $lines)));
        $offalOptionIds = array_filter(array_unique(array_map(fn (CartLine $l) => $l->offalOptionId, $lines)));
        $packingOptionIds = array_filter(array_unique(array_map(fn (CartLine $l) => $l->packingOptionId, $lines)));
        $cutOptions = CutOption::query()->active()->whereKey($cutOptionIds)->get()->keyBy('id');
        $offalOptions = OffalOption::query()->active()->whereKey($offalOptionIds)->get()->keyBy('id');
        $packingOptions = PackingOption::query()->active()->whereKey($packingOptionIds)->get()->keyBy('id');

        $quoted = [];
        $estimated = 0;
        $hold = 0;
        $maxLeadTimeDays = 0;

        foreach ($lines as $lineKey => $line) {
            $product = $products->get($line->productId);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages(['cart' => 'An item in your cart is no longer available.']);
            }
            if ($line->quantity < 1 || $line->quantity > self::MAX_QUANTITY) {
                throw ValidationException::withMessages(['cart' => "Invalid quantity for {$product->name}."]);
            }

            $cutOption = $line->cutOptionId !== null ? $cutOptions->get($line->cutOptionId) : null;
            $offalOption = $line->offalOptionId !== null ? $offalOptions->get($line->offalOptionId) : null;
            $packingOption = $line->packingOptionId !== null ? $packingOptions->get($line->packingOptionId) : null;

            if ($line->cutOptionId !== null && (! $cutOption instanceof CutOption || $cutOption->category_id !== $product->category_id)) {
                throw ValidationException::withMessages(['cart' => "That cut option isn't available for {$product->name}."]);
            }
            if ($line->offalOptionId !== null && (! $offalOption instanceof OffalOption || $offalOption->category_id !== $product->category_id)) {
                throw ValidationException::withMessages(['cart' => "That offal option isn't available for {$product->name}."]);
            }
            if ($line->packingOptionId !== null && ! $packingOption instanceof PackingOption) {
                throw ValidationException::withMessages(['cart' => "That packing option isn't available for {$product->name}."]);
            }
            if (($cutOption !== null || $offalOption !== null) && ! $product->supportsCustomCuts()) {
                throw ValidationException::withMessages(['cart' => "{$product->name} doesn't take cut or offal options."]);
            }

            $weight = $product->estimated_weight_lb->times($line->quantity);
            $baseCents = CatchWeightPricing::lineCents($product->price_per_lb_cents, $weight);
            $optionCentsPerPiece = ($cutOption->extra_price_cents ?? 0) + ($offalOption->extra_price_cents ?? 0) + ($packingOption->surcharge_cents ?? 0);
            $lineCents = $baseCents + ($optionCentsPerPiece * $line->quantity);
            $lineHold = CatchWeightPricing::applyPercent($lineCents, $product->holdTolerancePct());

            $leadTimeDays = ($cutOption->extra_lead_time_days ?? 0) + ($packingOption->extra_lead_time_days ?? 0);
            if ($leadTimeDays > (int) config('catchweight.max_lead_time_days')) {
                throw ValidationException::withMessages([
                    'cart' => "The cut and packing options chosen for {$product->name} need more processing time than we can hold your card for. Please choose fewer special options or contact us for a custom order.",
                ]);
            }
            $maxLeadTimeDays = max($maxLeadTimeDays, $leadTimeDays);

            $quoted[] = [
                'line_key' => $lineKey,
                'product' => $product,
                'quantity' => $line->quantity,
                'weight' => $weight,
                'cut_option' => $cutOption,
                'offal_option' => $offalOption,
                'packing_option' => $packingOption,
                'lead_time_days' => $leadTimeDays,
                'estimated_cents' => $lineCents,
                'hold_cents' => $lineHold,
            ];
            $estimated += $lineCents;
            $hold += $lineHold;
        }

        return ['lines' => $quoted, 'estimated_cents' => $estimated, 'hold_cents' => $hold, 'lead_time_days' => $maxLeadTimeDays];
    }
}
