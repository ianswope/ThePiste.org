<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = ['plan_item_id', 'category', 'est_amount', 'actual_amount'];

    protected $casts = ['est_amount' => 'decimal:2', 'actual_amount' => 'decimal:2'];

    public function planItem(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class);
    }

    /** The number that counts: what it actually cost, else the estimate. */
    public function effective(): float
    {
        return $this->actual_amount ?? $this->est_amount ?? 0.0;
    }
}
