<?php

namespace App\Livewire;

use App\Models\Fencer;
use App\Models\Result;
use App\Models\Season;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.builder')]
class ResultsTracker extends Component
{
    public Fencer $fencer;

    public Season $season;

    // add-result form
    public ?int $tournament_id = null;

    public string $event_name = '';

    public string $category = '';

    public string $weapon = '';

    public string $fenced_on = '';

    public ?int $place = null;

    public ?int $field_size = null;

    public string $rating_earned = '';

    public ?float $points = null;

    public string $notes = '';

    public function mount()
    {
        $fencers = auth()->user()->fencers()->with('weapons')->get();
        if ($fencers->isEmpty()) {
            return redirect()->route('fencers.create');
        }

        $this->fencer = $fencers->firstWhere('id', session('active_fencer_id')) ?? $fencers->first();
        $this->season = Season::where('is_active', true)->first() ?? Season::firstOrFail();
        $this->weapon = $this->fencer->weapon;
        $this->fenced_on = now()->toDateString();
    }

    public function save(): void
    {
        $data = $this->validate([
            'tournament_id' => ['nullable', 'exists:tournaments,id'],
            'event_name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'max:10'],
            'weapon' => ['required', 'in:foil,epee,sabre'],
            'fenced_on' => ['required', 'date'],
            'place' => ['required', 'integer', 'between:1,999'],
            'field_size' => ['nullable', 'integer', 'between:1,999'],
            'rating_earned' => ['nullable', 'string', 'max:4'],
            'points' => ['nullable', 'numeric', 'between:0,10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $result = $this->fencer->results()->create($data);
        $this->maybeUpgradeRating($result);

        $this->reset(['tournament_id', 'event_name', 'category', 'place', 'field_size', 'rating_earned', 'points', 'notes']);
        $this->fenced_on = now()->toDateString();
        $this->fencer->refresh();
    }

    public function delete(int $resultId): void
    {
        $this->fencer->results()->whereKey($resultId)->delete();
    }

    /**
     * A recorded earned rating better than the current one upgrades the
     * weapon's rating (and the fencer's headline rating when it's the
     * primary weapon). Earning a rating is permanent; deletes don't revert.
     */
    private function maybeUpgradeRating(Result $result): void
    {
        $earned = strtoupper(substr(trim((string) $result->rating_earned), 0, 1));
        if (! in_array($earned, Fencer::RATING_LADDER, true) || $earned === 'U') {
            return;
        }

        $row = $this->fencer->weapons->firstWhere('weapon', $result->weapon);
        if (! $row) {
            return;
        }

        $currentIdx = array_search(strtoupper(substr($row->rating, 0, 1)), Fencer::RATING_LADDER, true) ?: 0;
        $earnedIdx = array_search($earned, Fencer::RATING_LADDER, true);

        if ($earnedIdx > $currentIdx) {
            $row->update(['rating' => trim($result->rating_earned)]);
            if ($row->is_primary) {
                $this->fencer->update(['rating' => trim($result->rating_earned)]);
            }
            session()->flash('rating_upgraded', ucfirst($result->weapon)." rating updated to {$result->rating_earned}.");
        }
    }

    public function render()
    {
        $results = $this->fencer->results()
            ->with('tournament')
            ->orderByDesc('fenced_on')
            ->get();

        $seasonResults = $results->filter(
            fn ($r) => $r->fenced_on->between($this->season->starts_on, $this->season->ends_on)
        );

        return view('livewire.results-tracker', [
            'results' => $results,
            'stats' => [
                'events' => $seasonResults->count(),
                'wins' => $seasonResults->where('place', 1)->count(),
                'podiums' => $seasonResults->filter(fn ($r) => $r->isPodium())->count(),
                'top8' => $seasonResults->filter(fn ($r) => $r->place <= 8)->count(),
                'points' => round($seasonResults->sum(fn ($r) => $r->points ?? 0), 1),
            ],
            'tournaments' => $this->season->tournaments()->orderBy('starts_on')->get()
                ->mapWithKeys(fn ($t) => [$t->id => $t->starts_on->format('M j').' · '.$t->name]),
            'ladder' => Fencer::RATING_LADDER,
            'progress' => $this->fencer->ratingProgress(),
            'goals' => config('fencing.goals'),
        ]);
    }
}
