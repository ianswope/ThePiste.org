<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * A structured season goal. Types:
 *  - rating:   earn a letter rating (params: target_rating) in a weapon
 *  - qualify:  get on a championship's qualification path (params: target, see config fencing.qualify_targets)
 *  - standing: build regional points standing (params: category, null = any eligible)
 *  - develop:  competition mileage (params: target_events)
 */
class Goal extends Model
{
    protected $fillable = ['fencer_id', 'type', 'weapon', 'params', 'status', 'achieved_at'];

    protected $casts = [
        'params' => 'array',
        'achieved_at' => 'datetime',
    ];

    public const TYPES = ['rating', 'qualify', 'standing', 'develop'];

    public function fencer(): BelongsTo
    {
        return $this->belongsTo(Fencer::class);
    }

    /**
     * Upsert a goal for a fencer: one active goal per type+weapon, so the new
     * one replaces any existing match. Atomic so a failed create can't leave the
     * fencer with the old goal deleted and nothing in its place. Shared by the
     * season builder and the set-goal MCP tool.
     */
    public static function createForFencer(Fencer $fencer, string $type, ?string $weapon, array $params): self
    {
        return DB::transaction(function () use ($fencer, $type, $weapon, $params) {
            $fencer->goals()->active()
                ->where('type', $type)
                ->where('weapon', $weapon)
                ->delete();

            return $fencer->goals()->create([
                'type' => $type,
                'weapon' => $weapon,
                'params' => $params,
                'status' => 'active',
            ]);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function param(string $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    /** Human label, e.g. "Earn a B in foil", "Qualify for Junior Olympics". */
    public function label(): string
    {
        return match ($this->type) {
            'rating' => 'Earn a '.$this->param('target_rating').($this->weapon ? " in {$this->weapon}" : ''),
            'qualify' => 'Qualify for '.(config('fencing.qualify_targets.'.$this->param('target').'.label') ?? $this->param('target')),
            'standing' => $this->param('category')
                ? 'Build '.$this->param('category').' regional standing'
                : 'Build regional standing',
            'develop' => 'Fence '.($this->param('target_events') ?: 8).' events this season',
            default => $this->type,
        };
    }
}
