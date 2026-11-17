<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Incidència enviada per un treballador des de l'app (la gestionen RRHH i Superior). */
class Incidencia extends Model
{
    protected $table = 'incidencies';

    public const TIPUS = ['fitxatge', 'horari', 'nomina', 'permisos', 'app', 'altres'];

    /** A qui va dirigida: RRHH (per defecte) o l'equip d'informàtica. */
    public const DESTINATARIS = ['rrhh', 'informatica'];

    /** Estats de gestió (només RRHH i Superior). Els dos últims tanquen la incidència. */
    public const ESTATS_GESTIO = ['nova', 'en_curs', 'esperant', 'resolta', 'descartada'];
    public const ESTATS_TANCATS = ['resolta', 'descartada'];

    protected $fillable = ['user_id', 'tipus', 'destinatari', 'assumpte', 'descripcio', 'estat', 'estat_gestio', 'resolta_per', 'resolta_at', 'nota_resolucio', 'vist_treballador_at'];

    protected $casts = ['resolta_at' => 'datetime', 'vist_treballador_at' => 'datetime'];

    /**
     * Incidències que esperen una acció de qui les gestiona: obertes i (sense cap resposta encara i noves) o
     * amb l'últim missatge del propi treballador. Si l'últim missatge és de gestió (o l'estat és «esperant el
     * treballador»), la pilota és a l'altre costat i no s'avisa. RRHH, 11-2026: l'avís només si hi ha resposta.
     */
    public static function peraAtendre(array $destinataris)
    {
        return static::with(['missatges:id,incidencia_id,user_id'])
            ->where('estat', 'oberta')->whereIn('destinatari', $destinataris ?: ['rrhh'])->get()
            ->filter(fn ($i) => $i->estat_gestio !== 'esperant' && ($i->estat_gestio === 'nova' || (! $i->missatges->isEmpty()
                && (int) $i->missatges->last()->user_id === (int) $i->user_id)))
            ->values();
    }

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
