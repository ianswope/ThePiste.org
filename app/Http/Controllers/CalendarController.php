<?php

namespace App\Http\Controllers;

use App\Models\Fencer;
use App\Models\Season;
use App\Services\TierService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /** Signed-in fencer's personalized season (route is auth-guarded). */
    public function index(TierService $tiers): View|RedirectResponse
    {
        $fencers = auth()->user()->fencers()->with('homeClub')->get();

        if ($fencers->isEmpty()) {
            return redirect()->route('fencers.create');
        }

        $fencer = $fencers->firstWhere('id', session('active_fencer_id')) ?? $fencers->first();

        return $this->render($tiers, $fencer, $fencers, isDemo: false);
    }

    /** Public sample season driven by the demo fencer. */
    public function demo(TierService $tiers): View
    {
        $fencer = Fencer::with('homeClub')->whereNull('user_id')->firstOrFail();

        return $this->render($tiers, $fencer, new EloquentCollection, isDemo: true);
    }

    private function render(TierService $tiers, Fencer $fencer, $fencers, bool $isDemo): View
    {
        $season = Season::where('is_active', true)->first() ?? Season::firstOrFail();

        $rows = $tiers->evaluate($fencer, $season->tournaments()->with('hostClub')->get())
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
