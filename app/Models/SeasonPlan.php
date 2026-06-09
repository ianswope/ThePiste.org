<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeasonPlan extends Model
{
    protected $fillable = ['fencer_id', 'season_id', 'share_slug'];

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
}
