<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListFencers extends Tool
{
    use ResolvesFencer;

    protected string $description = 'List the fencer profiles on this account: weapons with per-weapon ratings, age group, home club, region, season goal, and rating-ladder progress.';

    public function handle(Request $request): Response
    {
        $fencers = $request->user()->fencers()->with(['weapons', 'homeClub'])->get();

        return Response::json($fencers->map(fn ($f) => [
            'id' => $f->id,
            'name' => $f->name,
            'age_group' => $f->age_group,
            'gender' => $f->gender,
            'handedness' => $f->handedness,
            'weapons' => $f->weapons->map(fn ($w) => [
                'weapon' => $w->weapon, 'rating' => $w->rating, 'primary' => $w->is_primary,
            ])->values(),
            'home_club' => $f->homeClub?->name,
            'region' => $f->region(),
            'home_zip' => $f->home_zip,
            'drive_radius_miles' => $f->driveRadius(),
            'goals' => $f->activeGoals()->map->label()->values(),
            'rating_progress_to_goal' => $f->ratingProgress(),
        ])->values());
    }
}
