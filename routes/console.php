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

// Morning digests, after the sync has settled: new relevant events first,
// then registration nudges for planned events entering their sign-up window.
Schedule::command('thepiste:notify-new-events')->dailyAt('06:30');
Schedule::command('thepiste:send-registration-reminders')->dailyAt('07:00');
