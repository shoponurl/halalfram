<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\AuthorizesOrderAccess;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    use AuthorizesOrderAccess;

    public function create(Cart $cart, QuoteCart $quote, PaymentGateway $payments): View|RedirectResponse
    {
        if ($cart->lines() === []) {
            return redirect()->route('cart.show');
        }

        return view('shop.checkout', [
            'quote' => $quote->handle($cart->lines()),
            'paymentsReady' => $payments->isConfigured(),
            'policy' => config('catchweight'),
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $request->validated();

        $result = $placeOrder->handle(
            lines: $cart->lines(),
            customer: [
                'customer_name' => (string) $data['customer_name'],
                'customer_email' => strtolower((string) $data['customer_email']),
                'customer_phone' => (string) $data['customer_phone'],
                'notes' => $data['notes'] ?? null,
            ],
            expectedHoldCents: (int) $data['expected_hold_cents'],
            user: $request->user(),
        );

        $order = $result['order'];
        $request->session()->put("order_tokens.{$order->number}", $result['token']);
        $cart->clear();

        return redirect()->route('checkout.pay', $order);
    }

    public function pay(Request $request, Order $order, PaymentGateway $payments): View|RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return redirect()->route('orders.show', $order);
        }

        $intent = $payments->retrieveIntent((string) $order->stripe_payment_intent_id);

        return view('shop.pay', [
            'order' => $order,
            'clientSecret' => $intent->clientSecret,
            'publishableKey' => $payments->publishableKey(),
            'returnUrl' => route('orders.show', $order),
        ]);
    }
}
