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
        // Fold the header lines here; the VEVENT blocks are already folded line
        // by line in event(), so don't re-fold those multi-line strings.
        $header = array_map(self::fold(...), [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ThePiste//thepiste.org//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.self::esc($name),
        ]);

        $lines = [...$header, ...$vevents, 'END:VCALENDAR'];

        return implode("\r\n", $lines)."\r\n";
    }

    /** One all-day VEVENT. DTEND is exclusive in iCal, so it's the day after ends_on. */
    public static function event(string $uid, Carbon $start, Carbon $end, string $summary, string $location, string $description, Carbon $stamp): string
    {
        return implode("\r\n", array_map(self::fold(...), [
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$stamp->utc()->format('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$end->copy()->addDay()->format('Ymd'),
            'SUMMARY:'.self::esc($summary),
            'LOCATION:'.self::esc($location),
            'DESCRIPTION:'.self::esc($description),
            'END:VEVENT',
        ]));
    }

    public static function esc(string $v): string
    {
        // Escape per RFC 5545 TEXT rules; collapse any CR/LF (including a bare
        // \r from a pasted value) to the literal \n escape so it can't break the
        // line structure.
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $v
        );
    }

    /**
     * Fold a content line to <=75 octets per RFC 5545 §3.1, multibyte-safe
     * (never splits a UTF-8 sequence). Continuation lines begin with a space,
     * which counts toward the octet budget.
     */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $segments = [];
        $current = '';
        $limit = 75; // first line: 75 octets; continuations carry a leading space
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $segments[] = $current;
                $current = $char;
                $limit = 74;
            } else {
                $current .= $char;
            }
        }
        $segments[] = $current;

        return implode("\r\n ", $segments);
    }
}
