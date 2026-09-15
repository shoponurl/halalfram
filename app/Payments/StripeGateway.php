<?php

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Data\ChargeResult;
use App\Payments\Data\HostedPaymentLink;
use App\Payments\Data\IntentState;
use App\Payments\Data\RefundResult;
use App\Payments\Data\WebhookEvent;
use App\Payments\Exceptions\InvalidWebhookSignature;
use App\Payments\Exceptions\PaymentsNotConfigured;
use Carbon\CarbonImmutable;
use Stripe\Exception\CardException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe implementation. Card data only ever touches Stripe Elements / Checkout (PCI SAQ-A).
 */
final class StripeGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly ?string $secretKey,
        private readonly ?string $publishableKey,
        private readonly ?string $webhookSecret,
        private readonly string $currency,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->secretKey) && filled($this->publishableKey) && filled($this->webhookSecret);
    }

    public function publishableKey(): ?string
    {
        return $this->publishableKey;
    }

    public function createCustomer(string $name, string $email, string $idempotencyKey): string
    {
        return $this->client()->customers->create(['name' => $name, 'email' => $email], ['idempotency_key' => $idempotencyKey])->id;
    }

    public function createHold(int $amountCents, string $customerId, string $description, array $metadata, string $idempotencyKey): IntentState
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => $this->currency,
            'customer' => $customerId,
            'description' => $description,
            'metadata' => $metadata,
            'capture_method' => 'manual',
            'setup_future_usage' => 'off_session',   // lets us charge an approved overage without the customer present
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ], ['idempotency_key' => $idempotencyKey]);

        return $this->toState($intent);
    }

    public function retrieveIntent(string $intentId): IntentState
    {
        return $this->toState($this->client()->paymentIntents->retrieve($intentId, ['expand' => ['latest_charge']]));
    }

    public function confirmAuthorization(string $intentId, string $idempotencyKey): IntentState
    {
        // The customer already confirmed client-side via the Payment Element; nothing more to do.
        return $this->retrieveIntent($intentId);
    }

    public function capture(string $intentId, int $amountCents, string $idempotencyKey): IntentState
    {
        $intent = $this->client()->paymentIntents->capture($intentId, ['amount_to_capture' => $amountCents], ['idempotency_key' => $idempotencyKey]);

        return $this->toState($intent);
    }

    public function cancel(string $intentId, string $idempotencyKey): IntentState
    {
        return $this->toState($this->client()->paymentIntents->cancel($intentId, [], ['idempotency_key' => $idempotencyKey]));
    }

    public function chargeOffSession(string $customerId, string $paymentMethodId, int $amountCents, string $description, array $metadata, string $idempotencyKey): ChargeResult
    {
        try {
            $intent = $this->client()->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => $this->currency,
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'description' => $description,
                'metadata' => $metadata,
                'off_session' => true,
                'confirm' => true,
            ], ['idempotency_key' => $idempotencyKey]);

            return new ChargeResult($intent->status === 'succeeded', $intent->id, $intent->status === 'succeeded' ? null : "Charge status: {$intent->status}");
        } catch (CardException $e) {
            // Declines and "authentication required" land here; the caller falls back to a payment link
            return new ChargeResult(false, $e->getError()->payment_intent->id ?? null, $e->getMessage());
        }
    }

    public function createPaymentLink(string $customerId, int $amountCents, string $description, string $successUrl, array $metadata, string $idempotencyKey): HostedPaymentLink
    {
        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'customer' => $customerId,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $this->currency,
                    'unit_amount' => $amountCents,
                    'product_data' => ['name' => $description],
                ],
            ]],
            'success_url' => $successUrl,
            'metadata' => $metadata,
            'payment_intent_data' => ['metadata' => $metadata, 'description' => $description],
        ], ['idempotency_key' => $idempotencyKey]);

        return new HostedPaymentLink($session->id, (string) $session->url);
    }

    public function refund(string $intentId, int $amountCents, string $idempotencyKey): RefundResult
    {
        try {
            $refund = $this->client()->refunds->create([
                'payment_intent' => $intentId,
                'amount' => $amountCents,
            ], ['idempotency_key' => $idempotencyKey]);

            return new RefundResult($refund->status === 'succeeded' || $refund->status === 'pending', $refund->id, $refund->status === 'failed' ? $refund->failure_reason : null);
        } catch (CardException $e) {
            return new RefundResult(false, null, $e->getMessage());
        }
    }

    public function parseWebhook(string $payload, string $signatureHeader): WebhookEvent
    {
        if (blank($this->webhookSecret)) {
            throw PaymentsNotConfigured::make();
        }

        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, (string) $this->webhookSecret);
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw new InvalidWebhookSignature($e->getMessage(), previous: $e);
        }

        /** @var array<string, mixed> $object */
        $object = $event->data->object->toArray();

        return new WebhookEvent($event->id, $event->type, $object);
    }

    private function client(): StripeClient
    {
        if (blank($this->secretKey)) {
            throw PaymentsNotConfigured::make();
        }

        return $this->client ??= new StripeClient((string) $this->secretKey);
    }

    private function toState(PaymentIntent $intent): IntentState
    {
        $captureBefore = null;
        $charge = $intent->latest_charge;
        if (is_object($charge) && isset($charge->payment_method_details->card->capture_before)) {
            $captureBefore = CarbonImmutable::createFromTimestamp((int) $charge->payment_method_details->card->capture_before);
        }

        return new IntentState(
            id: $intent->id,
            status: $intent->status,
            amountCents: (int) $intent->amount,
            amountCapturableCents: (int) $intent->amount_capturable,
            amountReceivedCents: (int) $intent->amount_received,
            clientSecret: $intent->client_secret,
            paymentMethodId: is_string($intent->payment_method) ? $intent->payment_method : ($intent->payment_method->id ?? null),
            captureBefore: $captureBefore,
            metadata: $intent->metadata->toArray(),
        );
    }
}
