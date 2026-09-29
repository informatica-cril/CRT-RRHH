<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RendimentAllegacio extends Model
{
    protected $table = 'rendiment_allegacions';

    protected $fillable = [
        'user_id', 'periode_desde', 'periode_fins', 'indicador',
        'text', 'presentada_ts', 'resposta', 'resposta_ts', 'respost_per',
    ];

    protected $casts = [
        // Amb 'date' sec, Carbon serialitza a UTC i el període surt desplaçat un dia a la pantalla.
        // El període és una etiqueta, no un instant: es desa i es mostra tal qual.
        'periode_desde' => 'date:Y-m-d',
        'periode_fins' => 'date:Y-m-d',
        'presentada_ts' => 'datetime',
        'resposta_ts' => 'datetime',
    ];

    /**
     * Invariants del contradictori — capa d'aplicació (missatge llegible abans que salti la BD).
     * La garantia dura són els CHECK de la taula; això és el que veu qui programa.
     */
    protected static function booted(): void
    {
        static::saving(function (self $a) {
            if ($a->resposta && ! $a->resposta_ts) {
                throw new \InvalidArgumentException('Al·legació: hi ha una resposta sense data de resposta.');
            }
            if ($a->resposta_ts && ! $a->resposta) {
                throw new \InvalidArgumentException('Al·legació: hi ha una data de resposta sense text de resposta.');
            }
            if ($a->resposta_ts && $a->presentada_ts && $a->resposta_ts->lt($a->presentada_ts)) {
                throw new \InvalidArgumentException(
                    'Al·legació incoherent: la resposta (' . $a->resposta_ts->toDateTimeString()
                    . ') no pot ser anterior a la presentació (' . $a->presentada_ts->toDateTimeString() . ').'
                );
            }
        });
    }

    /** Encara sense contestar: el que la safata de Direcció ha de resoldre. */
    public function scopePendent($query)
    {
        return $query->whereNull('resposta_ts');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function responedor()
    {
        return $this->belongsTo(User::class, 'respost_per');
    }
}
