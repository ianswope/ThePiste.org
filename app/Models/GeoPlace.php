<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeoPlace extends Model
{
    protected $fillable = ['city', 'state', 'lat', 'lng'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
}
