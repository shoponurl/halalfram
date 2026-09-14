<?php

declare(strict_types=1);

namespace App\Payments\Data;

final readonly class HostedPaymentLink
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
