<?php

declare(strict_types=1);

namespace App\Payments\Data;

final readonly class RefundResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $refundId,
        public ?string $failureMessage = null,
    ) {}
}
