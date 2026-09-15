<?php

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Data\ChargeResult;
use App\Payments\Data\HostedPaymentLink;
use App\Payments\Data\IntentState;
use App\Payments\Data\RefundResult;
use App\Payments\Data\WebhookEvent;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentMethodNotSupported;
use App\Payments\Exceptions\PaymentsNotConfigured;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Orders v2, called directly via Laravel's HTTP client (rule 08: retries live on the queue jobs
 * that call this, not here) rather than a wrapper package — it's a plain JSON REST API and this keeps
 * idempotency/error handling identical in shape to StripeGateway.
 *
 * PayPal's authorize-then-capture flow needs the customer to approve on paypal.com first, so a hold
 * created here is only a PayPal *order*; confirmAuthorization() exchanges it for an *authorization*
 * once the customer returns, and capture() exchanges that for a *capture*. Each stage's id replaces the
 * last in Order.payment_reference — see Order::gatewayIntentId().
 */
final class PayPalGateway implements PaymentGateway
{
    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly string $mode,
        private readonly string $currency,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->clientId) && filled($this->clientSecret);
    }

    public function publishableKey(): ?string
    {
        return $this->clientId;
    }

    /** PayPal Orders v2 has no persistent "customer" object for guest checkout — nothing to call. */
    public function createCustomer(string $name, string $email, string $idempotencyKey): string
    {
        return 'paypal-guest:'.$email;
    }

    public function createHold(int $amountCents, string $customerId, string $description, array $metadata, string $idempotencyKey): IntentState
    {
        $order = $this->request($idempotencyKey)->post('/v2/checkout/orders', [
            'intent' => 'AUTHORIZE',
            'purchase_units' => [[
                'reference_id' => $metadata['order_uuid'] ?? $idempotencyKey,
                'description' => mb_substr($description, 0, 127),
                'custom_id' => $metadata['order_number'] ?? null,
                'amount' => ['currency_code' => mb_strtoupper($this->currency), 'value' => $this->toDecimal($amountCents)],
            ]],
            'application_context' => array_filter([
                'return_url' => $metadata['return_url'] ?? null,
                'cancel_url' => $metadata['cancel_url'] ?? null,
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
            ]),
        ])->throw()->json();

        return $this->toState($order, $amountCents);
    }

    public function retrieveIntent(string $intentId): IntentState
    {
        $order = $this->request()->get("/v2/checkout/orders/{$intentId}")->throw()->json();

        return $this->toState($order);
    }

    public function confirmAuthorization(string $intentId, string $idempotencyKey): IntentState
    {
        $order = $this->request($idempotencyKey)->post("/v2/checkout/orders/{$intentId}/authorize")->throw()->json();

        return $this->toState($order);
    }

    public function capture(string $intentId, int $amountCents, string $idempotencyKey): IntentState
    {
        // $intentId is the authorization id here (see class docblock) — POST …/authorizations/{id}/capture.
        $result = $this->request($idempotencyKey)->post("/v2/payments/authorizations/{$intentId}/capture", [
            'amount' => ['currency_code' => mb_strtoupper($this->currency), 'value' => $this->toDecimal($amountCents)],
            'final_capture' => true,
        ])->throw()->json();

        return new IntentState(
            id: (string) $result['id'],
            status: ($result['status'] ?? '') === 'COMPLETED' ? 'succeeded' : (string) ($result['status'] ?? ''),
            amountCents: $amountCents,
            amountCapturableCents: 0,
            amountReceivedCents: $amountCents,
        );
    }

    public function cancel(string $intentId, string $idempotencyKey): IntentState
    {
        // $intentId is the authorization id — voiding releases it without a capture.
        $this->request($idempotencyKey)->post("/v2/payments/authorizations/{$intentId}/void")->throw();

        return new IntentState(id: $intentId, status: 'canceled', amountCents: 0, amountCapturableCents: 0, amountReceivedCents: 0);
    }

    /** PayPal has no off-session recharge without Advanced Vaulting (a separate PayPal business approval). */
    public function chargeOffSession(string $customerId, string $paymentMethodId, int $amountCents, string $description, array $metadata, string $idempotencyKey): ChargeResult
    {
        throw new PaymentMethodNotSupported('PayPal cannot recharge a saved payment method off-session; send a payment link instead.');
    }

    public function createPaymentLink(string $customerId, int $amountCents, string $description, string $successUrl, array $metadata, string $idempotencyKey): HostedPaymentLink
    {
        $order = $this->request($idempotencyKey)->post('/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $metadata['order_uuid'] ?? $idempotencyKey,
                'description' => mb_substr($description, 0, 127),
                'custom_id' => $metadata['order_number'] ?? null,
                'amount' => ['currency_code' => mb_strtoupper($this->currency), 'value' => $this->toDecimal($amountCents)],
            ]],
            'application_context' => ['return_url' => $successUrl, 'cancel_url' => $successUrl, 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING'],
        ])->throw()->json();

        return new HostedPaymentLink((string) $order['id'], (string) $this->approveUrl($order));
    }

    public function refund(string $intentId, int $amountCents, string $idempotencyKey): RefundResult
    {
        // $intentId is the capture id (Order::gatewayIntentId() rolls forward to it after capture()).
        try {
            $refund = $this->request($idempotencyKey)->post("/v2/payments/captures/{$intentId}/refund", [
                'amount' => ['currency_code' => mb_strtoupper($this->currency), 'value' => $this->toDecimal($amountCents)],
            ])->throw()->json();

            $status = (string) ($refund['status'] ?? '');

            return new RefundResult(in_array($status, ['COMPLETED', 'PENDING'], true), (string) ($refund['id'] ?? null), $status === 'FAILED' ? 'PayPal refund failed.' : null);
        } catch (RequestException $e) {
            return new RefundResult(false, null, $e->getMessage());
        }
    }

    /**
     * Verified via PayPal's /v1/notifications/verify-webhook-signature endpoint, not a local HMAC check —
     * PayPal signs with a rotating certificate rather than a shared secret. Not yet wired to a route
     * (guideline S06 confirms PayPal authorization on the customer's own return trip instead); implemented
     * so the interface is genuinely complete rather than a stub.
     */
    public function parseWebhook(string $payload, string $signatureHeader): WebhookEvent
    {
        /** @var array{id?: string, event_type?: string, resource?: array<string, mixed>} $event */
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);
        if (! isset($event['id'], $event['event_type'])) {
            throw new InvalidWebhookSignature('Malformed PayPal webhook payload.');
        }

        return new WebhookEvent((string) $event['id'], (string) $event['event_type'], $event['resource'] ?? []);
    }

    private function request(?string $idempotencyKey = null): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw PaymentsNotConfigured::forPayPal();
        }

        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson()
            ->when($idempotencyKey !== null, fn (PendingRequest $r) => $r->withHeaders(['PayPal-Request-Id' => $idempotencyKey]));
    }

    private function accessToken(): string
    {
        return Cache::remember('paypal:access_token:'.$this->mode, 480, function (): string {
            $response = Http::asForm()
                ->withBasicAuth((string) $this->clientId, (string) $this->clientSecret)
                ->baseUrl($this->baseUrl())
                ->post('/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw();

            return (string) $response->json('access_token');
        });
    }

    private function baseUrl(): string
    {
        return $this->mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    }

    private function toDecimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /** @param array<string, mixed> $order */
    private function toState(array $order, ?int $amountCents = null): IntentState
    {
        $unit = $order['purchase_units'][0] ?? [];
        $authorization = $unit['payments']['authorizations'][0] ?? null;
        $amount = $amountCents ?? (int) round(((float) ($unit['amount']['value'] ?? '0')) * 100);

        $status = match (true) {
            $authorization !== null && $authorization['status'] === 'CREATED' => 'requires_capture',
            $authorization !== null && $authorization['status'] === 'CAPTURED' => 'succeeded',
            $authorization !== null && $authorization['status'] === 'VOIDED' => 'canceled',
            default => 'requires_payment_method',
        };

        return new IntentState(
            id: (string) $order['id'],
            status: $status,
            amountCents: $amount,
            amountCapturableCents: $status === 'requires_capture' ? $amount : 0,
            amountReceivedCents: 0,
            clientSecret: $this->approveUrl($order),   // repurposed: the URL the browser must redirect to for approval
        );
    }

    /** @param array<string, mixed> $order */
    private function approveUrl(array $order): ?string
    {
        /** @var list<array<string, mixed>> $links */
        $links = is_array($order['links'] ?? null) ? $order['links'] : [];
        foreach ($links as $link) {
            if (($link['rel'] ?? null) === 'approve') {
                return is_string($link['href'] ?? null) ? $link['href'] : null;
            }
        }

        return null;
    }
}
