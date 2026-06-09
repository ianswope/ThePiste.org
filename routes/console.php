<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the tournament catalog current from AskFRED (quiet hour, once a day).
Schedule::command('thepiste:sync-askfred')->dailyAt('05:10');

// Deep audit 3x/week: re-walk the whole season, reconcile date changes by
// source id, and flag upcoming events that vanished (possible cancellations).
Schedule::command('thepiste:sync-askfred --full')->days([1, 3, 5])->at('05:40');
