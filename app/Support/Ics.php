<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Builds iCalendar (.ics) text. Shared by the shared-plan feed and the
 * per-event "add to calendar" download so escaping and the all-day VEVENT
 * shape live in one place.
 */
class Ics
{
    /** Wrap pre-built VEVENT blocks in a VCALENDAR. */
    public static function calendar(string $name, array $vevents): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ThePiste//thepiste.org//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.self::esc($name),
            ...$vevents,
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    /** One all-day VEVENT. DTEND is exclusive in iCal, so it's the day after ends_on. */
    public static function event(string $uid, Carbon $start, Carbon $end, string $summary, string $location, string $description, Carbon $stamp): string
    {
        return implode("\r\n", [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$end->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.self::esc($summary),
            'LOCATION:'.self::esc($location),
            'DESCRIPTION:'.self::esc($description),
            'END:VEVENT',
        ]);
    }

    public static function esc(string $v): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\n'], $v);
    }
}
