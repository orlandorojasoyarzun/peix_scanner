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
|
| The hourly purge keeps storage/app/private/scan-uploads/ from growing
| unbounded. Files older than 24h are not used by any active cache entry
| (scan caches TTL out at 10 min, species caches at 30 min), so dropping
| them loses nothing user-visible.
|
| A daily `--deep` pass walks the scan-state index and drops cache slots
| whose backing file no longer exists on disk. Cheap on a database cache,
| still bounded on Redis/file.
|
| In Railway this runs via `php artisan schedule:work` in a sidecar process,
| or alternatively a cron-style deployment trigger — see README.
*/
Schedule::command('scans:purge --older-than=24h')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('scans:purge --older-than=24h --deep')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->at('03:17');
