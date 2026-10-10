<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('leads:auto-return-untouched')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('email:sync-inboxes')
    ->everyMinute()
    ->withoutOverlapping();
