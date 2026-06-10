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

    // add-goal form
    public string $goalType = '';

    public string $goalWeapon = '';

    public string $goalRating = 'B';

    public string $goalTarget = 'jo';

    public string $goalCategory = '';

    public ?int $goalEvents = 8;

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
        $this->goalWeapon = $this->fencer->weapon;
        // "In plan" means an active item — a skipped one is off the plan but kept
        // on record (its costs survive), so it shows here as available to re-add.
        $this->selected = $this->plan->items()->where('status', '!=', 'skipped')
            ->pluck('tournament_id')->map(fn ($id) => (int) $id)->all();
        $this->costs = $this->plan->items()->pluck('est_cost', 'tournament_id')
            ->map(fn ($c) => $c !== null ? (float) $c : null)->all();

        // First visit (an empty plan): seed the recommended anchors. Keyed off
        // item count, not $selected, so a plan where everything was skipped
        // isn't treated as new and re-seeded.
        if ($this->plan->items()->count() === 0) {
            $anchors = $this->rows->filter(fn ($r) => $r['non_negotiable'])
                ->map(fn ($r) => $r['tournament']->id)->values()->all();
            foreach ($anchors as $id) {
                $this->plan->items()->create(['tournament_id' => $id]);
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
        $item = $this->plan->items()->where('tournament_id', $tournamentId)->first();

        if (in_array($tournamentId, $this->selected, true)) {
            // Removing: keep the record (skipped) when money's been entered.
            $item?->removeFromPlan();
            $this->selected = array_values(array_diff($this->selected, [$tournamentId]));
        } else {
            // Adding: re-activate a previously-skipped item (restoring its costs)
            // rather than orphaning it and starting a duplicate from scratch.
            $item ? $item->update(['status' => 'planned']) : $this->plan->items()->create(['tournament_id' => $tournamentId]);
            $this->selected[] = $tournamentId;
        }
    }

    public function addGoal(): void
    {
        $this->validate([
            'goalType' => ['required', 'in:rating,qualify,standing,develop'],
            'goalWeapon' => ['nullable', 'in:foil,epee,sabre'],
            'goalRating' => ['required_if:goalType,rating', 'in:E,D,C,B,A'],
            'goalTarget' => ['required_if:goalType,qualify', 'in:'.implode(',', array_keys(config('fencing.qualify_targets')))],
            'goalCategory' => ['nullable', 'string', 'max:6'],
            'goalEvents' => ['required_if:goalType,develop', 'nullable', 'integer', 'between:1,60'],
        ]);

        $params = match ($this->goalType) {
            'rating' => ['target_rating' => $this->goalRating],
            'qualify' => ['target' => $this->goalTarget],
            'standing' => ['category' => $this->goalCategory ?: null],
            'develop' => ['target_events' => $this->goalEvents],
        };

        $weapon = $this->goalType === 'develop' ? null : ($this->goalWeapon ?: $this->fencer->weapon);

        // Same type + weapon replaces (one rating goal per weapon, etc.).
        $this->fencer->goals()->active()
            ->where('type', $this->goalType)
            ->where('weapon', $weapon)
            ->delete();

        $this->fencer->goals()->create([
            'type' => $this->goalType,
            'weapon' => $weapon,
            'params' => $params,
            'status' => 'active',
        ]);

        $this->goalType = '';
        unset($this->rows);
    }

    public function removeGoal(int $goalId): void
    {
        $this->fencer->goals()->whereKey($goalId)->delete();
        unset($this->rows);
    }

    public function updatedCosts($value, $key): void
    {
        $id = (int) $key;
        $item = $this->plan->items()->with('expenses')->where('tournament_id', $id)->first();

        // The builder's inline field is only a ballpark for events not yet
        // itemized. Once the budget page breaks an event into categories, that
        // page owns its cost — ignore stale edits here so the two never fight.
        if (! $item || $item->expenses->isNotEmpty()) {
            return;
        }

        $cost = is_numeric($value)
            ? min((float) config('fencing.max_money'), max(0, round((float) $value, 2)))
            : null;
        $item->update(['est_cost' => $cost]);
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

        // Plan items keyed by tournament, so the rows can show the itemized
        // total (and lock the inline field) once an event has expenses.
        $planItems = $this->plan->items()->with('expenses')->get()->keyBy('tournament_id');

        // Budget tile sums the events actually shown here (visible, selected,
        // not skipped) so it matches the count beside it — skipped events and
        // any now-ineligible plan items drop out, same rule as the budget page.
        // Filter by tournament_id rather than Collection::only(), whose Eloquent
        // override keys off the plan-item id, not the array key.
        $chosenIds = $chosen->map(fn ($r) => (int) $r['tournament']->id)->values()->all();
        $estCost = round(
            $planItems
                ->filter(fn ($i) => in_array((int) $i->tournament_id, $chosenIds, true) && $i->status !== 'skipped')
                ->sum(fn ($i) => $i->effectiveTotal())
        );

        return view('livewire.season-builder', [
            'sections' => $sections,
            'planItems' => $planItems,
            'tally' => [
                'count' => $chosen->count(),
                'nacs' => $chosen->where('is_nac', true)->count(),
                'drives' => $chosen->filter(fn ($r) => $r['driveable'])->count(),
                'flights' => $chosen->filter(fn ($r) => ! $r['driveable'] && $r['distance'] !== null)->count(),
                'est_cost' => $estCost,
            ],
            'selectedIds' => $sel,
            'clashIds' => $clashes,
            'goals' => $this->fencer->goals()->active()->orderBy('created_at')->get(),
            'goalTypes' => config('fencing.goal_types'),
            'qualifyTargets' => collect(config('fencing.qualify_targets'))->map(fn ($t) => $t['label']),
            'weaponsList' => $this->fencer->weapons->pluck('weapon')->all() ?: [$this->fencer->weapon],
            'categories' => $this->fencer->eligibleCategories(),
        ]);
    }
}
