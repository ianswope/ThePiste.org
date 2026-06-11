<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared skeleton for the per-account digests: greeting, a bold header per
 * fencer with its bullet list, action button, and the "ThePiste" sign-off.
 * Subclasses supply the subject, intro, how to pull and format each fencer's
 * rows, and the closing call to action.
 *
 * Queued: the scheduled commands enqueue these and return immediately. The
 * dispatching command stamps alerted_at/reminded_at on a successful *enqueue*,
 * so without retries a later delivery failure (Resend outage, monthly cap)
 * would bury those records silently. We retry with backoff and, if the retries
 * are exhausted, log loudly via failed() so the run can be recovered by hand. A
 * complete fix would track delivery per recipient instead of stamping up front.
 * $groups is [['fencer' => Fencer, <rows-key> => [...]], ...].
 */
abstract class FencerDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /** Retry a failed delivery a few times before it lands in failed_jobs. */
    public int $tries = 3;

    public function __construct(public array $groups) {}

    /** Space retries out so a brief mail-provider blip clears before we give up. */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * Last resort when every retry failed: the dispatching command has already
     * stamped these records as done, so record which fencers were affected to
     * make manual recovery (clear the stamp, re-run) possible.
     */
    public function failed(\Throwable $e): void
    {
        logger()->error('Digest delivery failed after retries', [
            'notification' => static::class,
            'fencers' => array_map(fn ($g) => $g['fencer']->name, $this->groups),
            'error' => $e->getMessage(),
        ]);
    }

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

        return $mail->salutation('ThePiste');
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
