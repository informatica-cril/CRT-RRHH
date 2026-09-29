<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Historial append-only del cas (cadena de custòdia). Només created_at, mai s'actualitza. */
class DisciplinaryCaseEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['case_id', 'estat_de', 'estat_a', 'actor', 'nota', 'document_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
