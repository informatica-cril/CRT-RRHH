<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Departament = servei × modalitat: rehabilitació o logopèdia o teràpia ocupacional, i
 * ambulatòria o domiciliària. Una persona pot ser a més d'un.
 */
class Department extends Model
{
    protected $fillable = ['code', 'name', 'service', 'modality', 'sort', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'department_user');
    }
}
