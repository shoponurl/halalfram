<?php

declare(strict_types=1);

namespace App\Sms;

use App\Sms\Data\SmsResult;

/**
 * The only door to the SMS carrier (guideline ch. 6, Sprint 06). Every send takes an idempotency key
 * (rule 03), so a retried notification job can never text a customer twice.
 */
interface SmsGateway
{
    public function isConfigured(): bool;

    public function send(string $toE164, string $body, string $idempotencyKey): SmsResult;
}
