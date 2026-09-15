<?php

declare(strict_types=1);

return [
    /*
     * Guideline ch. 6, Sprint 09: the first real customers get a soft launch — store pickup plus a
     * few delivery zip codes, no nationwide shipping — before opening everything up. Turning it off
     * is a config change (SOFT_LAUNCH=false), not a deploy.
     */
    'soft_launch' => [
        'enabled' => (bool) env('SOFT_LAUNCH', false),
        // Comma-separated. Must also be in an active delivery zone. Empty = pickup only.
        'delivery_zips' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('SOFT_LAUNCH_DELIVERY_ZIPS', ''))))),
    ],

    /*
     * Guideline ch. 8 launch gate: "monitoring and alerting reach a real person". Log::critical()
     * calls (failed settlements, refunds, label purchases, ledger mismatches) go to the `alerts` log
     * channel in production — a Slack incoming webhook and/or email to a real inbox.
     */
    'alerts' => [
        'email' => env('ALERT_EMAIL'),
    ],

    /*
     * Guideline ch. 6 Sprint 09 / ch. 8: encrypted backups and a full restore drill with a recovery
     * time. `ops:backup` dumps with mysqldump and encrypts with GnuPG (AES-256, integrity-protected) —
     * a vetted tool, not home-made crypto. Copy the encrypted files off the server (runbook §4).
     */
    'backup' => [
        'connection' => env('BACKUP_DB_CONNECTION', 'mysql_migrate'),
        'directory' => env('BACKUP_DIRECTORY', storage_path('app/private/backups')),
        'passphrase' => env('BACKUP_PASSPHRASE'),
        'keep' => (int) env('BACKUP_KEEP', 14),
        'mysqldump_binary' => env('BACKUP_MYSQLDUMP_BINARY', 'mysqldump'),
        'mysql_binary' => env('BACKUP_MYSQL_BINARY', 'mysql'),
        'gpg_binary' => env('BACKUP_GPG_BINARY', 'gpg'),
        'restore_drill_database' => env('RESTORE_DRILL_DATABASE', 'halal_restore_drill'),
    ],

    // How recent a recorded drill has to be for `launch:check` to count it as evidence.
    'drill_max_age_days' => 30,

    // Guideline ch. 8: a recall drill on production data finishes within 30 seconds.
    'recall_drill_max_seconds' => 30,
];
