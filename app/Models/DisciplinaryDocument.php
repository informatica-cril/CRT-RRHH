<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisciplinaryDocument extends Model
{
    protected $fillable = [
        'case_id', 'professional', 'user_id', 'tipus', 'contingut', 'estat',
        'motor_hash', 'contingut_hash', 'apartat_del_motor',
        'generat_per', 'signat_per', 'signat_ts', 'notificat_ts', 'acus_ts', 'acus_ip',
    ];

    protected $casts = [
        'signat_ts' => 'datetime', 'notificat_ts' => 'datetime', 'acus_ts' => 'datetime',
        'apartat_del_motor' => 'boolean',
    ];

    /** Hash del text d'un escrit. Únic punt on es calcula: si divergís, la verificació mentiria. */
    public static function hashContingut(?string $text): string
    {
        return hash('sha256', (string) $text);
    }

    public function case()
    {
        return $this->belongsTo(DisciplinaryCase::class, 'case_id');
    }
}
