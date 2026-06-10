<?php

namespace App\Http\Controllers;

use App\Models\Fencer;
use App\Models\Season;
use App\Models\Tournament;
use App\Services\TierService;
use App\Support\Ics;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /** Single-event .ics download (catalog is public facts — no auth needed). */
    public function ics(Tournament $tournament): Response
    {
        $event = Ics::event(
            "event-{$tournament->id}@thepiste.org",
            $tournament->starts_on,
            $tournament->ends_on,
            $tournament->name,
            $tournament->location(),
            'via thepiste.org',
            $tournament->updated_at ?? $tournament->starts_on,
        );

        return response(Ics::calendar($tournament->name, [$event]), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($tournament->name).'.ics"',
        ]);
    }

    /** Signed-in fencer's personalized season (route is auth-guarded). */
    public function index(TierService $tiers): View|RedirectResponse
    {
        $fencers = auth()->user()->fencers()->with('homeClub')->get();

        if ($fencers->isEmpty()) {
            return redirect()->route('fencers.create');
        }

        $fencer = $fencers->firstWhere('id', session('active_fencer_id')) ?? $fencers->first();

        $items = $fencer->seasonPlans()->first()?->items()->get() ?? collect();
        $planIds = $items->pluck('tournament_id')->all();
        $planNotes = $items->filter(fn ($i) => filled($i->notes))->pluck('notes', 'tournament_id')->all();

        return $this->render($tiers, $fencer, $fencers, isDemo: false, planIds: $planIds, planNotes: $planNotes);
    }

    /** Public sample season driven by the demo fencer. */
    public function demo(TierService $tiers): View
    {
        $fencer = Fencer::with('homeClub')->whereNull('user_id')->firstOrFail();

        return $this->render($tiers, $fencer, new EloquentCollection, isDemo: true);
    }

    private function render(TierService $tiers, Fencer $fencer, $fencers, bool $isDemo, array $planIds = [], array $planNotes = []): View
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

        return view('calendar', compact('season', 'fencer', 'months', 'stats', 'fencers', 'isDemo', 'planIds', 'planNotes'));
    }
}
