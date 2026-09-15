<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Delivery\ReserveDeliverySlot;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\QuoteCart;
use App\Actions\Shipping\PriceShipment;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\Concerns\AuthorizesOrderAccess;
use App\Http\Requests\CheckoutRequest;
use App\Models\DeliverySlot;
use App\Models\Order;
use App\Models\StoreCreditAccount;
use App\Payments\PaymentGatewayFactory;
use App\Shipping\ShippingGateway;
use App\Support\Cart;
use App\Support\SoftLaunch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class CheckoutController extends Controller
{
    use AuthorizesOrderAccess;

    public function create(Request $request, Cart $cart, QuoteCart $quote, PaymentGatewayFactory $gateways, ReserveDeliverySlot $reserveDeliverySlot, PriceShipment $priceShipment, ShippingGateway $shippingGateway): View|RedirectResponse
    {
        if ($cart->lines() === []) {
            return redirect()->route('cart.show');
        }

        $deliveryAvailable = SoftLaunch::allowsDelivery();
        $shippingReady = $shippingGateway->isConfigured() && SoftLaunch::allowsShipping();
        $fulfilmentQuery = (string) $request->query('fulfilment');
        $method = match (true) {
            $fulfilmentQuery === 'delivery' && $deliveryAvailable => 'delivery',
            $fulfilmentQuery === 'shipping' && $shippingReady => 'shipping',
            default => 'pickup',
        };
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

        $shipState = mb_strtoupper(trim((string) $request->query('ship_state', '')));
        $shipZip = trim((string) $request->query('ship_zip', ''));
        $shipCents = 0;
        $shipError = null;
        if ($method === 'shipping' && $shipState !== '' && $shipZip !== '') {
            try {
                $shipment = $priceShipment->handle($quote->handle($cart->lines())['lines'], [
                    'name' => 'Customer', 'address1' => '', 'city' => '', 'state' => $shipState, 'zip' => $shipZip,
                ]);
                $shipCents = $shipment['rate_cents'];
            } catch (ValidationException $e) {
                $shipError = collect($e->errors())->flatten()->first();
            }
        }

        // S09 self-audit SA-01: never look a balance up by a typed-in email — only a verified one.
        $creditEmail = (string) $request->session()->get(StoreCreditVerificationController::SESSION_KEY, '');
        $availableCreditCents = $creditEmail !== '' ? (StoreCreditAccount::query()->find($creditEmail)->balance_cents ?? 0) : 0;

        return view('shop.checkout', [
            'quote' => $quote->handle($cart->lines()),
            'cardReady' => $gateways->for('card')?->isConfigured() ?? false,
            'paypalReady' => $gateways->for('paypal')?->isConfigured() ?? false,
            'shippingReady' => $shippingReady,
            'deliveryAvailable' => $deliveryAvailable,
            'codMaxCents' => (int) config('catchweight.cod_max_order_cents'),
            'policy' => config('catchweight'),
            'fulfilmentMethod' => $method,
            'zip' => $zip,
            'deliveryZone' => $deliveryZone,
            'zipError' => $zipError,
            'slots' => $slots,
            'deliveryFeeCents' => $deliveryZone->flat_fee_cents ?? 0,
            'shipState' => $shipState,
            'shipZip' => $shipZip,
            'shippingRateCents' => $shipCents,
            'shipError' => $shipError,
            'creditEmail' => $creditEmail,
            'availableCreditCents' => $availableCreditCents,
            'usdaEstablishmentNumber' => config('catchweight.usda_establishment_number'),
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $placeOrder): RedirectResponse
    {
        $data = $request->validated();
        $isDelivery = $data['fulfilment_method'] === 'delivery';
        $isShipping = $data['fulfilment_method'] === 'shipping';

        $customerEmail = strtolower(trim((string) $data['customer_email']));
        $applyStoreCredit = (bool) ($data['apply_store_credit'] ?? false);
        if ($applyStoreCredit && $request->session()->get(StoreCreditVerificationController::SESSION_KEY) !== $customerEmail) {
            throw ValidationException::withMessages([
                'apply_store_credit' => 'Store credit can only be used with the email address you confirmed through the link we sent.',
            ]);
        }

        $result = $placeOrder->handle(
            lines: $cart->lines(),
            customer: [
                'customer_name' => (string) $data['customer_name'],
                'customer_email' => $customerEmail,
                'customer_phone' => (string) $data['customer_phone'],
                'notes' => $data['notes'] ?? null,
                'marketing_sms_opt_in' => (bool) ($data['marketing_sms_opt_in'] ?? false),
                'marketing_email_opt_in' => (bool) ($data['marketing_email_opt_in'] ?? false),
            ],
            expectedHoldCents: (int) $data['expected_hold_cents'],
            fulfilment: match (true) {
                $isDelivery => [
                    'method' => 'delivery',
                    'address_line1' => (string) $data['delivery_address_line1'],
                    'address_line2' => $data['delivery_address_line2'] ?? null,
                    'city' => (string) $data['delivery_city'],
                    'state' => strtoupper((string) $data['delivery_state']),
                    'zip' => (string) $data['delivery_zip'],
                    'delivery_slot_id' => (int) $data['delivery_slot_id'],
                ],
                $isShipping => [
                    'method' => 'shipping',
                    'address_line1' => (string) $data['delivery_address_line1'],
                    'address_line2' => $data['delivery_address_line2'] ?? null,
                    'city' => (string) $data['delivery_city'],
                    'state' => strtoupper((string) $data['delivery_state']),
                    'zip' => (string) $data['delivery_zip'],
                ],
                default => ['method' => 'pickup'],
            },
            user: $request->user(),
            paymentMethod: (string) $data['payment_method'],
            couponCode: $data['coupon_code'] ?? null,
            applyStoreCredit: $applyStoreCredit,
            regulatoryConsent: true,   // CheckoutRequest already requires agree_regulatory_notice to be accepted
        );

        $order = $result['order'];
        $request->session()->put("order_tokens.{$order->number}", $result['token']);
        $cart->clear();

        return redirect()->route('checkout.pay', $order);
    }

    public function pay(Request $request, Order $order, PaymentGatewayFactory $gateways): View|RedirectResponse
    {
        $this->authorizeOrderAccess($request, $order);

        if ($order->status !== OrderStatus::PendingPayment) {
            return redirect()->route('orders.show', $order);
        }

        $payments = $gateways->for($order->payment_method);
        $intent = $payments?->retrieveIntent((string) $order->gatewayIntentId());

        return view('shop.pay', [
            'order' => $order,
            'clientSecret' => $intent?->clientSecret,
            'publishableKey' => $payments?->publishableKey(),
            'returnUrl' => route('orders.show', $order),
        ]);
    }
}
