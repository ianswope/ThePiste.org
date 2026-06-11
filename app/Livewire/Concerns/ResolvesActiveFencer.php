<?php

namespace App\Livewire\Concerns;

use App\Models\Fencer;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;

trait ResolvesActiveFencer
{
    public Fencer $fencer;

    public Season $season;

    /**
     * Resolve the account's active fencer (the one chosen in the session, else
     * the first) and the active season into $this->fencer / $this->season.
     * Returns a redirect to the fencer-creation form when the account has no
     * fencers yet — the caller's mount() should return that value.
     */
    protected function resolveActiveFencer(array $with = []): ?RedirectResponse
    {
        $fencers = auth()->user()->fencers()->with($with)->get();
        if ($fencers->isEmpty()) {
            return redirect()->route('fencers.create');
        }

        $this->fencer = $fencers->firstWhere('id', session('active_fencer_id')) ?? $fencers->first();
        $this->season = Season::active();

        return null;
    }
}
