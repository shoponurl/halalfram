<?php

declare(strict_types=1);

namespace App\Payments;

use InvalidArgumentException;

/**
 * Resolves the right gateway for an order's chosen payment method (guideline ch. 6, Sprint 06: PayPal
 * joins the Stripe card rail). Resolves from the container on every call — rather than caching an
 * injected instance — so tests can swap PaymentGateway::class/PayPalGateway::class for a fake mid-run.
 */
final class PaymentGatewayFactory
{
    /** Cash orders have no gateway at all — nothing to authorize or capture. */
    public function for(string $paymentMethod): ?PaymentGateway
    {
        return match ($paymentMethod) {
            'card' => app(PaymentGateway::class),
            'paypal' => app(PayPalGateway::class),
            'cash' => null,
            default => throw new InvalidArgumentException("Unknown payment method \"{$paymentMethod}\"."),
        };
    }
}
