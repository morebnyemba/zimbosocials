<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('upstream:sync-orders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('upstream:sync-services')->dailyAt('02:00')->withoutOverlapping();

// Expire stale pending transactions older than 24 hours
Schedule::command('transactions:cleanup-stale --hours=24')->hourly()->withoutOverlapping();

// Prune completed queue jobs older than 48 hours
Schedule::command('queue:prune-batches --hours=48')->daily();

// Close previous month's leaderboard on the 1st at 00:05 and notify winners
Schedule::command('leaderboard:close-month --notify')->monthlyOn(1, '00:05')->withoutOverlapping();
