<?php

declare(strict_types=1);

namespace App\Sms;

use App\Sms\Data\SmsResult;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

/**
 * Twilio implementation (guideline ch. 6, Sprint 06). Twilio's Messages API has no server-side
 * idempotency key of its own — the dedupe guarantee comes from App\Models\NotificationLog's unique
 * constraint, checked before this is ever called, not from anything in this class.
 */
final class TwilioSmsGateway implements SmsGateway
{
    private ?Client $client = null;

    public function __construct(
        private readonly ?string $accountSid,
        private readonly ?string $authToken,
        private readonly ?string $messagingServiceSid,
        private readonly ?string $fromNumber,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->accountSid) && filled($this->authToken) && (filled($this->messagingServiceSid) || filled($this->fromNumber));
    }

    public function send(string $toE164, string $body, string $idempotencyKey): SmsResult
    {
        try {
            $params = $this->messagingServiceSid !== null
                ? ['messagingServiceSid' => $this->messagingServiceSid, 'body' => $body]
                : ['from' => (string) $this->fromNumber, 'body' => $body];

            $message = $this->client()->messages->create($toE164, $params);

            return new SmsResult(true, $message->sid);
        } catch (TwilioException $e) {
            return new SmsResult(false, null, $e->getMessage());
        }
    }

    private function client(): Client
    {
        return $this->client ??= new Client((string) $this->accountSid, (string) $this->authToken);
    }
}
