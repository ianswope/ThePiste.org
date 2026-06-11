<?php

namespace App\Console\Commands;

use App\Console\Concerns\SendsDigests;
use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\NewEventsDigest;
use App\Services\TierService;
use Illuminate\Console\Command;

class NotifyNewEvents extends Command
{
    use SendsDigests;

    protected $signature = 'thepiste:notify-new-events
        {--dry-run : Report who would be emailed without sending or marking}';

    protected $description = 'Email users a digest of newly-cataloged upcoming events relevant to their fencers';

    /** Tiers worth interrupting someone's inbox for. */
    private const RELEVANT_TIERS = ['nac', 'home', 'priority', 'drive'];

    public function handle(TierService $tiers): int
    {
        $dry = (bool) $this->option('dry-run');

        // Scope to the active season: the digest links to the season builder,
        // which only shows that season — alerting on a future season's freshly
        // imported events would point at a builder that doesn't list them.
        $season = Season::active();

        $new = Tournament::where('season_id', $season->id)
            ->whereNull('alerted_at')
            ->whereDate('starts_on', '>=', now())
            ->with('hostClub')
            ->get();

        if ($new->isEmpty()) {
            $this->info('No new events to alert.');

            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        // Eager-load goals (used per fencer by TierService) and the home club so
        // evaluating relevance for every user is a couple of queries, not N.
        $users = User::whereHas('fencers')
            ->with(['fencers.homeClub', 'fencers.goals'])
            ->get();
        foreach ($users as $user) {
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

            $this->notifyOrLog($user, new NewEventsDigest($groups)) ? $sent++ : $failed++;
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
