<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the tournament catalog current from AskFRED (quiet hour, once a day).
Schedule::command('thepiste:sync-askfred')->dailyAt('05:10');
