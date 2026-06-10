<?php

namespace App\Notifications;

use App\Models\Fencer;
use App\Models\PlanItem;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Nudge for planned events entering their registration window. AskFRED gives
 * us no true deadlines, so the lead times in fencing.reminder_lead_days encode
 * the norms (nationals close ~6 weeks out, the rest 1-2 weeks). Sent by
 * thepiste:send-registration-reminders; items are marked reminded_at so each
 * plan item is nudged once.
 */
class RegistrationReminderDigest extends Notification
{
    /** @param array<int, array{fencer: Fencer, items: PlanItem[]}> $groups */
    public function __construct(public array $groups) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $count = collect($this->groups)->sum(fn ($g) => count($g['items']));

        $mail = (new MailMessage)
            ->subject($count === 1
                ? 'Time to register: an event on your plan is coming up'
                : "Time to register: {$count} events on your plan are coming up")
            ->greeting('Hi '.($notifiable->name ?: 'there').',')
            ->line('These planned events are inside their usual registration window. If you haven\'t signed up yet, now is the time — entries often close well before the first strip call.');

        foreach ($this->groups as $group) {
            $mail->line("**{$group['fencer']->name}**");
            foreach ($group['items'] as $item) {
                $t = $item->tournament;
                $days = (int) now()->startOfDay()->diffInDays($t->starts_on, false);
                $when = $t->starts_on->format('D M j').' ('.($days === 0 ? 'today' : "in {$days} day".($days === 1 ? '' : 's')).')';
                $register = $t->source_url ? " — [register on AskFRED]({$t->source_url})" : '';
                $mail->line("- **{$t->name}** · {$when} · {$t->city}, {$t->state}{$register}");
            }
        }

        return $mail
            ->action('Review your season plan', route('calendar'))
            ->line('Heads up: national events (NACs, JOs, Championships) close registration about six weeks out on the USA Fencing site.')
            ->salutation('— ThePiste');
    }
}
