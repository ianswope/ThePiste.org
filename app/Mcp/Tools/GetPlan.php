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
        $costs = $plan->items()->pluck('est_cost', 'tournament_id');

        $rows = $tiers->evaluate($fencer, $season->tournaments()->with('hostClub')->get())
            ->filter(fn ($r) => $costs->has($r['tournament']->id))
            ->values();

        return Response::json([
            'fencer' => $fencer->name,
            'season' => $season->name,
            'share_url' => $plan->share_slug ? route('plan.share', $plan->share_slug) : null,
            'tallies' => [
                'events' => $rows->count(),
                'nacs' => $rows->where('is_nac', true)->count(),
                'drives' => $rows->filter(fn ($r) => $r['driveable'])->count(),
                'flights' => $rows->filter(fn ($r) => ! $r['driveable'] && $r['distance'] !== null)->count(),
                'est_cost' => round($costs->sum(fn ($c) => $c ?? 0)),
            ],
            'events' => $rows->map(fn ($r) => [
                'tournament_id' => $r['tournament']->id,
                'dates' => $r['tournament']->starts_on->toDateString(),
                'name' => $r['tournament']->name,
                'location' => trim("{$r['tournament']->city}, {$r['tournament']->state}", ', '),
                'tier' => $r['tier'],
                'travel' => $r['distance'] === null ? null : ($r['driveable'] ? 'drive' : 'fly'),
                'distance_miles' => $r['distance'] !== null ? round($r['distance']) : null,
                'conflicts_with' => $r['conflict_with'],
            ])->values(),
        ]);
    }
}
