<?php

namespace App\Support;

use App\Models\Absence;
use App\Models\Holiday;
use App\Models\JornadaContracte;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Jornada d'un % de contracte repartida en mòduls diaris (el full «revisió de jornades» de RRHH).
 *
 *   màxim anual = 1726 h × %                       (art. 31.2 del conveni: mana la jornada anual)
 *   dies laborables = dilluns-divendres de l'any − festius entre setmana (taula de Festius)
 *   mòduls = dies laborables − vacances (22 laborables per a tothom, RRHH 16-10-2026)
 *   mòdul diari = màxim anual ÷ mòduls, arrodonit avall al minut
 *   resta = minuts del màxim que no hi caben: PENDENTS de repartir, no és excés.
 *
 * Es divideix entre els mòduls i no entre els dies laborables: els descansos que computen
 * com a treballats (art. 35 i 38) ja hi són dins, i restar-los a part els descomptaria dues vegades.
 * La setmana de referència són cinc mòduls, no una setmana fixa exigible.
 *
 * Tot en minuts enters: amb decimals, Excel donava 59.546 min al 57,5% en lloc de 59.547.
 */
final class ModulJornada
{
    public static function calcula(float $pct, int $any): array
    {
        $centessimes = self::centessimes($pct);
        $maximMin = self::maximAnualMin($centessimes);

        $laborables = count(self::diesLaborables($any));
        $festius = self::festiusEntreSetmana($any);
        $moduls = self::moduls($laborables);
        $modulMin = intdiv($maximMin, $moduls);

        return [
            'any' => $any,
            'pct' => round($centessimes / 100, 2),
            'jornada_anual_completa_h' => Jornada::ANUAL_COMPLETA_H,
            'maxim_anual_min' => $maximMin,
            'dies_laborables' => $laborables,
            'festius_entre_setmana' => $festius,
            'vacances_laborables' => Jornada::VACANCES_LABORABLES,
            'moduls' => $moduls,
            'modul_diari_min' => $modulMin,
            'setmana_referencia_min' => intdiv($maximMin * 5, $moduls),
            'resta_min' => $maximMin - $modulMin * $moduls,
            // Sense festius de l'any, els dies laborables surten inflats i el mòdul, curt.
            'avis' => $festius === 0
                ? "No hi ha festius de {$any} registrats a Festius: els dies laborables no en descompten cap."
                : null,
        ];
    }

    /**
     * L'any d'una persona amb el seu historial de %.
     *
     * TRAMS: si el % canvia a mig any (ampliació o reducció), el màxim anual es reparteix pels
     * dies laborables de cada tram: 75% fins al juny i 100% des del juliol ≈ la meitat de cada.
     *
     * SALDO MENSUAL: per a cada mes, el que tocava (mòdul diari del % de cada dia × dies
     * laborables sense absències aprovades) contra el que s'ha fet de jornada ordinària. Les
     * complementàries i extraordinàries autoritzades van a part (RRHH vol veure el minutatge
     * de més de cada mes sense barrejar-lo amb el que ja s'ha acceptat). La diferència
     * s'acumula: uns mesos se'n fa de més i uns altres de menys, i el còmput és anual.
     * El que toca només es compta fins avui: un mes que encara no ha passat no surt en negatiu.
     */
    public static function perUsuari(User $user, int $any): array
    {
        $historial = JornadaContracte::where('user_id', $user->id)->orderBy('valid_from')->get();
        $dies = self::diesLaborables($any);
        $avui = now('Europe/Madrid')->toDateString();

        // % vigent de cada dia laborable (null = encara no hi havia % de contracte)
        $pctDia = [];
        foreach ($dies as $d) {
            $vigent = $historial->filter(fn ($h) => $h->valid_from->toDateString() <= $d)->last();
            $pctDia[$d] = $vigent ? self::centessimes($vigent->pct) : null;
        }

        $moduls = self::moduls(count($dies));
        $anualMin = (int) round(Jornada::ANUAL_COMPLETA_H * 60);
        $trams = [];
        foreach ($pctDia as $d => $c) {
            if ($c === null) continue;
            $ultim = count($trams) - 1;
            if ($ultim >= 0 && $trams[$ultim]['c'] === $c) {
                $trams[$ultim]['fins'] = $d;
                $trams[$ultim]['dies'] ++;
            } else {
                $trams[] = ['c' => $c, 'des' => $d, 'fins' => $d, 'dies' => 1];
            }
        }
        $trams = array_map(fn ($t) => [
            'pct' => $t['c'] / 100,
            'des' => $t['des'],
            'fins' => $t['fins'],
            'dies_laborables' => $t['dies'],
            'maxim_min' => intdiv($anualMin * $t['c'] * $t['dies'], 10000 * count($dies)),
            'modul_diari_min' => intdiv(self::maximAnualMin($t['c']), $moduls),
        ], $trams);

        $absents = self::diesAmbAbsencia($user->id, $any);
        $fet = self::minutsFets($user->id, $any);

        $mesos = [];
        $acumulat = 0;
        for ($m = 1; $m <= 12; $m++) {
            $esperat = 0;
            foreach ($pctDia as $d => $c) {
                if ($c === null || (int) substr($d, 5, 2) !== $m || $d > $avui || isset($absents[$d])) continue;
                $esperat += intdiv(self::maximAnualMin($c), $moduls);
            }
            $f = $fet[$m] ?? ['total' => 0, 'compl' => 0, 'extra' => 0, 'pendent' => 0];
            $ordinari = max(0, $f['total'] - $f['compl'] - $f['extra']);
            $diferencia = $ordinari - $esperat;
            $passat = sprintf('%04d-%02d-01', $any, $m) <= $avui;
            if ($passat) $acumulat += $diferencia;
            $mesos[] = [
                'mes' => $m,
                'esperat_min' => $esperat,
                'ordinari_min' => $ordinari,
                'diferencia_min' => $passat ? $diferencia : null,
                'acumulat_min' => $passat ? $acumulat : null,
                'complementaries_min' => $f['compl'],
                'extres_min' => $f['extra'],
                'pendents_validar_min' => $f['pendent'],
            ];
        }

        $vigent = $historial->filter(fn ($h) => $h->valid_from->toDateString() <= $avui)->last();

        return [
            'any' => $any,
            'vigent' => $vigent ? ['pct' => $vigent->pct, 'valid_from' => $vigent->valid_from->toDateString()] : null,
            'historial' => $historial->load('autor:id,name')->map(fn ($h) => [
                'id' => $h->id, 'pct' => $h->pct, 'valid_from' => $h->valid_from->toDateString(),
                'motiu' => $h->motiu, 'autor' => $h->autor?->name,
                'futur' => $h->valid_from->toDateString() > $avui,
            ])->values(),
            'retribucio_pct' => $user->retribucio_pct !== null ? (float) $user->retribucio_pct : null,
            'trams' => $trams,
            'maxim_anual_min' => array_sum(array_column($trams, 'maxim_min')),
            'mesos' => $mesos,
            'totals' => [
                'esperat_min' => array_sum(array_column($mesos, 'esperat_min')),
                'ordinari_min' => array_sum(array_column($mesos, 'ordinari_min')),
                'acumulat_min' => $acumulat,
                'complementaries_min' => array_sum(array_column($mesos, 'complementaries_min')),
                'extres_min' => array_sum(array_column($mesos, 'extres_min')),
            ],
        ];
    }

    private static function centessimes(float $pct): int
    {
        return (int) round($pct * 100); // 57,5% → 5750
    }

    private static function maximAnualMin(int $centessimes): int
    {
        return intdiv((int) round(Jornada::ANUAL_COMPLETA_H * 60) * $centessimes, 10000);
    }

    private static function moduls(int $laborables): int
    {
        return max(1, $laborables - Jornada::VACANCES_LABORABLES);
    }

    /** @return string[] dates Y-m-d de dilluns a divendres que no són festiu */
    private static function diesLaborables(int $any): array
    {
        $festius = Holiday::whereYear('date', $any)->get()
            ->map(fn ($h) => Carbon::parse($h->date)->toDateString())->flip();
        $dies = [];
        for ($d = Carbon::create($any, 1, 1); $d->year === $any; $d->addDay()) {
            if (! $d->isWeekend() && ! isset($festius[$d->toDateString()])) $dies[] = $d->toDateString();
        }

        return $dies;
    }

    private static function festiusEntreSetmana(int $any): int
    {
        return Holiday::whereYear('date', $any)->get()
            ->filter(fn ($h) => ! Carbon::parse($h->date)->isWeekend())
            ->count();
    }

    /** Dies amb una absència aprovada (vacances, permisos, baixes): no se'ls espera jornada. */
    private static function diesAmbAbsencia(int $userId, int $any): array
    {
        $dies = [];
        Absence::where('user_id', $userId)->where('approved', true)
            ->whereDate('start_date', '<=', "{$any}-12-31")->whereDate('end_date', '>=', "{$any}-01-01")
            ->get()->each(function ($a) use (&$dies, $any) {
                for ($d = $a->start_date->copy(); $d->lte($a->end_date); $d->addDay()) {
                    if ($d->year === $any) $dies[$d->toDateString()] = true;
                }
            });

        return $dies;
    }

    /**
     * Minuts fets per mes (fitxatges tancats i no rebutjats). Les complementàries i
     * extraordinàries són les hores imputades a codis d'autorització, com a Codis.
     */
    private static function minutsFets(int $userId, int $any): array
    {
        $files = DB::table('work_logs')
            ->leftJoin('authorization_codes as ac', 'ac.code', '=', 'work_logs.authorized_extra_code')
            ->where('work_logs.user_id', $userId)
            ->whereYear('work_logs.date', $any)
            ->whereNotNull('work_logs.end_time')
            ->where('work_logs.status', '<>', 'rejected')
            ->select('work_logs.date', 'work_logs.status', 'work_logs.total_hours_worked',
                'work_logs.extra_hours_authorized', 'work_logs.complementary_minutes', 'ac.type as tipus_codi')
            ->get();

        $mesos = [];
        foreach ($files as $f) {
            $m = (int) substr((string) $f->date, 5, 2);
            $mesos[$m] ??= ['total' => 0, 'compl' => 0, 'extra' => 0, 'pendent' => 0];
            $total = (int) round(((float) $f->total_hours_worked) * 60);
            $mesos[$m]['total'] += $total;
            if ($f->status === 'pending') $mesos[$m]['pendent'] += $total;
            if ($f->tipus_codi !== null) {
                $hores = (float) $f->extra_hours_authorized ?: ((float) $f->complementary_minutes) / 60;
                $mesos[$m][$f->tipus_codi === 'extraordinaria' ? 'extra' : 'compl'] += (int) round($hores * 60);
            }
        }

        return $mesos;
    }
}
