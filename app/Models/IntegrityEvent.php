<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Esdeveniment del llibre d'integritat (APPEND-ONLY). Cada fila encadena el hash de l'anterior:
 * hash = sha256(prev_hash | source | source_id | occurred_at | payload_hash). Alterar o esborrar
 * qualsevol fila trenca la cadena i es detecta en la verificació.
 */
class IntegrityEvent extends Model
{
    public $timestamps = false; // només created_at, mai s'actualitza

    protected $fillable = [
        'occurred_at', 'source', 'source_id', 'payload_hash', 'prev_hash', 'hash', 'seal_id', 'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function seal()
    {
        return $this->belongsTo(DailySeal::class, 'seal_id');
    }
}
