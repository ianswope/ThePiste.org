<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared skeleton for the per-account digests: greeting, a bold header per
 * fencer with its bullet list, action button, and the "— ThePiste" sign-off.
 * Subclasses supply the subject, intro, how to pull and format each fencer's
 * rows, and the closing call to action.
 *
 * Queued: the scheduled commands enqueue these and return immediately, and a
 * delivery failure retries (then lands in failed_jobs) instead of blocking the
 * run. $groups is [['fencer' => Fencer, <rows-key> => [...]], ...].
 */
abstract class FencerDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public array $groups) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hi '.($notifiable->name ?: 'there').',');

        foreach ($this->intro() as $line) {
            $mail->line($line);
        }

        foreach ($this->groups as $group) {
            $mail->line("**{$group['fencer']->name}**");
            foreach ($this->rowsOf($group) as $row) {
                $mail->line('- '.$this->formatRow($row));
            }
        }

        $this->callToAction($mail);

        return $mail->salutation('— ThePiste');
    }

    abstract protected function subject(): string;

    /** @return string[] lines shown before the per-fencer list */
    abstract protected function intro(): array;

    /** @return iterable<mixed> one fencer group's list entries */
    abstract protected function rowsOf(array $group): iterable;

    /** Render one list entry (the leading "- " is added by the skeleton). */
    abstract protected function formatRow($row): string;

    /** Append the action button and any closing line(s). */
    abstract protected function callToAction(MailMessage $mail): void;
}
