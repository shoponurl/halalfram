<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Owner decision (guideline ch. 7, S05): one reminder, then a partial-refund write-off for orders
// still uncollected — needs `php artisan schedule:work` (or a real cron entry) running to fire.
Schedule::command('orders:expire-missed-pickups')->hourly();

// Guideline ch. 8 launch gate (S09): a nightly encrypted backup, and a daily ledger reconciliation that
// raises a critical alert on any mismatch. Both log failures to the `alerts` channel.
Schedule::command('ops:backup')->dailyAt('02:30')->onOneServer()->withoutOverlapping();
Schedule::command('ops:reconcile')->dailyAt('06:00')->onOneServer();
