<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SeasonPlan extends Model
{
    protected $fillable = ['fencer_id', 'season_id', 'share_slug', 'budget'];

    protected $casts = ['budget' => 'float'];

    public function fencer(): BelongsTo
    {
        return $this->belongsTo(Fencer::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanItem::class);
    }

    /**
     * The plan items that count toward money totals. Skipped events stay on
     * the schedule but drop out of every cost number, so this is the one place
     * that rule lives — every total (budget page, builder tile, share page,
     * MCP) sums over this set. Self-loads items.expenses so callers don't have
     * to remember the eager-load.
     */
    public function countedItems(): Collection
    {
        $this->loadMissing('items.expenses');

        return $this->items->where('status', '!=', 'skipped');
    }

    /** Best-known season total: per-item actuals over estimates, skipped excluded. */
    public function projectedTotal(): float
    {
        return round($this->countedItems()->sum(fn (PlanItem $i) => $i->effectiveTotal()), 2);
    }

    /**
     * Spreadsheet-style season rollup. Skipped items stay on the schedule but
     * drop out of every money number (see countedItems()).
     */
    public function costSummary(): array
    {
        $counted = $this->countedItems();
        $projected = round($counted->sum(fn (PlanItem $i) => $i->effectiveTotal()), 2);
        $paid = round($counted->where('paid', 'yes')->sum(fn (PlanItem $i) => $i->effectiveTotal()), 2);
        $withCosts = $counted->filter(fn (PlanItem $i) => $i->effectiveTotal() > 0);

        $byCategory = collect(array_keys(config('fencing.expense_categories')))
            ->mapWithKeys(fn ($c) => [$c => round($counted->sum(fn (PlanItem $i) => $i->categoryAmount($c) ?? 0), 2)])
            ->all();

        return [
            'projected' => $projected,
            'paid' => $paid,
            'to_pay' => round($projected - $paid, 2),
            'avg' => $withCosts->isEmpty() ? 0.0 : round($projected / $withCosts->count(), 2),
            'by_category' => $byCategory,
            // Ballpark estimates (est_cost, no itemized breakdown) count toward
            // projected but no category — surface the remainder so the category
            // chips and the Projected tile always reconcile.
            'unitemized' => round($projected - array_sum($byCategory), 2),
            'done' => $this->items->where('status', 'attended')->count(),
            'total' => $this->items->count(),
            'budget' => $this->budget,
            'surplus' => $this->budget !== null ? round($this->budget - $projected, 2) : null,
        ];
    }
}
