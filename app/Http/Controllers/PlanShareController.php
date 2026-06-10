<?php

namespace App\Http\Controllers;

use App\Models\SeasonPlan;
use App\Services\TierService;
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

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ThePiste//thepiste.org//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->esc("{$plan->fencer->name} fencing {$plan->season->name}"),
        ];

        foreach ($rows as $r) {
            $t = $r['tournament'];
            $lines = [...$lines,
                'BEGIN:VEVENT',
                "UID:plan-{$plan->id}-{$t->id}@thepiste.org",
                'DTSTAMP:'.$plan->updated_at->utc()->format('Ymd\THis\Z'),
                'DTSTART;VALUE=DATE:'.$t->starts_on->format('Ymd'),
                // iCal all-day DTEND is exclusive.
                'DTEND;VALUE=DATE:'.$t->ends_on->copy()->addDay()->format('Ymd'),
                'SUMMARY:'.$this->esc($t->name),
                'LOCATION:'.$this->esc($t->location()),
                'DESCRIPTION:'.$this->esc(strtoupper($r['tier']).($r['distance'] ? ' · '.round($r['distance']).' mi '.($r['driveable'] ? 'drive' : 'fly') : '').' · via thepiste.org'),
                'END:VEVENT',
            ];
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
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

    private function esc(string $v): string
    {
        return str_replace([
            '\\', ';', ',', "\n",
        ], [
            '\\\\', '\;', '\,', '\n',
        ], $v);
    }
}
