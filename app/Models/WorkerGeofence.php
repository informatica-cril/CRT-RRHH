<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerGeofence extends Model
{
    protected $fillable = ['user_id', 'cps', 'poligons', 'font'];

    protected $casts = [
        'cps' => 'array',
        'poligons' => 'array',
    ];
}
