<?php

namespace App\Services;

use App\Models\QuadrantDia;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;

/**
 * L'horari d'una persona un dia concret: el del quadrant si aquell dia hi és (RRHH l'ha canviat),
 * i si no, el de la plantilla setmanal de la fitxa, com sempre. És l'única porta per saber «quin
 * horari tocava»: fitxatges (trams), aprovació automàtica, avís de marge, pausa i recordatoris.
 *
 * Els trams són [['start' => 'HH:MM', 'end' => 'HH:MM'], ...], ordenats; un dia lliure és [].
 * Les hores són de Madrid sense zona, com start_time i end_time dels fitxatges.
 */
final class HorariDia
{
    /** Dies del quadrant ja llegits: "user|AAAA-MM" => ['t' => quan, 'dies' => [dia => fila]]. */
    private static array $memo = [];

    public static function oblida(): void
    {
        self::$memo = [];
    }

    public static function trams(?User $user, string $dia): array
    {
        if (! $user) return [];
        $dia = substr($dia, 0, 10);
        $q = self::delQuadrant($user->id, $dia);
        if ($q !== null) {
            return $q['lliure'] ? [] : self::neteja($q['trams']);
        }

        return self::dePlantilla($user->workSchedule, $dia);
    }

    /** Té el dia canviat al quadrant (encara que sigui lliure)? */
    public static function teQuadrant(int $userId, string $dia): bool
    {
        return self::delQuadrant($userId, substr($dia, 0, 10)) !== null;
    }

    /** Trams de la plantilla setmanal per al dia de la setmana d'aquella data. */
    public static function dePlantilla(?WorkSchedule $plantilla, string $dia): array
    {
        if (! $plantilla) return [];
        $diaSetmana = Carbon::parse(substr($dia, 0, 10), 'Europe/Madrid')->dayOfWeek; // 0=Dg..6=Ds

        return self::neteja(collect(WorkSchedule::normalizeDays($plantilla->days))
            ->filter(fn ($d) => $d['day'] === $diaSetmana && $d['active'])
            ->map(fn ($d) => ['start' => $d['start'], 'end' => $d['end']])
            ->all());
    }

    /** Els trams com a [inici, fi] de Carbon aquell dia (hora de Madrid). */
    public static function finestres(?User $user, string $dia): array
    {
        $dia = substr($dia, 0, 10);

        return array_map(fn ($t) => [
            Carbon::parse("{$dia} {$t['start']}", 'Europe/Madrid'),
            Carbon::parse("{$dia} {$t['end']}", 'Europe/Madrid'),
        ], self::trams($user, $dia));
    }

    public static function minuts(array $trams): int
    {
        return array_sum(array_map(fn ($t) => self::aMinuts($t['end']) - self::aMinuts($t['start']), $trams));
    }

    /** Valida, ordena i treu els trams buits o girats. */
    public static function neteja($trams): array
    {
        return collect(is_array($trams) ? $trams : [])
            ->filter(fn ($t) => is_array($t) && ! empty($t['start']) && ! empty($t['end']))
            ->map(fn ($t) => ['start' => substr((string) $t['start'], 0, 5), 'end' => substr((string) $t['end'], 0, 5)])
            ->filter(fn ($t) => self::aMinuts($t['end']) > self::aMinuts($t['start']))
            ->sortBy('start')->values()->all();
    }

    private static function aMinuts(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [0, 0]);

        return $h * 60 + $m;
    }

    /** La fila del quadrant d'aquell dia, o null. Es llegeix el mes sencer d'un cop. */
    private static function delQuadrant(int $userId, string $dia): ?array
    {
        $clau = $userId . '|' . substr($dia, 0, 7);
        // Un procés llarg (cua, planificador) no s'ha de quedar amb un quadrant vell: 60 s com a màxim.
        if (! isset(self::$memo[$clau]) || self::$memo[$clau]['t'] < time() - 60) {
            $inici = substr($dia, 0, 7) . '-01';
            $fi = Carbon::parse($inici)->endOfMonth()->toDateString();
            self::$memo[$clau] = ['t' => time(), 'dies' => QuadrantDia::where('user_id', $userId)
                ->whereBetween('dia', [$inici, $fi])->get()
                ->mapWithKeys(fn ($q) => [substr($q->getRawOriginal('dia'), 0, 10) => ['trams' => $q->trams, 'lliure' => $q->lliure]])
                ->all()];
        }

        return self::$memo[$clau]['dies'][$dia] ?? null;
    }
}
