<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * FleetManager Pro - Scheduled Tasks
 *
 * These tasks run automatically via the Laravel scheduler.
 * Make sure to add this cron entry to your server:
 * * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
 */

// Send daily alerts at 8:00 AM every day
Schedule::command('alerts:send-daily')
    ->dailyAt('08:00')
    ->timezone('Europe/Paris')
    ->onSuccess(function () {
        \Log::info('Daily alerts sent successfully');
    })
    ->onFailure(function () {
        \Log::error('Daily alerts failed to send');
    });
