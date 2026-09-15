<?php

declare(strict_types=1);

namespace App\Payments\Exceptions;

use RuntimeException;

final class PaymentsNotConfigured extends RuntimeException
{
    public static function make(): self
    {
        return new self('Online payments are not configured. Set STRIPE_KEY, STRIPE_SECRET and STRIPE_WEBHOOK_SECRET.');
    }

    public static function forPayPal(): self
    {
        return new self('PayPal is not configured. Set PAYPAL_CLIENT_ID and PAYPAL_CLIENT_SECRET.');
    }
}
