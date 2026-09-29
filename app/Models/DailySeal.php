<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tancament diari del llibre d'integritat. Un únic segell per dia: encadena l'arrel del dia amb el
 * segell anterior i hi incorpora un token RFC 3161 de la FNMT (1 crèdit de segellat per dia,
 * independentment del volum d'esdeveniments).
 */
class DailySeal extends Model
{
    protected $fillable = [
        'seal_date', 'events_count', 'first_event_id', 'last_event_id',
        'prev_seal_hash', 'root_hash', 'tsa_provider', 'tsa_status',
        'tsa_token', 'tsa_time', 'tsa_error', 'sealed_at',
    ];

    protected $casts = [
        'seal_date' => 'date',
        'tsa_time'  => 'datetime',
        'sealed_at' => 'datetime',
    ];

    /**
     * `seal_date` és una DATA, no un instant. Sense aquest mutador Eloquent l'escriu sempre com a
     * 'Y-m-d H:i:s': MySQL trunca l'hora (columna DATE) però SQLite —tests i qualsevol còpia local—
     * desa la cadena sencera, i qualsevol cerca per igualtat del dia deixa de trobar el segell.
     * Normalitzant-la a 'Y-m-d' el llibre es comporta igual a tots els motors.
     */
    public function setSealDateAttribute($value): void
    {
        $this->attributes['seal_date'] = $value === null
            ? null
            : \Carbon\Carbon::parse($value)->toDateString();
    }

    public function events()
    {
        return $this->hasMany(IntegrityEvent::class, 'seal_id');
    }
}
