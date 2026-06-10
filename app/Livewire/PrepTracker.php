<?php

namespace App\Livewire;

use App\Livewire\Concerns\ResolvesActiveFencer;
use App\Models\PlanItem;
use App\Models\SeasonPlan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Per-event readiness: for each planned event, a checklist from registration
 * through fees, travel, lodging, and coaching, plus add-to-calendar links.
 * Registration and fees reuse the plan item's status/paid; the rest live in
 * the prep columns. Skipped events drop off (they're not being attended).
 */
#[Layout('layouts.builder')]
class PrepTracker extends Component
{
    use ResolvesActiveFencer;

    public SeasonPlan $plan;

    /** field => allowed values, for the select-driven updates. */
    private const FIELDS = [
        'paid' => PlanItem::PAID_STATES,
        'travel_status' => PlanItem::LOGISTIC_STATES,
        'lodging_status' => PlanItem::LOGISTIC_STATES,
        'coaching_status' => PlanItem::COACHING_STATES,
    ];

    public function mount()
    {
        if ($redirect = $this->resolveActiveFencer()) {
            return $redirect;
        }

        $this->plan = $this->fencer->seasonPlans()->firstOrCreate(['season_id' => $this->season->id]);
    }

    /** @return Collection<int, PlanItem> planned events in date order */
    #[Computed]
    public function items()
    {
        return $this->plan->items()
            ->where('status', '!=', 'skipped')
            ->whereHas('tournament')
            ->with('tournament')
            ->get()
            ->sortBy(fn (PlanItem $i) => $i->tournament->starts_on)
            ->values();
    }

    /** Flip registration on/off; an attended event stays registered. */
    public function toggleRegistered(int $id): void
    {
        $item = $this->plan->items()->find($id);
        if (! $item || $item->status === 'attended') {
            return;
        }

        $item->update(['status' => $item->status === 'registered' ? 'planned' : 'registered']);
        unset($this->items);
    }

    public function setField(int $id, string $field, string $value): void
    {
        if (! isset(self::FIELDS[$field]) || ! in_array($value, self::FIELDS[$field], true)) {
            return;
        }

        $this->plan->items()->whereKey($id)->update([$field => $value]);
        unset($this->items);
    }

    public function render()
    {
        return view('livewire.prep-tracker', ['items' => $this->items]);
    }
}
