<?php

declare(strict_types=1);

namespace App\Shipping\Data;

final class PurchasedLabel
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $carrier,
        public readonly string $service,
        public readonly int $rateCents,
        public readonly string $trackingNumber,
        public readonly string $trackingUrl,
        public readonly string $labelUrl,
    ) {}
}
