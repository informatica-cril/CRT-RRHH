<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'province',
        'postal_codes',
        'municipalities',
        'domi_microzona_id',
        'active',
    ];

    protected $casts = [
        'postal_codes' => 'array',
        'municipalities' => 'array',
        'active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'zone_worker')
            ->withPivot('valid_from', 'valid_to')
            ->withTimestamps();
    }
}
