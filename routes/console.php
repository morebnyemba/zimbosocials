<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Heartbeat: proves the host's cron is actually invoking `schedule:run`.
// Every scheduled behaviour (order status sync, notifications queue,
// leaderboard close, cleanups) silently dies when that cron entry is missing —
// the admin dashboard watches this timestamp and warns loudly when it goes
// stale. See AGENTS.md "Set up a cron job" for the required crontab line.
Schedule::call(function (): void {
    Cache::put('scheduler:heartbeat_at', now()->toIso8601String(), now()->addWeek());
})->everyMinute()->name('scheduler-heartbeat');

Schedule::command('upstream:sync-orders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('upstream:sync-services')->dailyAt('02:00')->withoutOverlapping();

// Process queued jobs (WhatsApp/email notifications, etc). QUEUE_CONNECTION=database
// has no persistent worker on shared hosting, so this is the standard
// cron-driven substitute: process whatever's queued, then exit — self-limited
// to 50s so it never runs into (or blocks) the next minute's scheduler tick.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping();

// Expire stale pending deposits older than 24 hours (final-polls Paynow
// first; skips proof-submitted deposits; never touches withdrawals)
Schedule::command('transactions:cleanup-stale --hours=24')->hourly()->withoutOverlapping();

// Close contracts past their deadline and refund unused-slot escrow
Schedule::command('contracts:close-expired')->dailyAt('01:00')->withoutOverlapping();

// Safety net: verify every wallet balance against the transaction ledger
Schedule::command('wallet:reconcile')->dailyAt('03:00')->withoutOverlapping();

// Flag active orders with no status movement for 5+ days
Schedule::command('orders:flag-stuck')->dailyAt('08:00')->withoutOverlapping();

// Nightly database dump to storage/app/backups (keeps the last 14)
Schedule::command('db:backup')->dailyAt('03:30')->withoutOverlapping();

// Prune completed queue jobs older than 48 hours
Schedule::command('queue:prune-batches --hours=48')->daily();

// Close previous month's leaderboard on the 1st at 00:05 and notify winners
Schedule::command('leaderboard:close-month --notify')->monthlyOn(1, '00:05')->withoutOverlapping();

// Warn referrers before their ongoing-commission window lapses
Schedule::command('referral:warn-commission-expiry')->dailyAt('09:00')->withoutOverlapping();
