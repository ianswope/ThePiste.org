<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = [
        'name', 'slug', 'city', 'state', 'region', 'lat', 'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class, 'host_club_id');
    }

    public function fencers(): HasMany
    {
        return $this->hasMany(Fencer::class, 'home_club_id');
    }
}
