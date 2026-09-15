<?php

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Data\ChargeResult;
use App\Payments\Data\HostedPaymentLink;
use App\Payments\Data\IntentState;
use App\Payments\Data\RefundResult;
use App\Payments\Data\WebhookEvent;
use App\Payments\Exceptions\InvalidWebhookSignature;

/**
 * The only door to the card processor. Every money-moving call takes an idempotency key (rule 03),
 * so a retried job or a double click can never charge twice.
 */
interface PaymentGateway
{
    public function isConfigured(): bool;

    public function publishableKey(): ?string;

    public function createCustomer(string $name, string $email, string $idempotencyKey): string;

    /**
     * Manual-capture PaymentIntent for the hold, with the card saved for off-session overage charges.
     *
     * @param  array<string, string>  $metadata
     */
    public function createHold(int $amountCents, string $customerId, string $description, array $metadata, string $idempotencyKey): IntentState;

    public function retrieveIntent(string $intentId): IntentState;

    public function capture(string $intentId, int $amountCents, string $idempotencyKey): IntentState;

    public function cancel(string $intentId, string $idempotencyKey): IntentState;

    /** @param array<string, string> $metadata */
    public function chargeOffSession(string $customerId, string $paymentMethodId, int $amountCents, string $description, array $metadata, string $idempotencyKey): ChargeResult;

    /** @param array<string, string> $metadata */
    public function createPaymentLink(string $customerId, int $amountCents, string $description, string $successUrl, array $metadata, string $idempotencyKey): HostedPaymentLink;

    /** Refunds a captured PaymentIntent, in whole or in part (guideline ch. 6, Sprint 05: missed pickup/delivery). */
    public function refund(string $intentId, int $amountCents, string $idempotencyKey): RefundResult;

    /** @throws InvalidWebhookSignature */
    public function parseWebhook(string $payload, string $signatureHeader): WebhookEvent;
}
