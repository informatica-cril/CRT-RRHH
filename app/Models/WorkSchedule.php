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

    /**
     * Els trams actius per dia (0=Dg..6=Ds), ordenats i amb els que es trepitgen ajuntats.
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    public static function tramsPerDia($days): array
    {
        // «8:00» i «08:00» són la mateixa hora: es comparen sempre amb dos dígits.
        $hhmm = fn (?string $h) => $h === null ? null : vsprintf('%02d:%02d', array_map('intval', explode(':', $h) + [0, 0]));
        $per = [];
        foreach (self::normalizeDays($days) as $d) {
            [$a, $b] = [$hhmm($d['start']), $hhmm($d['end'])];
            if ($d['active'] && $a !== null && $b > $a) $per[$d['day']][] = [$a, $b];
        }
        foreach ($per as $dia => $trams) {
            sort($trams);
            $junts = [];
            foreach ($trams as [$a, $b]) {
                $u = count($junts) - 1;
                if ($u >= 0 && $a <= $junts[$u][1]) {
                    $junts[$u][1] = max($junts[$u][1], $b);
                } else {
                    $junts[] = [$a, $b];
                }
            }
            $per[$dia] = $junts;
        }
        ksort($per);

        return $per;
    }

    /** Clau de l'horari pels seus trams: dues plantilles amb la mateixa clau fan el mateix horari. */
    public static function signatura($days): string
    {
        $parts = [];
        foreach (self::tramsPerDia($days) as $dia => $trams) {
            foreach ($trams as [$a, $b]) $parts[] = "{$dia}:{$a}-{$b}";
        }

        return implode('|', $parts);
    }

    /** Minuts setmanals de l'horari tal com està (no el número que diu el nom). */
    public static function minutsSetmanals($days): int
    {
        $min = fn (string $h) => (int) substr($h, 0, 2) * 60 + (int) substr($h, 3, 2);
        $total = 0;
        foreach (self::tramsPerDia($days) as $trams) {
            foreach ($trams as [$a, $b]) $total += $min($b) - $min($a);
        }

        return $total;
    }
}
