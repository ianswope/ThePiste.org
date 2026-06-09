<?php

namespace App\Http\Controllers;

use App\Models\Fencer;
use App\Models\Season;
use App\Services\TierService;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(TierService $tiers): View
    {
        $season = Season::where('is_active', true)->first() ?? Season::firstOrFail();

        // v1: drive the calendar from the demo fencer. Phase 2 swaps this for the
        // signed-in user's selected fencer profile.
        $fencer = Fencer::with('homeClub')->firstOrFail();

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

        return view('calendar', compact('season', 'fencer', 'months', 'stats'));
    }
}
