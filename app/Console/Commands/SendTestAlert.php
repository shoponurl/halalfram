<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Guideline ch. 8: monitoring and alerting reach a real person. Fires one critical log through the
 * normal channel stack; once the person confirms it arrived, sign off `alert_reached_person`.
 */
class SendTestAlert extends Command
{
    protected $signature = 'ops:test-alert';

    protected $description = 'Sends a test critical alert through the configured alerts channel';

    public function handle(): int
    {
        Log::critical('Test alert — if you are reading this, alerts reach a real person', ['sent_at' => now()->toIso8601String(), 'host' => gethostname()]);

        $this->info('Sent. When it arrives: php artisan launch:attest alert_reached_person --evidence="received by <name> via <Slack/email> at <time>"');

        return self::SUCCESS;
    }
}
