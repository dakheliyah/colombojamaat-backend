<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:backup')
    ->everyThirtyMinutes()
    ->timezone('Asia/Colombo')
    ->when(function (): bool {
        // Active 08:00–02:00 inclusive (Colombo). Quiet 02:30–07:30.
        $minutes = now('Asia/Colombo')->hour * 60 + now('Asia/Colombo')->minute;

        return $minutes >= 8 * 60 || $minutes <= 2 * 60;
    })
    ->appendOutputTo(storage_path('logs/db-backup.log'));
