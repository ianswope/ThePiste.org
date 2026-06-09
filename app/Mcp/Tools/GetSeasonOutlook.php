<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use App\Services\TierService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetSeasonOutlook extends Tool
{
    use ResolvesFencer;

    protected string $description = 'The personalized season outlook for a fencer: every eligible tournament scored into tiers (nac, home, priority, drive, fly, skip) with distance, eligible categories, weekend conflicts, and whether it is already in the plan. Use this to recommend a season.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'fencer' => $schema->string()->description('Fencer name or id (optional).'),
            'tier' => $schema->string()->enum(['nac', 'home', 'priority', 'drive', 'fly', 'skip'])
                ->description('Only return this tier (optional).'),
        ];
    }

    public function handle(Request $request, TierService $tiers): Response
    {
        $fencer = $this->fencer($request);
        $season = $this->activeSeason();
        $inPlan = $this->plan($fencer)->items()->pluck('tournament_id')->all();

        $rows = $tiers->evaluate($fencer, $season->tournaments()->with('hostClub')->get())
            ->reject(fn ($r) => $r['tier'] === 'ineligible');

        if ($tier = $request->get('tier')) {
            $rows = $rows->where('tier', $tier);
        }

        return Response::json($rows->map(fn ($r) => [
            'tournament_id' => $r['tournament']->id,
            'name' => $r['tournament']->name,
            'dates' => $r['tournament']->starts_on->toDateString().' to '.$r['tournament']->ends_on->toDateString(),
            'location' => trim("{$r['tournament']->city}, {$r['tournament']->state}", ', '),
            'region' => $r['tournament']->region,
            'tier' => $r['tier'],
            'non_negotiable' => $r['non_negotiable'],
            'distance_miles' => $r['distance'] !== null ? round($r['distance']) : null,
            'travel' => $r['distance'] === null ? null : ($r['driveable'] ? 'drive' : 'fly'),
            'eligible_categories' => $r['eligible'],
            'advances_goals' => array_map(fn ($a) => ['goal' => $a['label'], 'why' => $a['why']], $r['advances']),
            'conflicts_with' => $r['conflict_with'],
            'in_plan' => in_array($r['tournament']->id, $inPlan, true),
            'note' => $r['note'],
        ])->values());
    }
}
