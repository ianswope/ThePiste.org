<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZipCode extends Model
{
    protected $primaryKey = 'zip';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['zip', 'lat', 'lng', 'city', 'state'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
}
