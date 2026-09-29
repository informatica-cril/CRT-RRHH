<?php

namespace App\Models;

use App\Services\DisciplinaryEngine;
use Illuminate\Database\Eloquent\Model;

/**
 * Catàleg de faltes (XII Conveni + ET).
 *
 * `prescripcio_dies` NO és paràmetre: el termini el fixa l'ET art. 60.2 segons el GRAU i el motor
 * (DisciplinaryEngine::diesPrescripcio) n'és l'única font de veritat. La columna es conserva com a
 * mirall per a llistats i informes, i es RECALCULA en desar perquè mai pugui contradir la llei.
 */
class DisciplinaryFaultType extends Model
{
    protected $fillable = [
        'clau', 'descripcio', 'grau_base', 'base_conveni', 'base_et',
        'prescripcio_dies', 'reincidencia_puja', 'llindar_reincidencia',
        'requereix_apercebiment', 'requereix_afectacio_servei',
    ];

    protected $casts = [
        'reincidencia_puja'          => 'boolean',
        'requereix_apercebiment'     => 'boolean',
        'requereix_afectacio_servei' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $ft) {
            $legal = app(DisciplinaryEngine::class)->diesPrescripcio($ft->grau_base);
            if ($legal !== null) {
                $ft->prescripcio_dies = $legal;
            }
        });
    }
}
