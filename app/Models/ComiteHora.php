<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComiteHora extends Model
{
    protected $table = 'comite_hores';

    protected $fillable = [
        'membre_id', 'user_id', 'data', 'hora_inici', 'hora_fi', 'minuts',
        'tipus', 'consumeix_credit', 'motiu',
    ];

    protected $casts = [
        'data'             => 'date:Y-m-d',
        'consumeix_credit' => 'boolean',
        'validat_ts'       => 'datetime',
    ];

    // Com als justificants d'absències: el fitxer es baixa per l'endpoint amb guarda, mai pel camí del disc.
    protected $hidden = ['justificant_path'];

    public const TIPUS = [
        'reunio_comite'      => 'Reunió del comitè',
        'assemblea'          => 'Assemblea',
        'negociacio_empresa' => "Reunió convocada per l'empresa",
        'formacio'           => 'Formació sindical',
        'gestio'             => 'Gestions de representació',
        'altres'             => 'Altres',
    ];

    /** Les reunions que convoca l'empresa no es descompten del crèdit horari. */
    public const NO_CONSUMEIXEN = ['negociacio_empresa'];

    public function membre()
    {
        return $this->belongsTo(ComiteMembre::class, 'membre_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validador()
    {
        return $this->belongsTo(User::class, 'validat_per');
    }
}
