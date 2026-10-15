<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un missatge de la conversa d'una incidència (de RRHH o del treballador). */
class IncidenciaMissatge extends Model
{
    protected $table = 'incidencia_missatges';

    protected $fillable = ['incidencia_id', 'user_id', 'text'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
