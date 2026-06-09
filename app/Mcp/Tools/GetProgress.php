<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetProgress extends Tool
{
    use ResolvesFencer;

    protected string $description = 'Season progress for a fencer: goal, rating-ladder position, and season stats (events fenced, wins, podiums, top-8s, points) with the logged results.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'fencer' => $schema->string()->description('Fencer name or id (optional).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $fencer = $this->fencer($request);
        $season = $this->activeSeason();

        $results = $fencer->results()->with('tournament')->orderByDesc('fenced_on')->get();
        $seasonResults = $results->filter(fn ($r) => $r->fenced_on->between($season->starts_on, $season->ends_on));

        return Response::json([
            'fencer' => $fencer->name,
            'goal' => $fencer->goal ? (config('fencing.goals')[$fencer->goal] ?? $fencer->goal) : null,
            'current_rating' => $fencer->rating,
            'target_rating' => $fencer->targetRating(),
            'rating_ladder_progress' => $fencer->ratingProgress(),
            'season_stats' => [
                'events' => $seasonResults->count(),
                'wins' => $seasonResults->where('place', 1)->count(),
                'podiums' => $seasonResults->filter(fn ($r) => $r->isPodium())->count(),
                'top_8' => $seasonResults->filter(fn ($r) => $r->place <= 8)->count(),
                'points' => round($seasonResults->sum(fn ($r) => $r->points ?? 0), 1),
            ],
            'results' => $results->take(25)->map(fn ($r) => [
                'date' => $r->fenced_on->toDateString(),
                'event' => $r->event_name,
                'category' => $r->category,
                'weapon' => $r->weapon,
                'place' => $r->place,
                'field_size' => $r->field_size,
                'rating_earned' => $r->rating_earned,
                'points' => $r->points,
                'tournament' => $r->tournament?->name,
            ])->values(),
        ]);
    }
}
