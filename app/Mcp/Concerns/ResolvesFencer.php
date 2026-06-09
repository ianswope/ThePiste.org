<?php

namespace App\Mcp\Concerns;

use App\Models\Fencer;
use App\Models\Season;
use App\Models\SeasonPlan;
use Laravel\Mcp\Request;

trait ResolvesFencer
{
    /**
     * Resolve which of the user's fencers a tool call refers to. Accepts an
     * optional "fencer" argument (name or id); defaults to the only/first one.
     */
    protected function fencer(Request $request): Fencer
    {
        $fencers = $request->user()->fencers()->with(['weapons', 'homeClub'])->get();

        if ($fencers->isEmpty()) {
            abort(422, 'No fencer profile yet — create one at thepiste.org first.');
        }

        $needle = trim((string) $request->get('fencer', ''));
        if ($needle === '') {
            return $fencers->first();
        }

        $match = $fencers->first(fn ($f) => (string) $f->id === $needle
            || str_contains(mb_strtolower($f->name), mb_strtolower($needle)));

        if (! $match) {
            abort(422, "No fencer matching \"{$needle}\". Available: ".$fencers->pluck('name')->implode(', '));
        }

        return $match;
    }

    protected function activeSeason(): Season
    {
        return Season::where('is_active', true)->first() ?? Season::firstOrFail();
    }

    protected function plan(Fencer $fencer): SeasonPlan
    {
        return $fencer->seasonPlans()->firstOrCreate(['season_id' => $this->activeSeason()->id]);
    }
}
