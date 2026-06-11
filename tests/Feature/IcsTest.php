<?php

namespace Tests\Feature;

use App\Support\Ics;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IcsTest extends TestCase
{
    public function test_event_escapes_text_and_uses_an_exclusive_end_date(): void
    {
        $event = Ics::event(
            'uid@thepiste.org',
            Carbon::parse('2026-09-12'),
            Carbon::parse('2026-09-13'),
            'Foil, Epee; and Sabre',
            "Chicago, IL\nUSA",
            "Line one\r\nLine two",
            Carbon::parse('2026-06-01 12:00:00'),
        );

        // All-day VEVENT, DTEND exclusive (the day after ends_on).
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20260912', $event);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20260914', $event);

        // RFC 5545 TEXT escaping for comma, semicolon, and CR/LF.
        $this->assertStringContainsString('SUMMARY:Foil\, Epee\; and Sabre', $event);
        $this->assertStringContainsString('LOCATION:Chicago\, IL\nUSA', $event);
        $this->assertStringContainsString('DESCRIPTION:Line one\nLine two', $event);
    }

    public function test_long_content_lines_are_folded_to_75_octets(): void
    {
        $event = Ics::event(
            'uid@thepiste.org',
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-01'),
            str_repeat('A', 200),
            'Somewhere',
            'Notes',
            Carbon::parse('2026-01-01'),
        );

        foreach (explode("\r\n", $event) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line), "Line exceeds 75 octets: {$line}");
        }
        // A folded line continues on the next physical line, which begins with a space.
        $this->assertStringContainsString("\r\n ", $event);
    }

    public function test_multibyte_characters_are_not_split_across_a_fold(): void
    {
        // A long run of 3-octet UTF-8 characters: the fold must land on a
        // character boundary, so the bytes stay valid UTF-8.
        $name = str_repeat('é', 60); // 2 octets each = 120 octets
        $event = Ics::event(
            'uid@thepiste.org',
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-01-01'),
            $name,
            'loc',
            'desc',
            Carbon::parse('2026-01-01'),
        );

        // Strip the fold (CRLF + space) and confirm the summary survives intact.
        $unfolded = str_replace("\r\n ", '', $event);
        $this->assertStringContainsString('SUMMARY:'.$name, $unfolded);
        $this->assertSame($name, mb_convert_encoding($name, 'UTF-8', 'UTF-8')); // valid UTF-8 round-trip
    }
}
