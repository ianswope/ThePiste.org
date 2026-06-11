<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use App\Services\TierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetPlan extends Tool
{
    use ResolvesFencer;

    protected string $description = 'The fencer\'s current season plan: the selected tournaments in date order with travel mode, plus tallies (events, NACs, drives, flights).';

    public function schema(JsonSchema $schema): array
    {
        return [
            'fencer' => $schema->string()->description('Fencer name or id (optional).'),
        ];
    }

    public function handle(Request $request, TierService $tiers): Response
    {
        $fencer = $this->fencer($request);
        $season = $this->activeSeason();
        $plan = $this->plan($fencer);
        // Skipped events drop out of the plan the MCP reports, matching the app.
        $plan->setRelation('items', $plan->items()->with('expenses')->get());
        $items = $plan->countedItems()->keyBy('tournament_id');

        $rows = $tiers->evaluate($fencer, $season->tournaments()->with('hostClub')->get())
            ->filter(fn ($r) => $items->has($r['tournament']->id))
            ->values();

        $categories = array_keys(config('fencing.expense_categories'));

        return Response::json([
            'fencer' => $fencer->name,
            'season' => $season->name,
            'share_url' => $plan->share_slug ? route('plan.share', $plan->share_slug) : null,
            'ics_url' => $plan->share_slug ? route('plan.ics', $plan->share_slug) : null,
            'tallies' => [
                'events' => $rows->count(),
                'nacs' => $rows->where('is_nac', true)->count(),
                'drives' => $rows->filter(fn ($r) => $r['driveable'])->count(),
                'flights' => $rows->filter(fn ($r) => ! $r['driveable'] && $r['distance'] !== null)->count(),
                'est_cost' => round($plan->projectedTotal()),
                'budget' => $plan->budget !== null ? (float) $plan->budget : null,
            ],
            'events' => $rows->map(function ($r) use ($items, $categories) {
                $item = $items->get($r['tournament']->id);

                return [
                    'tournament_id' => $r['tournament']->id,
                    'dates' => $r['tournament']->starts_on->toDateString().' to '.$r['tournament']->ends_on->toDateString(),
                    'name' => $r['tournament']->name,
                    'location' => $r['tournament']->location(),
                    'tier' => $r['tier'],
                    'travel' => $r['distance'] === null ? null : ($r['driveable'] ? 'drive' : 'fly'),
                    'distance_miles' => $r['distance'] !== null ? round($r['distance']) : null,
                    'conflicts_with' => $r['conflict_with'],
                    // Prep + budget so an agent can read the same state the
                    // prep and budget pages show, not just the calendar facts.
                    'status' => $item?->status,
                    'paid' => $item?->paid,
                    'prep' => $item ? [
                        'travel' => $item->travel_status,
                        'lodging' => $item->lodging_status,
                        'coaching' => $item->coaching_status,
                        'done' => $item->prepProgress()['done'],
                        'total' => $item->prepProgress()['total'],
                    ] : null,
                    'cost' => $item ? round($item->effectiveTotal(), 2) : null,
                    'expenses' => $item
                        ? collect($categories)->mapWithKeys(fn ($c) => [$c => $item->categoryAmount($c)])->filter(fn ($v) => $v !== null)->all()
                        : [],
                    'notes' => $item?->notes,
                ];
            })->values(),
        ]);
    }
}
