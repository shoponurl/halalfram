<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Guideline ch. 6, Sprint 09: pickup plus a short list of delivery zips; no shipping. */
final class SoftLaunch
{
    public static function enabled(): bool
    {
        return (bool) config('launch.soft_launch.enabled');
    }

    public static function allowsDelivery(): bool
    {
        return ! self::enabled() || self::deliveryZips() !== [];
    }

    public static function allowsShipping(): bool
    {
        return ! self::enabled();
    }

    /** @throws ValidationException */
    public static function assertDeliveryZipAllowed(string $zip): void
    {
        if (self::enabled() && ! in_array($zip, self::deliveryZips(), true)) {
            throw ValidationException::withMessages([
                'delivery_zip' => "We're opening delivery gradually and don't deliver to {$zip} just yet. Store pickup is available for every order.",
            ]);
        }
    }

    /** @throws ValidationException */
    public static function assertShippingAllowed(): void
    {
        if (! self::allowsShipping()) {
            throw ValidationException::withMessages([
                'fulfilment_method' => 'Nationwide shipping isn\'t available yet — please choose store pickup.',
            ]);
        }
    }

    /** @return list<string> */
    private static function deliveryZips(): array
    {
        /** @var list<string> $zips */
        $zips = config('launch.soft_launch.delivery_zips', []);

        return $zips;
    }
}
