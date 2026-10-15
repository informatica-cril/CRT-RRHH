<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Incidència enviada per un treballador des de l'app (la gestionen RRHH i Superior). */
class Incidencia extends Model
{
    protected $table = 'incidencies';

    public const TIPUS = ['fitxatge', 'horari', 'nomina', 'permisos', 'app', 'altres'];

    /** Estats de gestió (només RRHH i Superior). Els dos últims tanquen la incidència. */
    public const ESTATS_GESTIO = ['nova', 'en_curs', 'esperant', 'resolta', 'descartada'];
    public const ESTATS_TANCATS = ['resolta', 'descartada'];

    protected $fillable = ['user_id', 'tipus', 'assumpte', 'descripcio', 'estat', 'estat_gestio', 'resolta_per', 'resolta_at', 'nota_resolucio', 'vist_treballador_at'];

    protected $casts = ['resolta_at' => 'datetime', 'vist_treballador_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function missatges()
    {
        return $this->hasMany(IncidenciaMissatge::class)->orderBy('id');
    }

    public function resolutor()
    {
        return $this->belongsTo(User::class, 'resolta_per');
    }
}
