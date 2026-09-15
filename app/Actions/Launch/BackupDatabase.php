<?php

declare(strict_types=1);

namespace App\Actions\Launch;

use App\Actions\Action;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Dumps the database with mysqldump and encrypts it with GnuPG (symmetric AES-256; GnuPG adds an
 * integrity check, so a tampered or truncated file fails to decrypt rather than restoring garbage).
 * The plaintext dump only ever exists for the length of this call.
 */
final class BackupDatabase extends Action
{
    /** @return array{path: string, bytes: int, duration_ms: int} */
    public function handle(): array
    {
        $started = hrtime(true);
        $db = self::connectionConfig();
        $passphrase = self::passphrase();

        $directory = (string) config('launch.backup.directory');
        File::ensureDirectoryExists($directory, 0700);

        $stamp = now()->format('Y-m-d_His');
        $sqlPath = "{$directory}/halal-{$stamp}.sql";
        $encryptedPath = "{$sqlPath}.gpg";

        try {
            $dump = Process::env(['MYSQL_PWD' => $db['password']])->timeout(3600)->run([
                (string) config('launch.backup.mysqldump_binary'),
                '--single-transaction', '--quick', '--no-tablespaces', '--skip-triggers', '--hex-blob',
                '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                '--result-file='.self::cliPath($sqlPath),
                $db['database'],
            ]);
            if (! $dump->successful() || ! is_file($sqlPath) || filesize($sqlPath) === 0) {
                throw new RuntimeException('mysqldump failed: '.trim($dump->errorOutput()));
            }

            $encrypt = Process::input($passphrase)->timeout(3600)->run([
                (string) config('launch.backup.gpg_binary'),
                '--batch', '--yes', '--pinentry-mode', 'loopback', '--passphrase-fd', '0',
                '--symmetric', '--cipher-algo', 'AES256', '--compress-algo', 'zlib',
                '--output', self::cliPath($encryptedPath), self::cliPath($sqlPath),
            ]);
            if (! $encrypt->successful() || ! is_file($encryptedPath)) {
                throw new RuntimeException('gpg encryption failed: '.trim($encrypt->errorOutput()));
            }
        } finally {
            if (is_file($sqlPath)) {
                unlink($sqlPath);
            }
        }

        $this->prune($directory);

        return ['path' => $encryptedPath, 'bytes' => (int) filesize($encryptedPath), 'duration_ms' => intdiv(hrtime(true) - $started, 1_000_000)];
    }

    /** @return array{host: string, port: string, database: string, username: string, password: string} */
    public static function connectionConfig(): array
    {
        $name = (string) config('launch.backup.connection');
        /** @var array<string, mixed>|null $config */
        $config = config("database.connections.{$name}");
        if (! is_array($config) || ! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Backups need a MySQL/MariaDB connection; \"{$name}\" isn't one.");
        }

        return [
            'host' => (string) $config['host'],
            'port' => (string) $config['port'],
            'database' => (string) $config['database'],
            'username' => (string) $config['username'],
            'password' => (string) $config['password'],
        ];
    }

    public static function passphrase(): string
    {
        $passphrase = (string) config('launch.backup.passphrase');
        if (mb_strlen($passphrase) < 20) {
            throw new RuntimeException('Set BACKUP_PASSPHRASE (20+ characters, kept in the host\'s secrets and a password manager — never next to the backups).');
        }

        return $passphrase;
    }

    /** gpg/mysql from Git for Windows (MSYS) accept forward slashes; Linux paths pass through unchanged. */
    public static function cliPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private function prune(string $directory): void
    {
        $files = glob("{$directory}/halal-*.sql.gpg") ?: [];
        rsort($files);   // timestamped names sort newest-first
        foreach (array_slice($files, max(1, (int) config('launch.backup.keep'))) as $old) {
            unlink($old);
        }
    }
}
