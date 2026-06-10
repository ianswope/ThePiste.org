<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanItem extends Model
{
    public const STATUSES = ['planned', 'registered', 'attended', 'skipped'];

    public const PAID_STATES = ['no', 'partial', 'yes'];

    protected $fillable = ['season_plan_id', 'tournament_id', 'status', 'paid', 'est_cost', 'notes', 'reminded_at'];

    protected $casts = ['est_cost' => 'float', 'reminded_at' => 'datetime'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SeasonPlan::class, 'season_plan_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Best known cost for one category: actual once entered, else estimate. */
    public function categoryAmount(string $category): ?float
    {
        $expense = $this->expenses->firstWhere('category', $category);

        return $expense?->actual_amount ?? $expense?->est_amount;
    }

    /**
     * Best known trip total. Per category actuals replace estimates as they
     * land; the legacy single-number est_cost is the fallback for items with
     * no itemized expenses (the season builder's quick ballpark).
     */
    public function effectiveTotal(): float
    {
        if ($this->expenses->isEmpty()) {
            return (float) ($this->est_cost ?? 0);
        }

        return round($this->expenses->sum(fn (Expense $e) => $e->effective()), 2);
    }
}
