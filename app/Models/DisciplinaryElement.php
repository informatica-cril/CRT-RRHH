<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryElement extends Model
{
    protected $fillable = [
        'professional', 'user_id', 'tipus_falta', 'data_fet', 'data_coneixement',
        'font', 'origen', 'valor', 'descripcio', 'imputable', 'estat', 'case_id', 'created_by',
    ];

    /** Espai de noms de font reservat al pont amb l'app domiciliària: cap formulari hi escriu. */
    public const FONT_PONT = 'domi:';

    protected $casts = [
        'data_fet'         => 'date',
        'data_coneixement' => 'date',
        'apte_plec'        => 'boolean',
        'apte_plec_ts'     => 'datetime',
    ];

    /**
     * Aptitud per fonamentar un escrit. `apte_plec` NO és fillable a propòsit: rehabilitar una prova
     * bloquejada és una decisió motivada d'una persona i té el seu propi camí
     * (DisciplinaryController::reinstateElement), no un camp més d'un formulari d'alta.
     *
     * Aquest scope és l'únic lloc on es defineix "prova que pot entrar en un escrit". Tot el que
     * fonamenti un relat de fets, una reincidència o un suggeriment d'obertura hi ha de passar.
     */
    public function scopeApte($query)
    {
        return $query->where('apte_plec', true);
    }

    public function case()
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }
}
