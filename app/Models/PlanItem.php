<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanItem extends Model
{
    protected $fillable = ['season_plan_id', 'tournament_id', 'status', 'est_cost', 'notes', 'reminded_at'];

    protected $casts = ['est_cost' => 'float', 'reminded_at' => 'datetime'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SeasonPlan::class, 'season_plan_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}
