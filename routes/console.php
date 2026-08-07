<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file defines all scheduled tasks for the SIBAKA portal.
| The scheduler is managed by Supervisor (schedule:work) on the VPS.
|
*/

// Purge expired anonymous metadata (daily at 3 AM)
Schedule::job(new \App\Jobs\PurgeAnonymousMetadata)->dailyAt('03:00');

// Auto-lock inactive threads (daily at 4 AM)
Schedule::job(new \App\Jobs\AutoLockThreads)->dailyAt('04:00');

// Prune audit logs older than 365 days (daily at 5 AM)
Schedule::command('sibaka:prune-audit-logs')->dailyAt('05:00');

// Refresh moderation dashboard stats cache (every minute)
Schedule::command('moderation:refresh-stats')->everyMinute();

// Clean expired sessions (daily at 6 AM)
Schedule::command('session:gc')->dailyAt('06:00');

// Prune failed jobs older than 7 days (daily at 6:30 AM)
Schedule::command('queue:prune-failed --hours=168')->dailyAt('06:30');
