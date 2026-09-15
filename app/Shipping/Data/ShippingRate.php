<?php

declare(strict_types=1);

namespace App\Shipping\Data;

final class ShippingRate
{
    public function __construct(
        public readonly string $carrier,
        public readonly string $service,
        public readonly int $rateCents,
        public readonly int $estimatedDays,
    ) {}
}
