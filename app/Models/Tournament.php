<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tournament extends Model
{
    protected $fillable = [
        'season_id', 'host_club_id', 'name', 'slug', 'external_id', 'starts_on', 'ends_on', 'level', 'country',
        'city', 'state', 'region', 'lat', 'lng', 'is_nac',
        'circuits', 'contested_events', 'curated_note', 'source_url', 'last_seen_at',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_nac' => 'boolean',
        'circuits' => 'array',
        'contested_events' => 'array',
        'lat' => 'float',
        'lng' => 'float',
        'last_seen_at' => 'datetime',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function hostClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'host_club_id');
    }

    /** Calendar month bucket, e.g. "September 2026". */
    public function monthLabel(): string
    {
        return $this->starts_on->format('F Y');
    }
}
