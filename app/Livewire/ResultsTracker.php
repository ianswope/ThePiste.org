<?php

namespace App\Livewire;

use App\Livewire\Concerns\ResolvesActiveFencer;
use App\Models\Fencer;
use App\Services\ResultRecorder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.builder')]
class ResultsTracker extends Component
{
    use ResolvesActiveFencer;

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
        if ($redirect = $this->resolveActiveFencer(['weapons'])) {
            return $redirect;
        }

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

    /** The season's tournaments as id => "M j · Name", for the result-log dropdown.
     *  Memoized for the request: the catalog doesn't change within a session. */
    #[Computed]
    public function tournaments(): array
    {
        return $this->season->tournaments()->orderBy('starts_on')->get()
            ->mapWithKeys(fn ($t) => [$t->id => $t->starts_on->format('M j').' · '.$t->name])
            ->all();
    }

    /** Per-goal progress derived from logged results. Path-aware, never claims "qualified". */
    private function goalCards($seasonResults): array
    {
        return $this->fencer->activeGoals()->sortBy('created_at')->map(function ($g) use ($seasonResults) {
            $card = ['id' => $g->id, 'type' => $g->type, 'label' => $g->label()];

            return $card + match ($g->type) {
                'rating' => [
                    'progress' => $this->fencer->ratingProgress(),
                    'detail' => 'Currently '.$this->fencer->rating.' · target '.$g->param('target_rating'),
                ],
                'qualify' => [
                    'progress' => null,
                    'detail' => ($n = $seasonResults->filter(function ($r) use ($g) {
                        $circuits = $r->tournament?->circuits ?? [];

                        return (bool) array_intersect(
                            config('fencing.qualify_targets.'.$g->param('target').'.path_circuits', []),
                            $circuits
                        );
                    })->count()).' path event'.($n === 1 ? '' : 's').' fenced this season',
                ],
                'standing' => [
                    'progress' => null,
                    'detail' => round($seasonResults
                        ->filter(fn ($r) => $g->param('category') === null || $r->category === $g->param('category'))
                        ->sum(fn ($r) => $r->points ?? 0), 1).' points logged'
                        .($g->param('category') ? ' in '.$g->param('category') : ''),
                ],
                'develop' => [
                    'progress' => min(1.0, $seasonResults->count() / max(1, (int) $g->param('target_events'))),
                    'detail' => $seasonResults->count().' of '.$g->param('target_events').' events fenced',
                ],
            };
        })->all();
    }

    public function render()
    {
        // Load goals once so goalCards, targetRating, and ratingProgress below
        // all read the same in-memory relation instead of re-querying per call.
        $this->fencer->loadMissing('goals');

        $results = $this->fencer->results()
            ->with('tournament')
            ->orderByDesc('fenced_on')
            ->get();

        $seasonResults = $results->filter(
            fn ($r) => $r->fenced_on->between($this->season->starts_on, $this->season->ends_on)
        );

        return view('livewire.results-tracker', [
            'goalCards' => $this->goalCards($seasonResults),
            'results' => $results,
            'stats' => [
                'events' => $seasonResults->count(),
                'wins' => $seasonResults->where('place', 1)->count(),
                'podiums' => $seasonResults->filter(fn ($r) => $r->isPodium())->count(),
                'top8' => $seasonResults->filter(fn ($r) => $r->place <= 8)->count(),
                'points' => round($seasonResults->sum(fn ($r) => $r->points ?? 0), 1),
            ],
            'tournaments' => $this->tournaments,
            'ladder' => Fencer::RATING_LADDER,
            'progress' => $this->fencer->ratingProgress(),
        ]);
    }
}
