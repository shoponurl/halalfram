<?php

declare(strict_types=1);

use App\Enums\LaunchGateItem;
use App\Models\LaunchGateEvidence;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/*
 * Guideline ch. 8: encrypted backups and a full restore drill. This runs the real mysqldump, gpg and
 * mysql binaries end to end (CI's Ubuntu runner has all three); it's skipped where they're missing.
 * Point BACKUP_*_BINARY at them to run it locally.
 */

beforeEach(function () {
    $binaries = [
        'mysqldump_binary' => (string) (getenv('BACKUP_MYSQLDUMP_BINARY') ?: 'mysqldump'),
        'mysql_binary' => (string) (getenv('BACKUP_MYSQL_BINARY') ?: 'mysql'),
        'gpg_binary' => (string) (getenv('BACKUP_GPG_BINARY') ?: 'gpg'),
    ];
    foreach ($binaries as $binary) {
        try {
            $available = Process::timeout(15)->run([$binary, '--version'])->successful();
        } catch (Throwable) {
            $available = false;
        }
        if (! $available) {
            test()->markTestSkipped("{$binary} is not available on this machine.");
        }
    }

    $this->directory = storage_path('framework/testing/backups-'.bin2hex(random_bytes(4)));
    config([
        'launch.backup.directory' => $this->directory,
        'launch.backup.passphrase' => 'correct horse battery staple, test only',
        'launch.backup.restore_drill_database' => 'halal_restore_drill_test',
        'launch.backup.connection' => 'mysql_migrate',
        ...collect($binaries)->mapWithKeys(fn (string $bin, string $key) => ["launch.backup.{$key}" => $bin])->all(),
    ]);
});

afterEach(function () {
    if (isset($this->directory)) {
        File::deleteDirectory($this->directory);
    }
});

it('writes an encrypted backup, restores it into a scratch database, and records the recovery time', function () {
    $this->artisan('ops:backup')->assertSuccessful();

    $files = glob($this->directory.'/halal-*.sql.gpg');
    expect($files)->toHaveCount(1)
        ->and(glob($this->directory.'/*.sql'))->toBeEmpty()   // the plaintext dump never stays on disk
        ->and(str_contains((string) file_get_contents($files[0]), 'CREATE TABLE'))->toBeFalse();

    $this->artisan('ops:restore-drill')->assertSuccessful()->expectsOutputToContain('PASS — recovery time');

    $evidence = LaunchGateEvidence::query()->sole();
    expect($evidence->item)->toBe(LaunchGateItem::RestoreDrill)
        ->and($evidence->passed)->toBeTrue()
        ->and($evidence->evidence)->toContain('migrations complete')
        ->and($evidence->duration_ms)->toBeGreaterThan(0);
});

it('fails the drill, and records the failure, when the backup cannot be decrypted', function () {
    $this->artisan('ops:backup')->assertSuccessful();
    config(['launch.backup.passphrase' => 'a different passphrase, twenty+ chars']);

    $this->artisan('ops:restore-drill')->assertFailed();

    expect(LaunchGateEvidence::query()->sole()->passed)->toBeFalse();
});

it('refuses to back up without a strong passphrase', function () {
    config(['launch.backup.passphrase' => 'short']);

    $this->artisan('ops:backup')->assertFailed()->expectsOutputToContain('BACKUP_PASSPHRASE');
});
