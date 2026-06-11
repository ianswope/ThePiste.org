<?php

namespace App\Notifications;

use App\Models\Fencer;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * One email per user after a catalog sync: the newly-added events that
 * actually matter to their fencer(s), as judged by TierService. Sent by
 * thepiste:notify-new-events; events are marked alerted_at so they never
 * appear in a digest twice.
 */
class NewEventsDigest extends FencerDigest
{
    /** @param array<int, array{fencer: Fencer, rows: array}> $groups */
    public function __construct(public array $groups)
    {
        parent::__construct($groups);
    }

    /** Distinct tournaments, not per-fencer rows: two fencers, one event reads "a", not "2". */
    private function eventCount(): int
    {
        return collect($this->groups)
            ->flatMap(fn ($g) => array_column($g['rows'], 'tournament'))
            ->unique(fn ($t) => $t->id)
            ->count();
    }

    protected function subject(): string
    {
        return $this->eventCount() === 1
            ? 'A new tournament fits your season'
            : "{$this->eventCount()} new tournaments fit your season";
    }

    protected function intro(): array
    {
        return ['The catalog just picked up '.($this->eventCount() === 1 ? 'an event' : 'some events').' worth a look:'];
    }

    protected function rowsOf(array $group): iterable
    {
        return $group['rows'];
    }

    protected function formatRow($row): string
    {
        $t = $row['tournament'];

        return "**{$t->name}** · {$t->starts_on->format('D M j')} · {$t->location()} · {$row['note']}";
    }

    protected function callToAction(MailMessage $mail): void
    {
        $mail->action('Open the season builder', route('season.build'))
            ->line('Add the keepers to the plan; skip the rest and they stay out of your way.');
    }
}
