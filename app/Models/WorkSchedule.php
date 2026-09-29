<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    protected $fillable = ['name', 'total_hours_weekly', 'days'];

    protected $casts = [
        'total_hours_weekly' => 'decimal:2',
        'days' => 'array', // JSON array of day objects
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Normalitza l'array `days` a una estructura consistent i consumible.
     *
     * Cada entrada resultant té: day (0=Dg..6=Ds, convenció getDay JS), active (bool),
     * start (HH:MM|null), end (HH:MM|null) i, opcionalment, name.
     * - Descarta entrades sense `day` (no es poden ubicar).
     * - Si falta `active`, s'infereix: actiu quan té start i end.
     * - Una entrada marcada activa però sense horari es passa a inactiva.
     * - Conserva jornades partides (diverses entrades amb el mateix `day`).
     *
     * @param  mixed  $days
     * @return array
     */
    public static function normalizeDays($days): array
    {
        if (is_string($days)) {
            $days = json_decode($days, true);
        }
        if (! is_array($days)) {
            return [];
        }

        $out = [];
        foreach ($days as $d) {
            if (! is_array($d)) {
                continue;
            }
            $day = $d['day'] ?? $d['day_num'] ?? null;
            if ($day === null || $day === '') {
                continue; // sense dia → no es pot ubicar
            }
            $day = (int) $day;

            $start = isset($d['start']) && $d['start'] !== '' ? substr((string) $d['start'], 0, 5) : null;
            $end = isset($d['end']) && $d['end'] !== '' ? substr((string) $d['end'], 0, 5) : null;

            $active = array_key_exists('active', $d)
                ? (bool) $d['active']
                : ($start !== null && $end !== null);

            // Actiu sense horari complet no és vàlid
            if ($active && ($start === null || $end === null)) {
                $active = false;
            }

            $entry = ['day' => $day, 'active' => $active, 'start' => $start, 'end' => $end];
            if (! empty($d['name'])) {
                $entry['name'] = $d['name'];
            }
            $out[] = $entry;
        }

        return $out;
    }
}
