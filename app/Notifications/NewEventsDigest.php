<?php

namespace App\Notifications;

use App\Models\Fencer;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One email per user after a catalog sync: the newly-added events that
 * actually matter to their fencer(s), as judged by TierService. Sent by
 * thepiste:notify-new-events; events are marked alerted_at so they never
 * appear in a digest twice.
 */
class NewEventsDigest extends Notification
{
    /** @param array<int, array{fencer: Fencer, rows: array}> $groups */
    public function __construct(public array $groups) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Count distinct tournaments, not per-fencer rows: a household with two
        // eligible fencers shouldn't read "4 new tournaments" for the same two.
        $count = collect($this->groups)
            ->flatMap(fn ($g) => array_column($g['rows'], 'tournament'))
            ->unique(fn ($t) => $t->id)
            ->count();

        $mail = (new MailMessage)
            ->subject($count === 1
                ? 'A new tournament fits your season'
                : "{$count} new tournaments fit your season")
            ->greeting('Hi '.($notifiable->name ?: 'there').',')
            ->line('The catalog just picked up '.($count === 1 ? 'an event' : 'some events').' worth a look:');

        foreach ($this->groups as $group) {
            $mail->line("**{$group['fencer']->name}**");
            foreach ($group['rows'] as $row) {
                $t = $row['tournament'];
                $mail->line("- **{$t->name}** · {$t->starts_on->format('D M j')} · {$t->location()} — {$row['note']}");
            }
        }

        return $mail
            ->action('Open the season builder', route('season.build'))
            ->line('Add the keepers to the plan; skip the rest and they stay out of your way.')
            ->salutation('— ThePiste');
    }
}
