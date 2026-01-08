<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Subscription reminder emails - run daily at 9 AM
Schedule::command('subscriptions:send-trial-reminders')->dailyAt('09:00');
Schedule::command('subscriptions:send-expiration-reminders')->dailyAt('09:00');
