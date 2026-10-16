<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Configuració de l'aprovació automàtica de fitxatges (una sola fila, com BreakSetting). */
class AprovacioAutomaticaSetting extends Model
{
    protected $table = 'aprovacio_automatica_settings';

    protected $fillable = ['enabled', 'marge_minuts'];

    protected $casts = [
        'enabled' => 'boolean',
        'marge_minuts' => 'integer',
    ];

    public static function getSettings(): self
    {
        return self::first() ?? self::create(['enabled' => true, 'marge_minuts' => 10]);
    }
}
