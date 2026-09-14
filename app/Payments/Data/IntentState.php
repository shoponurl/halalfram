<?php

declare(strict_types=1);

namespace App\Payments\Data;

use Carbon\CarbonImmutable;

final readonly class IntentState
{
    /** @param array<string, string> $metadata */
    public function __construct(
        public string $id,
        public string $status,                   // requires_payment_method | requires_capture | succeeded | canceled | …
        public int $amountCents,
        public int $amountCapturableCents,
        public int $amountReceivedCents,
        public ?string $clientSecret = null,
        public ?string $paymentMethodId = null,
        public ?CarbonImmutable $captureBefore = null,
        public array $metadata = [],
    ) {}

    public function isAuthorized(): bool
    {
        return $this->status === 'requires_capture';
    }
}
