<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tournament extends Model
{
    protected $fillable = [
        'season_id', 'host_club_id', 'name', 'slug', 'external_id', 'starts_on', 'ends_on', 'level', 'country',
        'city', 'state', 'region', 'lat', 'lng', 'is_nac',
        'circuits', 'contested_events', 'curated_note', 'source_url', 'last_seen_at', 'alerted_at',
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
        'alerted_at' => 'datetime',
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

    /**
     * Human location, e.g. "Chicago, IL". FIE/international events carry no
     * US state, so fall back to the country and never render a dangling comma.
     */
    public function location(): string
    {
        $region = $this->state ?: ($this->country && $this->country !== 'US' ? $this->country : null);

        return collect([$this->city, $region])->filter()->implode(', ');
    }

    /** National-level event (NAC, JO, Championship): long registration lead. */
    public function isNational(): bool
    {
        return (bool) $this->is_nac || $this->level === 'national';
    }

    /** FIE / international event (opt-in per fencer; never an auto-anchor). */
    public function isInternational(): bool
    {
        return str_starts_with((string) $this->level, 'fie');
    }

    /**
     * Compact date span, e.g. "Aug 22–23", "Dec 19–Jan 2", or "Oct 9" for a
     * one-day event. Pass $weekday to prefix the start with its day name.
     */
    public function dateRange(bool $weekday = false): string
    {
        $startFormat = $weekday ? 'D M j' : 'M j';
        if ($this->ends_on->isSameDay($this->starts_on)) {
            return $this->starts_on->format($startFormat);
        }

        $endFormat = $this->ends_on->month === $this->starts_on->month ? 'j' : 'M j';

        return $this->starts_on->format($startFormat).'–'.$this->ends_on->format($endFormat);
    }
}
