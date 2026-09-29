<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryCase extends Model
{
    protected $fillable = [
        'professional', 'user_id', 'vincle', 'tipus_falta', 'gravetat', 'estat',
        'data_coneixement', 'data_fet', 'data_prescripcio', 'reincidencia_acreditada',
        'afectacio_servei_acreditada', 'afectacio_confirmada_per', 'apercebiment_previ',
        'te_evidencia_licita', 'es_representant', 'expedient_contradictori',
        'comunicat_ts', 'termini_alegacions', 'alegacions_ts', 'audiencia_rlt_ts',
        'alegacions_text', 'alegacions_per', 'alegacions_adjunt_nom', 'alegacions_adjunt',
        'renuncia_termini_ts', 'renuncia_termini_per',
        'sancio_proposada', 'resolucio_tipus', 'resolucio_motivacio', 'resolt_per',
        'resolt_ts', 'obert_per', 'obert_ts',
    ];

    protected $casts = [
        'data_coneixement'            => 'date',
        'data_fet'                    => 'date',
        'data_prescripcio'            => 'date',
        'termini_alegacions'          => 'date',
        'comunicat_ts'                => 'datetime',
        'alegacions_ts'               => 'datetime',
        'renuncia_termini_ts'         => 'datetime',
        'audiencia_rlt_ts'            => 'datetime',
        'resolt_ts'                   => 'datetime',
        'obert_ts'                    => 'datetime',
        'reincidencia_acreditada'     => 'boolean',
        'afectacio_servei_acreditada' => 'boolean',
        'apercebiment_previ'          => 'boolean',
        'te_evidencia_licita'         => 'boolean',
        'es_representant'             => 'boolean',
        'expedient_contradictori'     => 'boolean',
    ];

    /** Titular de l'expedient a RRHH: la seva fitxa (relacio) mana sobre el 'vincle' del formulari. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events()
    {
        return $this->hasMany(DisciplinaryCaseEvent::class, 'case_id')->orderBy('id');
    }

    public function documents()
    {
        return $this->hasMany(DisciplinaryDocument::class, 'case_id')->orderBy('id');
    }

    public function elements()
    {
        return $this->hasMany(DisciplinaryElement::class, 'case_id');
    }
}
