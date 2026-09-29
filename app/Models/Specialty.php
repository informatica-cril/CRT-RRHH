<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Especialitat clínica de rehabilitació (general, respiratori, sol_pelvia, limfatic).
 * Configurable des de RRHH i sincronitzada cap a domi per a l'atribució de pacients.
 */
class Specialty extends Model
{
    protected $fillable = ['code', 'name', 'sort', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'specialty_user', 'specialty_id', 'user_id')->withTimestamps();
    }
}
