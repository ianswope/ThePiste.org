<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fencer extends Model
{
    protected $fillable = [
        'user_id', 'home_club_id', 'name', 'gender', 'handedness', 'birth_year',
        'usa_fencing_id', 'weapon', 'age_group', 'rating',
        'home_zip', 'home_lat', 'home_lng', 'goal', 'drive_radius_miles',
    ];

    protected $casts = [
        'home_lat' => 'float',
        'home_lng' => 'float',
        'drive_radius_miles' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'home_club_id');
    }

    public function weapons(): HasMany
    {
        return $this->hasMany(FencerWeapon::class);
    }

    public function seasonPlans(): HasMany
    {
        return $this->hasMany(SeasonPlan::class);
    }

    /** The fencer's primary weapon row (falls back to first). */
    public function primaryWeapon(): ?FencerWeapon
    {
        return $this->weapons->firstWhere('is_primary', true) ?? $this->weapons->first();
    }

    /** Competition categories this fencer is eligible to enter. */
    public function eligibleCategories(): array
    {
        return config("fencing.eligibility.{$this->age_group}", []);
    }

    /** USA Fencing region, derived from the home club (fallback for v1). */
    public function region(): ?string
    {
        return $this->homeClub?->region;
    }

    public function driveRadius(): int
    {
        return $this->drive_radius_miles ?: config('fencing.default_drive_radius');
    }
}
