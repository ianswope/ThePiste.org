<?php

namespace App\Services;

use App\Models\Fencer;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Turns the objective tournament catalog into a personalized, prioritized plan
 * for a given fencer: eligibility, drive-vs-fly distance, strategic tier, and
 * same-weekend conflict flags. This is what makes the calendar "a real app"
 * instead of a static page — every fencer sees a different calendar.
 */
class TierService
{
    public function __construct(private GoalScorer $goalScorer) {}

    /**
     * @return Collection<int, array> one row per tournament, decorated with tier data,
     *                                ordered by date. Ineligible events are included
     *                                with tier "ineligible" so the UI can hide them.
     */
    public function evaluate(Fencer $fencer, Collection $tournaments): Collection
    {
        $eligibleCats = $fencer->eligibleCategories();
        $fencerRegion = $fencer->region();
        $radius = $fencer->driveRadius();
        $threshold = config('fencing.multi_category_threshold');
        $goals = $fencer->activeGoals();

        $rows = $tournaments
            // FIE events are opt-in per fencer.
            ->reject(fn (Tournament $t) => $t->isInternational() && ! $fencer->include_fie)
            ->sortBy(fn (Tournament $t) => $t->starts_on->timestamp)
            ->values()
            ->map(function (Tournament $t) use ($fencer, $eligibleCats, $fencerRegion, $radius, $threshold, $goals) {
                $eligible = array_values(array_intersect($t->contested_events ?? [], $eligibleCats));
                $eligibleCount = count($eligible);
                $distance = $this->distanceMiles($fencer->home_lat, $fencer->home_lng, $t->lat, $t->lng);
                $driveable = $distance !== null && $distance <= $radius;
                $inRegion = $fencerRegion !== null && $t->region === $fencerRegion;
                $isHome = $t->host_club_id !== null && $t->host_club_id === $fencer->home_club_id;

                $tier = $this->baseTier($t, $eligibleCount, $driveable, $inRegion, $isHome, $threshold);

                $advances = $tier === 'ineligible' ? [] : $this->goalScorer->advances($goals, $t, [
                    'eligible' => $eligible,
                    'in_region' => $inRegion,
                    'driveable' => $driveable,
                    'distance' => $distance,
                    'tier' => $tier,
                ]);

                return [
                    'tournament' => $t,
                    'eligible' => $eligible,
                    'eligible_count' => $eligibleCount,
                    'distance' => $distance,
                    'driveable' => $driveable,
                    'in_region' => $inRegion,
                    'is_home' => $isHome,
                    'is_nac' => (bool) $t->is_nac,
                    'tier' => $tier,
                    'non_negotiable' => in_array($tier, ['nac', 'home'], true)
                        || ($tier === 'priority' && $eligibleCount >= $threshold),
                    'advances' => $advances,
                    'goal_score' => round(array_sum(array_column($advances, 'weight')), 2),
                    'conflict_with' => null,
                    'note' => $t->curated_note ?: $this->generatedNote($t, $eligible, $distance, $driveable, $inRegion, $tier),
                ];
            });

        return $this->flagConflicts($rows);
    }

    private function baseTier(Tournament $t, int $eligibleCount, bool $driveable, bool $inRegion, bool $isHome, int $threshold): string
    {
        if ($eligibleCount === 0) {
            return 'ineligible';
        }
        // FIE / international events are always fly trips, never auto-anchors.
        if ($t->isInternational()) {
            return 'fly';
        }
        if ($t->is_nac) {
            return 'nac';
        }
        if ($isHome) {
            return 'home';
        }
        // Club-level events never outrank circuit events: a nearby one is a
        // training-mileage drive, a far one is a pass — never priority or fly.
        if ($t->level === 'local') {
            return $driveable && $inRegion ? 'drive' : 'skip';
        }
        if ($inRegion && $eligibleCount >= $threshold) {
            return 'priority';
        }
        if ($driveable && $inRegion) {
            return 'drive';
        }
        if (! $driveable && $eligibleCount >= $threshold) {
            return 'fly';
        }

        return 'skip';
    }

    /**
     * Demote lower-priority events that share a weekend with a higher-priority one,
     * tagging them with the conflict so the UI can explain the trade-off.
     */
    private function flagConflicts(Collection $rows): Collection
    {
        $ranks = config('fencing.tier_rank');

        $byWeekend = $rows
            ->filter(fn ($r) => $r['tier'] !== 'ineligible')
            ->groupBy(fn ($r) => $r['tournament']->starts_on->copy()->startOfWeek()->toDateString());

        foreach ($byWeekend as $group) {
            if ($group->count() < 2) {
                continue;
            }

            // Tier wins the weekend; goal contribution breaks ties within a tier.
            $winner = $group->sortByDesc(fn ($r) => (($ranks[$r['tier']] ?? 0) * 1000) + $r['goal_score'])->first();

            foreach ($group as $row) {
                if ($row['tournament']->id === $winner['tournament']->id) {
                    continue;
                }

                $idx = $rows->search(fn ($r) => $r['tournament']->id === $row['tournament']->id);
                $updated = $rows[$idx];
                $updated['conflict_with'] = $winner['tournament']->name;
                $rows[$idx] = $updated;
            }
        }

        return $rows;
    }

    private function generatedNote(Tournament $t, array $eligible, ?float $distance, bool $driveable, bool $inRegion, string $tier): string
    {
        $bits = [];
        if ($distance !== null) {
            $bits[] = round($distance).' mi '.($driveable ? 'drive' : 'flight');
        }
        if ($t->region) {
            $bits[] = $inRegion ? "{$t->region} (home region) points" : "{$t->region} points";
        }
        if ($n = count($eligible)) {
            $bits[] = $n.' eligible '.($n === 1 ? 'category' : 'categories').' ('.implode(', ', $eligible).')';
        }

        return ucfirst(implode(' · ', $bits)).'.';
    }

    /** Great-circle distance in miles, or null if either point is unknown. */
    private function distanceMiles(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }

        $r = 3958.8; // earth radius, miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
