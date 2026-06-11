<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = [
        'name', 'slug', 'starts_on', 'ends_on', 'is_active',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
    ];

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /**
     * The season the app operates on: the flagged-active one, falling back to
     * the only/first season. Centralizes the "current season" lookup that the
     * calendar, builder, MCP tools, and digest commands all need.
     */
    public static function active(): self
    {
        return static::where('is_active', true)->first() ?? static::firstOrFail();
    }
}
