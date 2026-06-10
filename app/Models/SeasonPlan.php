<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Spreadsheet-style season rollup. Expects items.expenses to be loaded.
     * Skipped items stay on the schedule but drop out of every money number.
     */
    public function costSummary(): array
    {
        $counted = $this->items->where('status', '!=', 'skipped');
        $projected = round($counted->sum(fn (PlanItem $i) => $i->effectiveTotal()), 2);
        $paid = round($counted->where('paid', 'yes')->sum(fn (PlanItem $i) => $i->effectiveTotal()), 2);
        $withCosts = $counted->filter(fn (PlanItem $i) => $i->effectiveTotal() > 0);

        return [
            'projected' => $projected,
            'paid' => $paid,
            'to_pay' => round($projected - $paid, 2),
            'avg' => $withCosts->isEmpty() ? 0.0 : round($projected / $withCosts->count(), 2),
            'by_category' => collect(array_keys(config('fencing.expense_categories')))
                ->mapWithKeys(fn ($c) => [$c => round($counted->sum(fn (PlanItem $i) => $i->categoryAmount($c) ?? 0), 2)])
                ->all(),
            'done' => $this->items->where('status', 'attended')->count(),
            'total' => $this->items->count(),
            'budget' => $this->budget,
            'surplus' => $this->budget !== null ? round($this->budget - $projected, 2) : null,
        ];
    }
}
