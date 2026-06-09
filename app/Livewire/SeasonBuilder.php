<?php

namespace App\Livewire;

use App\Models\Fencer;
use App\Models\Season;
use App\Models\SeasonPlan;
use App\Services\TierService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.builder')]
class SeasonBuilder extends Component
{
    public Fencer $fencer;

    public Season $season;

    public SeasonPlan $plan;

    /** @var int[] selected tournament ids */
    public array $selected = [];

    /** @var array<int, float|null> tournament id => estimated trip cost */
    public array $costs = [];

    public ?string $goal = null;

    public function mount()
    {
        $fencers = auth()->user()->fencers()->get();
        if ($fencers->isEmpty()) {
            return redirect()->route('fencers.create');
        }

        $this->fencer = $fencers->firstWhere('id', session('active_fencer_id')) ?? $fencers->first();
        $this->season = Season::where('is_active', true)->first() ?? Season::firstOrFail();
        $this->plan = $this->fencer->seasonPlans()->firstOrCreate(['season_id' => $this->season->id]);
        if (! $this->plan->share_slug) {
            $this->plan->update(['share_slug' => Str::random(24)]);
        }
        $this->goal = $this->fencer->goal;
        $this->selected = $this->plan->items()->pluck('tournament_id')->map(fn ($id) => (int) $id)->all();
        $this->costs = $this->plan->items()->pluck('est_cost', 'tournament_id')
            ->map(fn ($c) => $c !== null ? (float) $c : null)->all();

        // First visit: seed the plan with the recommended anchors.
        if (empty($this->selected)) {
            $anchors = $this->rows->filter(fn ($r) => $r['non_negotiable'])
                ->map(fn ($r) => $r['tournament']->id)->values()->all();
            foreach ($anchors as $id) {
                $this->plan->items()->firstOrCreate(['tournament_id' => $id]);
            }
            $this->selected = $anchors;
        }
    }

    #[Computed]
    public function rows(): Collection
    {
        return app(TierService::class)
            ->evaluate($this->fencer->load('homeClub'), $this->season->tournaments()->with('hostClub')->get())
            ->reject(fn ($r) => $r['tier'] === 'ineligible')
            ->values();
    }

    public function toggle(int $tournamentId): void
    {
        if (in_array($tournamentId, $this->selected, true)) {
            $this->plan->items()->where('tournament_id', $tournamentId)->delete();
            $this->selected = array_values(array_diff($this->selected, [$tournamentId]));
        } else {
            $this->plan->items()->firstOrCreate(['tournament_id' => $tournamentId]);
            $this->selected[] = $tournamentId;
        }
    }

    public function updatedGoal(?string $value): void
    {
        $this->fencer->update(['goal' => $value ?: null]);
    }

    public function updatedCosts($value, $key): void
    {
        $id = (int) $key;
        $cost = is_numeric($value) ? max(0, (float) $value) : null;
        $this->plan->items()->where('tournament_id', $id)->update(['est_cost' => $cost]);
        $this->costs[$id] = $cost;
    }

    public function render()
    {
        $rows = $this->rows;
        $sel = $this->selected;

        $sections = [
            'anchors' => $rows->filter(fn ($r) => $r['non_negotiable']),
            'value' => $rows->filter(fn ($r) => ! $r['non_negotiable'] && in_array($r['tier'], ['priority', 'drive'], true)),
            'optional' => $rows->filter(fn ($r) => ! $r['non_negotiable'] && $r['tier'] === 'fly'),
            'rest' => $rows->filter(fn ($r) => ! $r['non_negotiable'] && $r['tier'] === 'skip'),
        ];

        $chosen = $rows->filter(fn ($r) => in_array($r['tournament']->id, $sel, true));

        // Both sides of a same-weekend conflict in the plan = a clash to resolve.
        $chosenNames = $chosen->map(fn ($r) => $r['tournament']->name)->all();
        $clashes = $chosen
            ->filter(fn ($r) => $r['conflict_with'] && in_array($r['conflict_with'], $chosenNames, true))
            ->map(fn ($r) => $r['tournament']->id)
            ->values()
            ->all();

        return view('livewire.season-builder', [
            'sections' => $sections,
            'tally' => [
                'count' => $chosen->count(),
                'nacs' => $chosen->where('is_nac', true)->count(),
                'drives' => $chosen->filter(fn ($r) => $r['driveable'])->count(),
                'flights' => $chosen->filter(fn ($r) => ! $r['driveable'] && $r['distance'] !== null)->count(),
                'est_cost' => round($chosen->sum(fn ($r) => $this->costs[$r['tournament']->id] ?? 0)),
            ],
            'selectedIds' => $sel,
            'clashIds' => $clashes,
            'goals' => config('fencing.goals'),
        ]);
    }
}
