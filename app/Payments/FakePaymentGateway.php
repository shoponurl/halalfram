<?php

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Data\ChargeResult;
use App\Payments\Data\HostedPaymentLink;
use App\Payments\Data\IntentState;
use App\Payments\Data\RefundResult;
use App\Payments\Data\WebhookEvent;
use App\Payments\Exceptions\InvalidWebhookSignature;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * In-memory Stripe stand-in for tests and for local development without keys.
 * Honours idempotency keys the same way Stripe does: the same key returns the same result.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const WEBHOOK_SECRET = 'whsec_fake';

    /** @var array<string, IntentState> */
    public array $intents = [];

    /** @var array<string, mixed> results by idempotency key */
    private array $idempotent = [];

    /** @var list<array{operation: string, key: string, amount: int}> every money-moving call, for assertions */
    public array $calls = [];

    public bool $declineOffSession = false;

    public bool $declineHold = false;

    public bool $declineRefund = false;

    private int $sequence = 0;

    public function isConfigured(): bool
    {
        return true;
    }

    public function publishableKey(): string
    {
        return 'pk_test_fake';
    }

    public function createCustomer(string $name, string $email, string $idempotencyKey): string
    {
        return $this->once($idempotencyKey, fn () => 'cus_fake_'.(++$this->sequence));
    }

    public function createHold(int $amountCents, string $customerId, string $description, array $metadata, string $idempotencyKey): IntentState
    {
        return $this->once($idempotencyKey, function () use ($amountCents, $metadata, $idempotencyKey) {
            if ($this->declineHold) {
                throw new RuntimeException('The card was declined.');
            }
            $this->calls[] = ['operation' => 'hold', 'key' => $idempotencyKey, 'amount' => $amountCents];
            $id = 'pi_fake_'.(++$this->sequence);

            return $this->intents[$id] = new IntentState($id, 'requires_payment_method', $amountCents, 0, 0, "{$id}_secret", null, null, $metadata);
        });
    }

    /** Test helper: what the customer's browser + Stripe do when the card is authorized. */
    public function authorize(string $intentId, string $paymentMethodId = 'pm_card_visa'): IntentState
    {
        $i = $this->intent($intentId);

        return $this->intents[$intentId] = new IntentState($i->id, 'requires_capture', $i->amountCents, $i->amountCents, 0,
            $i->clientSecret, $paymentMethodId, CarbonImmutable::now()->addDays(7), $i->metadata);
    }

    public function retrieveIntent(string $intentId): IntentState
    {
        return $this->intent($intentId);
    }

    public function capture(string $intentId, int $amountCents, string $idempotencyKey): IntentState
    {
        return $this->once($idempotencyKey, function () use ($intentId, $amountCents, $idempotencyKey) {
            $i = $this->intent($intentId);
            if ($i->status !== 'requires_capture' || $amountCents > $i->amountCapturableCents) {
                throw new RuntimeException("Cannot capture {$amountCents} on {$intentId} ({$i->status}, capturable {$i->amountCapturableCents}).");
            }
            $this->calls[] = ['operation' => 'capture', 'key' => $idempotencyKey, 'amount' => $amountCents];

            return $this->intents[$intentId] = new IntentState($i->id, 'succeeded', $i->amountCents, 0, $amountCents,
                $i->clientSecret, $i->paymentMethodId, null, $i->metadata);
        });
    }

    public function cancel(string $intentId, string $idempotencyKey): IntentState
    {
        return $this->once($idempotencyKey, function () use ($intentId, $idempotencyKey) {
            $i = $this->intent($intentId);
            $this->calls[] = ['operation' => 'cancel', 'key' => $idempotencyKey, 'amount' => 0];

            return $this->intents[$intentId] = new IntentState($i->id, 'canceled', $i->amountCents, 0, 0, $i->clientSecret, $i->paymentMethodId, null, $i->metadata);
        });
    }

    public function chargeOffSession(string $customerId, string $paymentMethodId, int $amountCents, string $description, array $metadata, string $idempotencyKey): ChargeResult
    {
        return $this->once($idempotencyKey, function () use ($amountCents, $idempotencyKey) {
            $this->calls[] = ['operation' => 'off_session', 'key' => $idempotencyKey, 'amount' => $amountCents];

            return $this->declineOffSession
                ? new ChargeResult(false, 'pi_fake_declined_'.(++$this->sequence), 'Your card requires authentication.')
                : new ChargeResult(true, 'pi_fake_extra_'.(++$this->sequence));
        });
    }

    public function createPaymentLink(string $customerId, int $amountCents, string $description, string $successUrl, array $metadata, string $idempotencyKey): HostedPaymentLink
    {
        return $this->once($idempotencyKey, function () use ($amountCents, $idempotencyKey) {
            $this->calls[] = ['operation' => 'payment_link', 'key' => $idempotencyKey, 'amount' => $amountCents];
            $id = 'cs_fake_'.(++$this->sequence);

            return new HostedPaymentLink($id, "https://checkout.stripe.test/{$id}");
        });
    }

    public function refund(string $intentId, int $amountCents, string $idempotencyKey): RefundResult
    {
        return $this->once($idempotencyKey, function () use ($intentId, $amountCents, $idempotencyKey) {
            $this->intent($intentId);   // must exist, same as Stripe would require
            if ($this->declineRefund) {
                return new RefundResult(false, null, 'Refund could not be processed.');
            }
            $this->calls[] = ['operation' => 'refund', 'key' => $idempotencyKey, 'amount' => $amountCents];

            return new RefundResult(true, 're_fake_'.(++$this->sequence));
        });
    }

    public function parseWebhook(string $payload, string $signatureHeader): WebhookEvent
    {
        if (! hash_equals(self::sign($payload), $signatureHeader)) {
            throw new InvalidWebhookSignature('Signature mismatch.');
        }
        /** @var array{id: string, type: string, data: array{object: array<string, mixed>}} $event */
        $event = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        return new WebhookEvent($event['id'], $event['type'], $event['data']['object']);
    }

    public static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::WEBHOOK_SECRET);
    }

    /** @return list<array{operation: string, key: string, amount: int}> */
    public function callsFor(string $operation): array
    {
        return array_values(array_filter($this->calls, fn (array $c) => $c['operation'] === $operation));
    }

    private function intent(string $id): IntentState
    {
        return $this->intents[$id] ?? throw new RuntimeException("Unknown intent {$id}.");
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function once(string $key, callable $callback): mixed
    {
        if (! array_key_exists($key, $this->idempotent)) {
            $this->idempotent[$key] = $callback();
        }

        return $this->idempotent[$key];
    }
}
