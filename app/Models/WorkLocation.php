<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ambulatory_center_id',
        'name',
        'address',
        'lat',
        'lng',
        'radius',
        'active',
    ];

    protected $casts = [
        'lat' => 'double',
        'lng' => 'double',
        'radius' => 'integer',
        'active' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'location_worker', 'work_location_id', 'user_id')
            ->withPivot('valid_from', 'valid_to')
            ->withTimestamps();
    }

    public function ambulatoryCenter()
    {
        return $this->belongsTo(AmbulatoryCenter::class);
    }
}
