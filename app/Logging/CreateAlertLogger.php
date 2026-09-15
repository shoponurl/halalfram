<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Handler\SlackWebhookHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * The `alerts` log channel: every Log::critical() (a settlement, refund or label purchase that failed
 * after retries; a ledger mismatch) goes to a Slack incoming webhook and/or an alert inbox, whichever
 * is configured. Guideline ch. 8: monitoring and alerting must reach a real person.
 */
final class CreateAlertLogger
{
    /** @param  array<string, mixed>  $config */
    public function __invoke(array $config): Logger
    {
        $level = match (strtolower((string) ($config['level'] ?? 'critical'))) {
            'error' => Level::Error,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Critical,   // anything noisier than error would page someone for routine events
        };
        $logger = new Logger('alerts');

        $slackUrl = config('logging.channels.slack.url');
        if (is_string($slackUrl) && $slackUrl !== '') {
            $logger->pushHandler(new SlackWebhookHandler($slackUrl, username: 'Halal Brothers alerts', level: $level));
        }

        $email = config('launch.alerts.email');
        if (is_string($email) && $email !== '') {
            $logger->pushHandler(new AlertMailHandler($email, $level));
        }

        return $logger;
    }
}
