<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationTracking extends Model
{
    protected $table = 'location_tracking';

    protected $fillable = [
        'user_id', 'latitude', 'longitude',
        'accuracy', 'event_type',
    ];

    protected $casts = [
        'latitude' => 'encrypted',
        'longitude' => 'encrypted',
        'accuracy' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
