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
}
