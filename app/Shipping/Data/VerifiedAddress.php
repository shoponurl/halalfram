<?php

declare(strict_types=1);

namespace App\Shipping\Data;

final class VerifiedAddress
{
    /** @param list<string> $errors */
    public function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
    ) {}
}
