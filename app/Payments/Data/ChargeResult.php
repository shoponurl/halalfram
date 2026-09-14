<?php

declare(strict_types=1);

namespace App\Payments\Data;

final readonly class ChargeResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $intentId,
        public ?string $failureMessage = null,
    ) {}
}
