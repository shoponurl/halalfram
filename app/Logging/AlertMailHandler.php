<?php

declare(strict_types=1);

namespace App\Logging;

use App\Mail\AlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Emails an alert, at most once per identical message every 10 minutes, so a failure storm (a gateway
 * outage failing hundreds of jobs) sends one email rather than hundreds. Never throws: a broken mail
 * setup must not take down the request or job that was only trying to report a problem.
 */
final class AlertMailHandler extends AbstractProcessingHandler
{
    public const THROTTLE_MINUTES = 10;

    public function __construct(private readonly string $email, Level $level = Level::Critical)
    {
        parent::__construct($level);
    }

    protected function write(LogRecord $record): void
    {
        try {
            if (! Cache::add('alert-mail:'.hash('sha256', $record->message), true, now()->addMinutes(self::THROTTLE_MINUTES))) {
                return;
            }

            // Context holds ids and gateway error text only — call sites never log customer PII (CLAUDE.md rule 9).
            $context = json_encode($record->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);

            Mail::to($this->email)->send(new AlertMail(
                level: $record->level->getName(),
                messageText: $record->message,
                contextJson: $context === false ? '{}' : $context,
                environment: (string) config('app.env'),
                occurredAt: $record->datetime->format('Y-m-d H:i:s T'),
            ));
        } catch (Throwable) {
            // Swallowed on purpose — see the class docblock.
        }
    }
}
