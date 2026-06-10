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
        $count = collect($this->groups)->sum(fn ($g) => count($g['rows']));

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
                $where = $t->state ? "{$t->city}, {$t->state}" : $t->city;
                $mail->line("- **{$t->name}** · {$t->starts_on->format('D M j')} · {$where} — {$row['note']}");
            }
        }

        return $mail
            ->action('Open the season builder', route('season.build'))
            ->line('Add the keepers to the plan; skip the rest and they stay out of your way.')
            ->salutation('— ThePiste');
    }
}
