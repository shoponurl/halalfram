<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Launch\BackupDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'ops:backup';

    protected $description = 'Writes an encrypted (GnuPG AES-256) database backup and prunes old ones';

    public function handle(BackupDatabase $backup): int
    {
        try {
            $result = $backup->handle();
        } catch (Throwable $e) {
            Log::critical('Database backup failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Backup written: %s (%s KB, %d ms). Copy it off this server — see docs/runbook.md §4.', $result['path'], number_format($result['bytes'] / 1024, 1), $result['duration_ms']));

        return self::SUCCESS;
    }
}
