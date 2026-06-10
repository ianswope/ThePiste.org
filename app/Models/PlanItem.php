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

    /** Best known cost for one category: actual once entered, else estimate (null if untracked). */
    public function categoryAmount(string $category): ?float
    {
        return $this->expenses->firstWhere('category', $category)?->effective();
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

    /** Real money has been entered, so removal shouldn't silently discard it. */
    public function hasFinancialHistory(): bool
    {
        return $this->paid !== 'no' || $this->expenses()->exists();
    }

    /**
     * Take this event out of the active plan. If costs or payments have been
     * recorded, keep the row as skipped (off every total and off the schedule)
     * so an accidental un-tick can't wipe real spending; otherwise drop it.
     */
    public function removeFromPlan(): void
    {
        if ($this->hasFinancialHistory()) {
            $this->update(['status' => 'skipped']);
        } else {
            $this->delete();
        }
    }
}
