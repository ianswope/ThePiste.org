<?php

namespace App\Livewire;

use App\Models\Fencer;
use App\Models\Result;
use App\Models\Season;
use App\Services\ResultRecorder;
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

        $outcome = app(ResultRecorder::class)->record($this->fencer, $data);
        if ($outcome['rating_upgraded']) {
            session()->flash('rating_upgraded', $outcome['rating_upgraded']);
        }

        $this->reset(['tournament_id', 'event_name', 'category', 'place', 'field_size', 'rating_earned', 'points', 'notes']);
        $this->fenced_on = now()->toDateString();
        $this->fencer->refresh();
    }

    public function delete(int $resultId): void
    {
        $this->fencer->results()->whereKey($resultId)->delete();
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
