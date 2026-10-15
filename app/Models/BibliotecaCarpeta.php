<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Carpeta de la Documentació (biblioteca de consulta per a tothom). */
class BibliotecaCarpeta extends Model
{
    protected $table = 'biblioteca_carpetes';

    protected $fillable = ['nom', 'ordre'];

    public function fitxers()
    {
        return $this->hasMany(BibliotecaFitxer::class, 'carpeta_id')->orderBy('nom');
    }
}
