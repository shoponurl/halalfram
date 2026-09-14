<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Orders\MarkOrderAuthorized;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\AuthorizesOrderAccess;
use App\Models\Order;
use App\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class OrderController extends Controller
{
    use AuthorizesOrderAccess;

    public function show(Request $request, Order $order, PaymentGateway $payments, MarkOrderAuthorized $markAuthorized): View
    {
        $this->authorizeOrderAccess($request, $order);

        // Returning from Stripe: confirm the hold now instead of waiting for the webhook (either path is safe)
        if ($order->status === OrderStatus::PendingPayment && $order->stripe_payment_intent_id !== null) {
            $markAuthorized->handle($order, $payments->retrieveIntent($order->stripe_payment_intent_id));
        }

        return view('shop.order', ['order' => $order->load('items')]);
    }
}
