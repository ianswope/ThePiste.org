<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'fencer_id', 'tournament_id', 'event_name', 'category', 'weapon',
        'fenced_on', 'place', 'field_size', 'rating_earned', 'points', 'notes',
    ];

    protected $casts = [
        'fenced_on' => 'date',
        'place' => 'integer',
        'field_size' => 'integer',
        'points' => 'float',
    ];

    public function fencer(): BelongsTo
    {
        return $this->belongsTo(Fencer::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function isPodium(): bool
    {
        return $this->place <= 3;
    }
}
