<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\QuoteCart;
use App\Http\Controllers\Controller;
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
            // A product went inactive: drop unavailable lines and show what's left
            foreach (array_keys($cart->lines()) as $productId) {
                if (! Product::query()->active()->whereKey($productId)->exists()) {
                    $cart->remove($productId);
                }
            }
            $quoted = $quote->handle($cart->lines());
            session()->now('status', 'An item in your cart is no longer available and was removed.');
        }

        return view('shop.cart', ['quote' => $quoted]);
    }

    public function add(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.QuoteCart::MAX_QUANTITY],
        ]);
        abort_unless(Product::query()->active()->whereKey($data['product_id'])->exists(), 404);

        $cart->add((int) $data['product_id'], (int) $data['quantity']);

        return redirect()->route('cart.show')->with('status', 'Added to your cart.');
    }

    public function update(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:'.QuoteCart::MAX_QUANTITY]]);
        $cart->set($product->id, (int) $data['quantity']);

        return redirect()->route('cart.show');
    }

    public function remove(Product $product, Cart $cart): RedirectResponse
    {
        $cart->remove($product->id);

        return redirect()->route('cart.show');
    }
}
