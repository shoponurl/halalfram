<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Launch\RunRestoreDrill;
use App\Enums\LaunchGateItem;
use App\Models\LaunchGateEvidence;
use Illuminate\Console\Command;
use Throwable;

/** Guideline ch. 8: a full restore drill from backup, with the recovery time recorded. */
class RestoreDrillCommand extends Command
{
    protected $signature = 'ops:restore-drill
        {backup? : path to a .sql.gpg backup; defaults to the newest one in the backup directory}
        {--keep : leave the scratch database in place for inspection}';

    protected $description = 'Restores a backup into a scratch database, verifies it, and records the recovery time';

    public function handle(RunRestoreDrill $drill): int
    {
        $path = $this->argument('backup');
        if (! is_string($path)) {
            $files = glob(config('launch.backup.directory').'/halal-*.sql.gpg') ?: [];
            rsort($files);
            $path = $files[0] ?? null;
        }
        if ($path === null) {
            $this->error('No backup found — run `php artisan ops:backup` first (or pass a path).');

            return self::FAILURE;
        }

        try {
            $result = $drill->handle($path, (bool) $this->option('keep'));
        } catch (Throwable $e) {
            LaunchGateEvidence::record(LaunchGateItem::RestoreDrill, false, basename($path).': '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Table', 'Rows restored', 'Rows live now'], collect($result['counts'])->map(fn (array $c, string $table) => [$table, $c['restored'], $c['live']])->values()->all());
        foreach ($result['missing_migrations'] as $migration) {
            $this->line("<fg=red>✗</> missing migration: {$migration}");
        }
        foreach ($result['mismatches'] as $mismatch) {
            $this->line("<fg=red>✗</> {$mismatch}");
        }

        $seconds = number_format($result['duration_ms'] / 1000, 1);
        $evidence = sprintf(
            '%s (%s KB) restored and verified in %ss — migrations %s, reconciliation %s, %d orders / %d lots restored',
            basename($path),
            number_format((int) filesize($path) / 1024, 1),
            $seconds,
            $result['missing_migrations'] === [] ? 'complete' : count($result['missing_migrations']).' missing',
            $result['mismatches'] === [] ? 'clean' : count($result['mismatches']).' mismatch(es)',
            $result['counts']['orders']['restored'],
            $result['counts']['lots']['restored'],
        );
        LaunchGateEvidence::record(LaunchGateItem::RestoreDrill, $result['passed'], $evidence, $result['duration_ms']);

        $result['passed'] ? $this->info("PASS — recovery time {$seconds}s. {$evidence}") : $this->error("FAIL — {$evidence}");

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
