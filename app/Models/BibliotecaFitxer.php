<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Fitxer de la Documentació. El fitxer és al disc privat ('local'), a biblioteca/. */
class BibliotecaFitxer extends Model
{
    protected $table = 'biblioteca_fitxers';

    protected $fillable = ['carpeta_id', 'nom', 'path', 'mime', 'mida', 'pujat_per'];

    protected $hidden = ['path'];

    public function carpeta()
    {
        return $this->belongsTo(BibliotecaCarpeta::class, 'carpeta_id');
    }
}
