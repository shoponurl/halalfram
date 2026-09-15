<?php

declare(strict_types=1);

namespace App\Actions\Launch;

use App\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Guideline ch. 8: "most teams find out their backup was incomplete on the exact day they need it."
 * Decrypts a backup, restores it into a separate scratch database, and proves it's usable: every
 * migration present, row counts readable, and every ledger reconciling on the restored data. The
 * elapsed time is the recovery time the runbook quotes.
 */
final class RunRestoreDrill extends Action
{
    public const CONNECTION = 'restore_drill';

    private const TABLES = ['users', 'products', 'lots', 'stock_movements', 'orders', 'order_items', 'weight_events', 'payment_transactions', 'store_credit_events', 'audit_logs'];

    public function __construct(private readonly ReconcileLedgers $reconcile) {}

    /**
     * @return array{passed: bool, duration_ms: int, missing_migrations: list<string>, mismatches: list<string>, counts: array<string, array{restored: int, live: int}>}
     */
    public function handle(string $backupPath, bool $keepDatabase = false): array
    {
        if (! is_file($backupPath)) {
            throw new RuntimeException("No backup file at {$backupPath}.");
        }

        $started = hrtime(true);
        $db = BackupDatabase::connectionConfig();
        $scratch = (string) config('launch.backup.restore_drill_database');
        if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $scratch) || $scratch === $db['database']) {
            throw new RuntimeException('RESTORE_DRILL_DATABASE must be a plain name, different from the live database.');
        }

        $sqlPath = storage_path('app/private/restore-drill-'.bin2hex(random_bytes(8)).'.sql');
        File::ensureDirectoryExists(dirname($sqlPath), 0700);
        $admin = DB::connection((string) config('launch.backup.connection'));

        try {
            $decrypt = Process::input(BackupDatabase::passphrase())->timeout(3600)->run([
                (string) config('launch.backup.gpg_binary'),
                '--batch', '--yes', '--pinentry-mode', 'loopback', '--passphrase-fd', '0',
                '--output', BackupDatabase::cliPath($sqlPath), '--decrypt', BackupDatabase::cliPath($backupPath),
            ]);
            if (! $decrypt->successful() || ! is_file($sqlPath)) {
                throw new RuntimeException('Decrypting the backup failed (wrong passphrase, or the file is damaged): '.trim($decrypt->errorOutput()));
            }

            $admin->statement("DROP DATABASE IF EXISTS `{$scratch}`");
            $admin->statement("CREATE DATABASE `{$scratch}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            $sql = fopen($sqlPath, 'rb');
            if ($sql === false) {
                throw new RuntimeException('Could not open the decrypted dump.');
            }
            try {
                $import = Process::env(['MYSQL_PWD' => $db['password']])->input($sql)->timeout(3600)->run([
                    (string) config('launch.backup.mysql_binary'),
                    '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                    $scratch,
                ]);
            } finally {
                fclose($sql);
            }
            if (! $import->successful()) {
                throw new RuntimeException('Importing the dump failed: '.trim($import->errorOutput()));
            }

            config(['database.connections.'.self::CONNECTION => array_merge((array) config('database.connections.'.config('launch.backup.connection')), ['database' => $scratch])]);
            DB::purge(self::CONNECTION);

            $missingMigrations = $this->missingMigrations();
            $counts = $this->counts();
            $mismatches = $this->reconcileRestored();
        } finally {
            if (is_file($sqlPath)) {
                unlink($sqlPath);
            }
        }

        $durationMs = intdiv(hrtime(true) - $started, 1_000_000);

        if (! $keepDatabase) {
            DB::purge(self::CONNECTION);
            $admin->statement("DROP DATABASE IF EXISTS `{$scratch}`");
        }

        return [
            'passed' => $missingMigrations === [] && $mismatches === [],
            'duration_ms' => $durationMs,
            'missing_migrations' => $missingMigrations,
            'mismatches' => $mismatches,
            'counts' => $counts,
        ];
    }

    /** @return list<string> */
    private function missingMigrations(): array
    {
        $expected = array_map(fn (string $file): string => basename($file, '.php'), glob(database_path('migrations/*.php')) ?: []);
        $restored = DB::connection(self::CONNECTION)->table('migrations')->pluck('migration')->all();

        return array_values(array_diff($expected, $restored));
    }

    /** @return array<string, array{restored: int, live: int}> */
    private function counts(): array
    {
        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = [
                'restored' => DB::connection(self::CONNECTION)->table($table)->count(),
                'live' => DB::connection((string) config('launch.backup.connection'))->table($table)->count(),
            ];
        }

        return $counts;
    }

    /** @return list<string> */
    private function reconcileRestored(): array
    {
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection(self::CONNECTION);
        try {
            return $this->reconcile->handle();
        } finally {
            DB::setDefaultConnection($original);
        }
    }
}
