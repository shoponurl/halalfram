<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Delivery\ReserveDeliverySlot;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\AuthorizesOrderAccess;
use App\Http\Requests\CheckoutRequest;
use App\Models\DeliverySlot;
use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    use AuthorizesOrderAccess;

    public function create(Request $request, Cart $cart, QuoteCart $quote, PaymentGateway $payments, ReserveDeliverySlot $reserveDeliverySlot): View|RedirectResponse
    {
        if ($cart->lines() === []) {
            return redirect()->route('cart.show');
        }

        $method = $request->query('fulfilment') === 'delivery' ? 'delivery' : 'pickup';
        $zip = trim((string) $request->query('zip', ''));

        $deliveryZone = null;
        $zipError = null;
        $slots = collect();
        if ($method === 'delivery' && $zip !== '') {
            try {
                $deliveryZone = $reserveDeliverySlot->zoneForZip($zip);
                $slots = DeliverySlot::query()->upcoming()->get()
                    ->filter(fn (DeliverySlot $slot) => Order::query()->where('delivery_slot_id', $slot->id)->whereNotIn('status', OrderStatus::abandoned())->count() < $slot->capacity)
                    ->values();
            } catch (ValidationException $e) {
                $zipError = collect($e->errors())->flatten()->first();
            }
        }

        return view('shop.checkout', [
            'quote' => $quote->handle($cart->lines()),
            'paymentsReady' => $payments->isConfigured(),
            'policy' => config('catchweight'),
            'fulfilmentMethod' => $method,
            'zip' => $zip,
            'deliveryZone' => $deliveryZone,
            'zipError' => $zipError,
            'slots' => $slots,
            'deliveryFeeCents' => $deliveryZone->flat_fee_cents ?? 0,
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $request->validated();
        $isDelivery = $data['fulfilment_method'] === 'delivery';

        $result = $placeOrder->handle(
            lines: $cart->lines(),
            customer: [
                'customer_name' => (string) $data['customer_name'],
                'customer_email' => strtolower((string) $data['customer_email']),
                'customer_phone' => (string) $data['customer_phone'],
                'notes' => $data['notes'] ?? null,
            ],
            expectedHoldCents: (int) $data['expected_hold_cents'],
            fulfilment: $isDelivery ? [
                'method' => 'delivery',
                'address_line1' => (string) $data['delivery_address_line1'],
                'address_line2' => $data['delivery_address_line2'] ?? null,
                'city' => (string) $data['delivery_city'],
                'state' => strtoupper((string) $data['delivery_state']),
                'zip' => (string) $data['delivery_zip'],
                'delivery_slot_id' => (int) $data['delivery_slot_id'],
            ] : ['method' => 'pickup'],
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
