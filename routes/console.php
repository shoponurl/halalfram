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
