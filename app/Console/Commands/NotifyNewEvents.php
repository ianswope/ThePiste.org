<?php

namespace App\Console\Commands;

use App\Models\Tournament;
use App\Models\User;
use App\Notifications\NewEventsDigest;
use App\Services\TierService;
use Illuminate\Console\Command;

class NotifyNewEvents extends Command
{
    protected $signature = 'thepiste:notify-new-events
        {--dry-run : Report who would be emailed without sending or marking}';

    protected $description = 'Email users a digest of newly-cataloged upcoming events relevant to their fencers';

    /** Tiers worth interrupting someone's inbox for. */
    private const RELEVANT_TIERS = ['nac', 'home', 'priority', 'drive'];

    public function handle(TierService $tiers): int
    {
        $dry = (bool) $this->option('dry-run');

        $new = Tournament::whereNull('alerted_at')
            ->whereDate('starts_on', '>=', now())
            ->with('hostClub')
            ->get();

        if ($new->isEmpty()) {
            $this->info('No new events to alert.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach (User::whereHas('fencers')->with('fencers.homeClub')->get() as $user) {
            $groups = [];
            foreach ($user->fencers as $fencer) {
                $rows = $tiers->evaluate($fencer, $new)
                    ->filter(fn ($r) => in_array($r['tier'], self::RELEVANT_TIERS, true) || $r['goal_score'] > 0)
                    ->values();
                if ($rows->isNotEmpty()) {
                    $groups[] = ['fencer' => $fencer, 'rows' => $rows->all()];
                }
            }

            if ($groups === []) {
                continue;
            }

            $names = collect($groups)->flatMap(fn ($g) => array_column($g['rows'], 'tournament'))->pluck('name')->unique();
            if ($dry) {
                $this->line("  would email {$user->email}: ".$names->implode(' · '));
                $sent++;

                continue;
            }

            // One bad recipient must not abort the run and re-spam everyone
            // already emailed when the next run re-finds the same events.
            try {
                $user->notify(new NewEventsDigest($groups));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("  ! digest to {$user->email} failed: {$e->getMessage()}");
                logger()->error('New-events digest failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        // Mark the events considered so they never re-alert — unless every
        // send failed (a likely mail outage), in which case leave them for the
        // next run rather than silently burying them.
        if (! $dry && ! ($sent === 0 && $failed > 0)) {
            Tournament::whereIn('id', $new->pluck('id'))->update(['alerted_at' => now()]);
        }

        $this->info(sprintf('%s: %d new event(s), %d digest(s) %s%s.',
            $dry ? 'Dry run' : 'Done', $new->count(), $sent, $dry ? 'would be sent' : 'sent',
            $failed ? ", {$failed} failed" : ''));

        return self::SUCCESS;
    }
}
