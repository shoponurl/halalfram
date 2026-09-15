<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Sent synchronously, not queued: an alert about a stuck queue must not wait in that same queue. */
final class AlertMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $level,
        public readonly string $messageText,
        public readonly string $contextJson,
        public readonly string $environment,
        public readonly string $occurredAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->environment}] {$this->level}: ".mb_strimwidth($this->messageText, 0, 120, '…'));
    }

    public function content(): Content
    {
        return new Content(text: 'emails.alert-text');
    }
}
