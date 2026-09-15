<?php

declare(strict_types=1);

namespace App\Shipping\Data;

final class TrackingUpdate
{
    public function __construct(
        public readonly string $shipmentId,
        public readonly string $status,   // in_transit | delivered | failure | unknown
    ) {}
}
