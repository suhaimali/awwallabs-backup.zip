<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Remove report audit history older than AUDIT_RETENTION_DAYS (default 5 years), daily at midnight
use Illuminate\Support\Facades\Schedule;
Schedule::command('audit:cleanup')->daily();

// Secure daily database backup
Schedule::command('backup:database')->dailyAt('00:30');
