<?php

namespace App\Console\Commands;

use App\Models\PlanItem;
use App\Models\Result;
use App\Models\Tournament;
use App\Services\TournamentImporter;
use Illuminate\Console\Command;

class DedupeTournaments extends Command
{
    protected $signature = 'thepiste:dedupe-tournaments {--dry-run : Report merges without writing}';

    protected $description = 'Merge synced duplicates of curated tournaments (same date/state, matching name) into the curated row';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $merged = 0;

        foreach (Tournament::whereNotNull('external_id')->orderBy('id')->get() as $synced) {
            $curated = Tournament::whereNull('external_id')
                ->whereDate('starts_on', $synced->starts_on)
                ->where('state', $synced->state)
                ->whereKeyNot($synced->id)
                ->get()
                ->first(fn (Tournament $c) => TournamentImporter::namesLookAlike($c->name, $synced->name));

            if (! $curated) {
                continue;
            }

            $this->line(($dry ? '[DRY] ' : '')."merge: \"{$synced->name}\" (#{$synced->id}, synced) -> \"{$curated->name}\" (#{$curated->id}, curated)");
            $merged++;

            if ($dry) {
                continue;
            }

            $identity = [
                'external_id' => $synced->external_id,
                'source_url' => $synced->source_url ?? $curated->source_url,
                'last_seen_at' => $synced->last_seen_at,
            ];

            // Re-point plan items (drop ones that would collide with an
            // existing selection of the curated row) and results.
            foreach (PlanItem::where('tournament_id', $synced->id)->get() as $item) {
                $already = PlanItem::where('season_plan_id', $item->season_plan_id)
                    ->where('tournament_id', $curated->id)
                    ->exists();
                $already ? $item->delete() : $item->update(['tournament_id' => $curated->id]);
            }
            Result::where('tournament_id', $synced->id)->update(['tournament_id' => $curated->id]);

            // Delete the dupe first (external_id is unique), then the curated
            // row absorbs the source identity so future syncs update in place.
            $synced->delete();
            $curated->update($identity);
        }

        $this->info(($dry ? 'Would merge' : 'Merged')." {$merged} duplicate(s).");

        return self::SUCCESS;
    }
}
