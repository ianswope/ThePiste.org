<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FencerWeapon extends Model
{
    protected $fillable = ['fencer_id', 'weapon', 'rating', 'is_primary'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function fencer(): BelongsTo
    {
        return $this->belongsTo(Fencer::class);
    }
}
