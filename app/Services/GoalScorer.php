<?php

namespace App\Services;

use App\Models\Goal;
use App\Models\Tournament;
use Illuminate\Support\Collection;

/**
 * Scores a tournament against a fencer's active goals. Returns which goals
 * the event advances (with a human "why") and a numeric weight used to
 * sort recommendations and break same-weekend conflicts.
 *
 * Path-aware, not rules-complete: we label events that are ON a path
 * (rating-earning fields, qualification circuits, regional points), we
 * never claim a goal is achieved — that comes from logged results.
 */
class GoalScorer
{
    /**
     * @param  array  $ctx  row context: eligible[], in_region, driveable, distance, tier
     * @return list<array{goal_id: int, type: string, label: string, why: string, weight: float}>
     */
    public function advances(Collection $goals, Tournament $t, array $ctx): array
    {
        $out = [];

        foreach ($goals as $goal) {
            if ($hit = $this->scoreOne($goal, $t, $ctx)) {
                $out[] = [
                    'goal_id' => $goal->id,
                    'type' => $goal->type,
                    'label' => $goal->label(),
                    'why' => $hit['why'],
                    'weight' => $hit['weight'],
                ];
            }
        }

        return $out;
    }

    /** @return array{why: string, weight: float}|null */
    private function scoreOne(Goal $goal, Tournament $t, array $ctx): ?array
    {
        return match ($goal->type) {
            'rating' => $this->rating($goal, $t, $ctx),
            'qualify' => $this->qualify($goal, $t, $ctx),
            'standing' => $this->standing($goal, $t, $ctx),
            'develop' => $this->develop($goal, $t, $ctx),
            default => null,
        };
    }

    private function rating(Goal $goal, Tournament $t, array $ctx): ?array
    {
        // Club opens almost never rate the field a letter goal needs; only
        // official circuit/national events count as a credible rating path.
        if ($t->level === 'local') {
            return null;
        }

        $earning = array_values(array_intersect(
            $ctx['eligible'],
            config('fencing.rating_earning_categories')
        ));

        $target = $goal->param('target_rating');

        if ($t->is_nac && $earning) {
            return ['why' => 'NAC field with '.implode('/', $earning).": the strongest {$target}-earning strips of the season", 'weight' => 3.0];
        }
        if ($earning && in_array('ROC', $t->circuits ?? [], true)) {
            return ['why' => 'ROC with '.implode('/', $earning).": fields here regularly rate {$target}s", 'weight' => 2.5];
        }
        if ($earning) {
            return ['why' => implode('/', $earning)." contested; a {$target} is earnable if the field is strong", 'weight' => 1.5];
        }

        return null;
    }

    private function qualify(Goal $goal, Tournament $t, array $ctx): ?array
    {
        $cfg = config('fencing.qualify_targets.'.$goal->param('target'));
        if (! $cfg) {
            return null;
        }

        if (preg_match($cfg['championship_pattern'], $t->name)) {
            return ['why' => "This is the goal event: {$cfg['label']}", 'weight' => 3.0];
        }

        if (preg_match($cfg['qualifier_pattern'], $t->name)) {
            return ['why' => "Named qualifier on the {$cfg['label']} path", 'weight' => 2.5];
        }

        $onCircuit = array_intersect($cfg['path_circuits'], $t->circuits ?? []);
        $inCategory = empty($cfg['categories'])
            || array_intersect($cfg['categories'], $ctx['eligible']);

        if ($onCircuit && $inCategory) {
            return ['why' => implode('/', $onCircuit)." points count toward {$cfg['label']} qualification", 'weight' => 2.0];
        }

        return null;
    }

    private function standing(Goal $goal, Tournament $t, array $ctx): ?array
    {
        if (! $ctx['in_region'] || empty($t->circuits)) {
            return null;
        }

        $category = $goal->param('category');
        if ($category !== null && ! in_array($category, $ctx['eligible'], true)) {
            return null;
        }

        $what = $category ?: 'regional';
        $weight = 1.5 + (count($ctx['eligible']) >= 3 ? 0.5 : 0);

        return ['why' => ucfirst($t->region ?? 'regional')." circuit points toward {$what} standing, close to home", 'weight' => $weight];
    }

    private function develop(Goal $goal, Tournament $t, array $ctx): ?array
    {
        if ($t->level === 'local' && $ctx['driveable']) {
            return ['why' => 'Low-pressure local field, an easy drive: ideal mileage', 'weight' => 1.5];
        }
        if ($ctx['driveable'] && in_array($ctx['tier'], ['drive', 'priority', 'home'], true)) {
            $dist = $ctx['distance'] !== null ? round($ctx['distance']).' mi' : 'short';

            return ['why' => "Bouts without the airfare: {$dist} drive", 'weight' => 1.0];
        }

        return null;
    }
}
