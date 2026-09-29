<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExcedenciaType extends Model
{
    protected $fillable = [
        'name', 'min_months', 'max_months',
        'requires_seniority_months', 'job_reserve',
        'job_reserve_months', 'seniority_counts', 'description',
    ];

    protected $casts = [
        'job_reserve' => 'boolean',
        'seniority_counts' => 'boolean',
    ];

    public function excedencias()
    {
        return $this->hasMany(Excedencia::class);
    }
}
