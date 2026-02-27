<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| YogaPro Scheduled Tasks
|--------------------------------------------------------------------------
|
| Activate by adding ONE crontab entry on the server:
|     * * * * * php /home/shani/projects/YogaPro/artisan schedule:run >> /dev/null 2>&1
|
*/

// 1. Daily channel scan — fetch new videos from the YouTube channel
// Schedule::command('channel:scan')->daily();
// 
// 2. Every 30 min analysis dispatch — push a batch of pending videos onto the queue
//    Batch size is controlled by ANALYSIS_BATCH_SIZE in .env (default 5)
//    Only videos between 10–50 minutes are eligible for analysis
// Schedule::command('videos:analyze')->everyTenMinutes();

// 3. Hourly enrichment dispatch — pick up any new yoga_move stubs from video analysis
//    and enrich them via Gemini (text-only, cheap)
// Schedule::command('yoga:enrich')->hourly();
