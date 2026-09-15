<?php

declare(strict_types=1);

namespace App\Mail;

use App\Actions\Payments\SendStoreCreditLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class StoreCreditLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Use your Halal Brothers store credit');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.store-credit-link', with: ['url' => $this->url, 'minutes' => SendStoreCreditLink::LINK_MINUTES]);
    }
}
