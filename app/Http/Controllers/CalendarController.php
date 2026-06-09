<?php

namespace App\Http\Controllers;

use App\Models\Fencer;
use App\Models\Season;
use App\Services\TierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(TierService $tiers): View|RedirectResponse
    {
        $season = Season::where('is_active', true)->first() ?? Season::firstOrFail();

        $user = auth()->user();
        $fencers = collect();
        $isDemo = true;

        if ($user) {
            $fencers = $user->fencers()->with('homeClub')->get();
            if ($fencers->isEmpty()) {
                return redirect()->route('fencers.create');
            }
            $fencer = $fencers->firstWhere('id', session('active_fencer_id')) ?? $fencers->first();
            $isDemo = false;
        } else {
            // Logged-out preview runs off the demo fencer (the only one with no owner).
            $fencer = Fencer::with('homeClub')->whereNull('user_id')->firstOrFail();
        }

        $tournaments = $season->tournaments()->with('hostClub')->get();

        $rows = $tiers->evaluate($fencer, $tournaments)
            ->reject(fn ($r) => $r['tier'] === 'ineligible');

        $months = $rows->groupBy(fn ($r) => $r['tournament']->monthLabel());

        $stats = [
            'total' => $rows->count(),
            'nac' => $rows->where('tier', 'nac')->count(),
            'priority' => $rows->whereIn('tier', ['home', 'priority'])->count(),
            'drive' => $rows->where('tier', 'drive')->count(),
            'fly' => $rows->where('tier', 'fly')->count(),
            'nonneg' => $rows->where('non_negotiable', true)->count(),
        ];

        return view('calendar', compact('season', 'fencer', 'months', 'stats', 'fencers', 'isDemo'));
    }
}
