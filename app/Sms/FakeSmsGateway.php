<?php

declare(strict_types=1);

namespace App\Sms;

use App\Sms\Data\SmsResult;

/** In-memory stand-in for tests and local development without Twilio credentials. */
final class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, body: string, key: string}> */
    public array $sent = [];

    public bool $decline = false;

    public function isConfigured(): bool
    {
        return true;
    }

    public function send(string $toE164, string $body, string $idempotencyKey): SmsResult
    {
        if ($this->decline) {
            return new SmsResult(false, null, 'Carrier rejected the message.');
        }

        $this->sent[] = ['to' => $toE164, 'body' => $body, 'key' => $idempotencyKey];

        return new SmsResult(true, 'SM_fake_'.count($this->sent));
    }
}
