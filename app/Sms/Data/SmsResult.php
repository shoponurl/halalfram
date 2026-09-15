<?php

declare(strict_types=1);

namespace App\Sms\Data;

final readonly class SmsResult
{
    public function __construct(
        public bool $succeeded,
        public ?string $messageId,
        public ?string $failureMessage = null,
    ) {}
}
