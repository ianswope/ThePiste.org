<?php

namespace App\Http\Controllers;

use App\Models\SeasonPlan;
use App\Services\TierService;
use App\Support\Ics;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PlanShareController extends Controller
{
    /** Public, read-only season plan (unguessable slug). */
    public function show(string $slug, TierService $tiers): View
    {
        [$plan, $rows] = $this->resolve($slug, $tiers);

        return view('plan-share', [
            'plan' => $plan,
            'fencer' => $plan->fencer,
            'season' => $plan->season,
            'months' => $rows->groupBy(fn ($r) => $r['tournament']->monthLabel()),
            'tallies' => $this->tallies($rows),
        ]);
    }

    /** iCal feed of the plan — subscribe from Google/Apple Calendar. */
    public function ics(string $slug, TierService $tiers): Response
    {
        [$plan, $rows] = $this->resolve($slug, $tiers);

        $vevents = $rows->map(function ($r) use ($plan) {
            $t = $r['tournament'];
            $desc = strtoupper($r['tier']).($r['distance'] ? ' · '.round($r['distance']).' mi '.($r['driveable'] ? 'drive' : 'fly') : '').' · via thepiste.org';

            return Ics::event("plan-{$plan->id}-{$t->id}@thepiste.org", $t->starts_on, $t->ends_on, $t->name, $t->location(), $desc, $plan->updated_at);
        })->all();

        $body = Ics::calendar("{$plan->fencer->name} fencing {$plan->season->name}", $vevents);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="thepiste-plan.ics"',
        ]);
    }

    /** @return array{0: SeasonPlan, 1: Collection} */
    private function resolve(string $slug, TierService $tiers): array
    {
        $plan = SeasonPlan::where('share_slug', $slug)
            ->with(['fencer.homeClub', 'season'])
            ->firstOrFail();

        // Skipped events drop out of the shared plan entirely — both the
        // schedule and the cost tally — the same rule the budget page uses.
        $plan->setRelation('items', $plan->items()->with('expenses')->get());
        $items = $plan->countedItems()->keyBy('tournament_id');

        $rows = $tiers->evaluate($plan->fencer, $plan->season->tournaments()->with('hostClub')->get())
            ->filter(fn ($r) => $items->has($r['tournament']->id))
            ->map(function ($r) use ($items) {
                $r['est_cost'] = $items[$r['tournament']->id]->effectiveTotal() ?: null;

                return $r;
            })
            ->values();

        return [$plan, $rows];
    }

    private function tallies($rows): array
    {
        return [
            'events' => $rows->count(),
            'nacs' => $rows->where('is_nac', true)->count(),
            'drives' => $rows->filter(fn ($r) => $r['driveable'])->count(),
            'flights' => $rows->filter(fn ($r) => ! $r['driveable'] && $r['distance'] !== null)->count(),
            'est_cost' => round($rows->sum(fn ($r) => $r['est_cost'] ?? 0)),
        ];
    }
}
