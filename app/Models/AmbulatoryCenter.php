<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmbulatoryCenter extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function workLocations()
    {
        return $this->hasMany(WorkLocation::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'ambulatory_center_user', 'ambulatory_center_id', 'user_id')
            ->withPivot('valid_from', 'valid_to')
            ->withTimestamps();
    }
}
