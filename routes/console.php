<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Nightly database backup (spatie/laravel-backup) with 14-day retention
| (config/backup.php). Requires a single cPanel cron entry driving the
| scheduler — documented in docs/ADMIN_GO_LIVE_CHECKLIST.md:
|   * * * * * cd /home/almadin1/office.ncatfmd.com.ng && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('backup:run --only-db')->daily()->at('01:00');
Schedule::command('backup:clean')->daily()->at('01:30');
