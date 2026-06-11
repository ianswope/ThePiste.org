<?php

namespace App\Livewire;

use App\Livewire\Concerns\ClampsMoney;
use App\Livewire\Concerns\ResolvesActiveFencer;
use App\Models\PlanItem;
use App\Models\SeasonPlan;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The page that replaces the season-budget spreadsheet: per-event trip costs
 * in five categories, entered as estimates while planning and overwritten
 * with actuals as bookings land, plus the rollup cards (projected vs budget,
 * paid vs still-to-pay, per-category totals).
 */
#[Layout('layouts.builder')]
class BudgetTracker extends Component
{
    use ClampsMoney;
    use ResolvesActiveFencer;

    public SeasonPlan $plan;

    /** Which layer the inputs edit: planning numbers or real ones. */
    public string $layer = 'est';

    public ?string $budget = null;

    /** @var array<int, array<string, string|null>> item id => category => amount (active layer) */
    public array $amounts = [];

    /** @var array<int, string> item id => status */
    public array $statuses = [];

    /** @var array<int, string> item id => paid state */
    public array $paids = [];

    public function mount()
    {
        if ($redirect = $this->resolveActiveFencer()) {
            return $redirect;
        }

        $this->plan = $this->fencer->seasonPlans()->firstOrCreate(['season_id' => $this->season->id]);
        $this->budget = $this->plan->budget !== null ? (string) $this->plan->budget : null;

        $this->loadRows();
    }

    private function loadRows(): void
    {
        $this->amounts = [];
        foreach ($this->items as $item) {
            $this->statuses[$item->id] = $item->status;
            $this->paids[$item->id] = $item->paid;
            foreach (array_keys(config('fencing.expense_categories')) as $cat) {
                $expense = $item->expenses->firstWhere('category', $cat);
                $value = $this->layer === 'est' ? $expense?->est_amount : $expense?->actual_amount;
                $this->amounts[$item->id][$cat] = $value !== null ? (string) $value : null;
            }
        }
    }

    /**
     * The plan's items in date order, with tournament + expenses loaded.
     * Memoized for the request (accessed in both loadRows and render); write
     * hooks unset it so a re-render after an edit recomputes fresh.
     *
     * @return Collection<int, PlanItem>
     */
    #[Computed]
    public function items()
    {
        return $this->plan->items()->with(['tournament', 'expenses'])->get()
            ->sortBy(fn (PlanItem $i) => $i->tournament->starts_on)
            ->values();
    }

    public function setLayer(string $layer): void
    {
        if (in_array($layer, ['est', 'actual'], true)) {
            $this->layer = $layer;
            $this->loadRows();
        }
    }

    public function updatedBudget($value): void
    {
        $budget = $this->clampMoney($value);
        $this->budget = $budget !== null ? (string) $budget : null;
        $this->plan->update(['budget' => $budget]);
    }

    public function updatedAmounts($value, $key): void
    {
        [$itemId, $category] = explode('.', $key, 2);
        $item = $this->plan->items()->find((int) $itemId);
        if (! $item || ! array_key_exists($category, config('fencing.expense_categories'))) {
            return;
        }

        $amount = $this->clampMoney($value);
        $column = $this->layer === 'est' ? 'est_amount' : 'actual_amount';

        $expense = $item->expenses()->firstOrNew(['category' => $category]);
        $expense->{$column} = $amount;

        // A fully-emptied row is deletion, not a zero-cost trip leg.
        if ($expense->est_amount === null && $expense->actual_amount === null) {
            if ($expense->exists) {
                $expense->delete();
            }
        } else {
            $expense->save();
        }

        $this->amounts[(int) $itemId][$category] = $amount !== null ? (string) $amount : null;
        unset($this->items); // the edited expense must show in this render
    }

    public function updatedStatuses($value, $key): void
    {
        if (in_array($value, PlanItem::STATUSES, true)) {
            $this->plan->items()->whereKey((int) $key)->update(['status' => $value]);
            unset($this->items);
        }
    }

    public function updatedPaids($value, $key): void
    {
        if (in_array($value, PlanItem::PAID_STATES, true)) {
            $this->plan->items()->whereKey((int) $key)->update(['paid' => $value]);
            unset($this->items);
        }
    }

    public function render()
    {
        $items = $this->items;
        $this->plan->setRelation('items', $items);

        return view('livewire.budget-tracker', [
            'items' => $items,
            'summary' => $this->plan->costSummary(),
            'categories' => config('fencing.expense_categories'),
        ]);
    }
}
