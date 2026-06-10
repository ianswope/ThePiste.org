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
            } else {
                $user->notify(new NewEventsDigest($groups));
            }
            $sent++;
        }

        if (! $dry) {
            Tournament::whereIn('id', $new->pluck('id'))->update(['alerted_at' => now()]);
        }

        $this->info(sprintf('%s: %d new event(s), %d digest(s) %s.',
            $dry ? 'Dry run' : 'Done', $new->count(), $sent, $dry ? 'would be sent' : 'sent'));

        return self::SUCCESS;
    }
}
