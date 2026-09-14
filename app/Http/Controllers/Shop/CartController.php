<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\QuoteCart;
use App\Http\Controllers\Controller;
use App\Models\CutOption;
use App\Models\OffalOption;
use App\Models\PackingOption;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CartController extends Controller
{
    public function show(Cart $cart, QuoteCart $quote): View
    {
        try {
            $quoted = $quote->handle($cart->lines());
        } catch (ValidationException $e) {
            // A product or option went inactive: drop the whole cart line by line until it quotes clean.
            $quoted = $this->dropUnavailableLines($cart, $quote);
            session()->now('status', 'An item in your cart is no longer available and was removed.');
        }

        return view('shop.cart', ['quote' => $quoted]);
    }

    public function add(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.QuoteCart::MAX_QUANTITY],
            'cut_option_id' => ['nullable', 'integer', 'exists:cut_options,id'],
            'offal_option_id' => ['nullable', 'integer', 'exists:offal_options,id'],
            'packing_option_id' => ['nullable', 'integer', 'exists:packing_options,id'],
        ]);

        $product = Product::query()->active()->whereKey($data['product_id'])->firstOrFail();

        $cutOptionId = isset($data['cut_option_id']) ? (int) $data['cut_option_id'] : null;
        $offalOptionId = isset($data['offal_option_id']) ? (int) $data['offal_option_id'] : null;
        if ($cutOptionId !== null || $offalOptionId !== null) {
            abort_unless($product->supportsCustomCuts(), 422, "{$product->name} doesn't take cut or offal options.");
        }
        if ($cutOptionId !== null) {
            abort_unless(CutOption::query()->active()->whereKey($cutOptionId)->where('category_id', $product->category_id)->exists(), 422, 'Invalid cut option.');
        }
        if ($offalOptionId !== null) {
            abort_unless(OffalOption::query()->active()->whereKey($offalOptionId)->where('category_id', $product->category_id)->exists(), 422, 'Invalid offal option.');
        }
        $packingOptionId = isset($data['packing_option_id']) ? (int) $data['packing_option_id'] : null;
        if ($packingOptionId !== null) {
            abort_unless(PackingOption::query()->active()->whereKey($packingOptionId)->exists(), 422, 'Invalid packing option.');
        }

        $cart->add((int) $data['product_id'], (int) $data['quantity'], $cutOptionId, $offalOptionId, $packingOptionId);

        return redirect()->route('cart.show')->with('status', 'Added to your cart.');
    }

    public function update(Request $request, string $line, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.QuoteCart::MAX_QUANTITY]]);
        $cart->updateQuantity($line, (int) $data['quantity']);

        return redirect()->route('cart.show');
    }

    public function remove(string $line, Cart $cart): RedirectResponse
    {
        $cart->remove($line);

        return redirect()->route('cart.show');
    }

    /**
     * A product or one of its options went inactive since the line was added. Drop lines one at a
     * time — checking each in isolation — until only the ones that still quote cleanly remain.
     *
     * @return array{lines: list<array<string, mixed>>, estimated_cents: int, hold_cents: int, lead_time_days: int}
     */
    private function dropUnavailableLines(Cart $cart, QuoteCart $quote): array
    {
        foreach ($cart->lines() as $key => $value) {
            try {
                $quote->handle([$key => $value]);
            } catch (ValidationException) {
                $cart->remove((string) $key);
            }
        }

        return $quote->handle($cart->lines());
    }
}
