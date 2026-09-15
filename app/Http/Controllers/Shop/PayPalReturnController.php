<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\MarkOrderAuthorized;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\AuthorizesOrderAccess;
use App\Models\Order;
use App\Payments\PayPalGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * PayPal has no client-side confirmation like Stripe's Payment Element — the customer approves on
 * paypal.com and is redirected back here, which is what actually completes the authorization
 * (guideline ch. 6, Sprint 06). Whichever finishes first between this and a future webhook wins;
 * MarkOrderAuthorized is a no-op the second time.
 */
final class PayPalReturnController extends Controller
{
    use AuthorizesOrderAccess;

    public function __invoke(Request $request, Order $order, PayPalGateway $paypal, MarkOrderAuthorized $markAuthorized): RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        if ($order->status !== OrderStatus::PendingPayment || $order->payment_method !== 'paypal') {
            return redirect()->route('orders.show', $order);
        }

        $intent = $paypal->confirmAuthorization((string) $order->payment_reference, $order->idempotencyKey('paypal-confirm'));
        $markAuthorized->handle($order, $intent);

        return redirect()->route('orders.show', $order);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        return redirect()->route('checkout.pay', $order)->with('paypal_cancelled', true);
    }
}
