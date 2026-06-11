<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanItem extends Model
{
    public const STATUSES = ['planned', 'registered', 'attended', 'skipped'];

    public const PAID_STATES = ['no', 'partial', 'yes'];

    /** Travel and lodging: still to do, done, or not needed (day trip / drive). */
    public const LOGISTIC_STATES = ['pending', 'booked', 'na'];

    public const COACHING_STATES = ['undecided', 'arranged', 'none'];

    protected $fillable = [
        'season_plan_id', 'tournament_id', 'status', 'paid', 'est_cost', 'notes', 'reminded_at',
        'travel_status', 'lodging_status', 'coaching_status',
    ];

    protected $casts = ['est_cost' => 'decimal:2', 'reminded_at' => 'datetime'];

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

    /**
     * The prep pipeline for this event: each milestone with whether it's done.
     * Registration and fee payment reuse status/paid; travel, lodging, and
     * coaching are tracked here. "na" travel/lodging and a decided "none"
     * coaching count as done — there's nothing left to do.
     *
     * @return array<int, array{key: string, label: string, done: bool}>
     */
    public function prepChecklist(): array
    {
        return [
            ['key' => 'registered', 'label' => 'Registered', 'done' => in_array($this->status, ['registered', 'attended'], true)],
            ['key' => 'fees', 'label' => 'Fees paid', 'done' => $this->paid === 'yes'],
            ['key' => 'travel', 'label' => 'Travel booked', 'done' => in_array($this->travel_status, ['booked', 'na'], true)],
            ['key' => 'lodging', 'label' => 'Lodging booked', 'done' => in_array($this->lodging_status, ['booked', 'na'], true)],
            ['key' => 'coaching', 'label' => 'Coaching', 'done' => in_array($this->coaching_status, ['arranged', 'none'], true)],
        ];
    }

    /** @return array{done: int, total: int} how many prep milestones are settled */
    public function prepProgress(): array
    {
        $items = $this->prepChecklist();

        return ['done' => count(array_filter($items, fn ($i) => $i['done'])), 'total' => count($items)];
    }
}
