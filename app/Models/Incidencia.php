<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Incidència enviada per un treballador des de l'app (la gestionen RRHH i Superior). */
class Incidencia extends Model
{
    protected $table = 'incidencies';

    public const TIPUS = ['fitxatge', 'horari', 'nomina', 'permisos', 'app', 'altres'];

    protected $fillable = ['user_id', 'tipus', 'assumpte', 'descripcio', 'estat', 'resolta_per', 'resolta_at', 'nota_resolucio'];

    protected $casts = ['resolta_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function resolutor()
    {
        return $this->belongsTo(User::class, 'resolta_per');
    }
}
