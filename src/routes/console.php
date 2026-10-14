<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Optional auto-generation; only runs after explicit opt-in and when the
// existing Laravel scheduler (cron schedule:run) is active.
if (config('rms_recommendations.auto_enabled', false)) {
    Schedule::command('rms:recommendations-generate')
        ->everyTwoMinutes()
        ->withoutOverlapping();
}
