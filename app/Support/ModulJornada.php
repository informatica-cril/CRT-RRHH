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
     * TRAMS: cada registre de l'historial és un tram (des de la seva data fins al dia abans del
     * següent). El màxim anual es reparteix pels dies laborables de cada tram: 20 h fins al
     * juliol i 100% des del 13-07 ≈ cada % per la part de l'any que li toca.
     *
     * SALDO: per a cada tram i cada mes, el que tocava (mòdul diari del tram × dies laborables
     * sense absències aprovades) contra el que s'ha fet de jornada ordinària. Les
     * complementàries i extraordinàries autoritzades van a part (RRHH vol veure el minutatge
     * de més de cada mes sense barrejar-lo amb el que ja s'ha acceptat). La diferència
     * s'acumula dins del tram i a l'any: el còmput és anual.
     * El que toca només es compta fins avui: un mes que encara no ha passat no surt en negatiu.
     *
     * Els dies SENSE % de contracte (abans del primer registre) no compten enlloc: sense saber
     * què li tocava, tot el que s'hi ha fitxat sortia com a hores de més. Surten en un avís.
     */
    public static function perUsuari(User $user, int $any): array
    {
        $historial = JornadaContracte::where('user_id', $user->id)->orderBy('valid_from')->get();
        $dies = self::diesLaborables($any);
        $avui = now('Europe/Madrid')->toDateString();
        $moduls = self::moduls(count($dies));
        $anualMin = (int) round(Jornada::ANUAL_COMPLETA_H * 60);
        $absents = self::diesAmbAbsencia($user->id, $any);
        $fetDia = self::minutsFetsPerDia($user->id, $any);

        // Trams de l'any segons l'historial
        $trams = [];
        foreach ($historial as $i => $h) {
            $des = max($h->valid_from->toDateString(), "{$any}-01-01");
            $seguent = $historial[$i + 1] ?? null;
            $fins = $seguent ? $seguent->valid_from->copy()->subDay()->toDateString() : "{$any}-12-31";
            $fins = min($fins, "{$any}-12-31");
            if ($des > $fins) continue;
            $trams[] = ['c' => self::centessimes($h->pct), 'des' => $des, 'fins' => $fins, 'motiu' => $h->motiu];
        }
        $tramDe = function (string $d) use (&$trams): ?int {
            foreach ($trams as $i => $t) {
                if ($d >= $t['des'] && $d <= $t['fins']) return $i;
            }

            return null;
        };

        $buit = fn () => ['esperat' => 0, 'total' => 0, 'compl' => 0, 'extra' => 0, 'pendent' => 0];
        // [tram][mes] acumuladors
        $acc = [];
        foreach ($trams as $i => $t) {
            $diesTram = array_values(array_filter($dies, fn ($d) => $d >= $t['des'] && $d <= $t['fins']));
            $trams[$i]['dies_laborables'] = count($diesTram);
            $trams[$i]['modul_diari_min'] = intdiv(self::maximAnualMin($t['c']), $moduls);
            $trams[$i]['maxim_min'] = intdiv($anualMin * $t['c'] * count($diesTram), 10000 * count($dies));
            for ($m = (int) substr($t['des'], 5, 2); $m <= (int) substr($t['fins'], 5, 2); $m++) {
                $acc[$i][$m] = $buit();
            }
            foreach ($diesTram as $d) {
                if ($d > $avui || isset($absents[$d])) continue;
                $acc[$i][(int) substr($d, 5, 2)]['esperat'] += $trams[$i]['modul_diari_min'];
            }
        }

        $senseContracte = ['ordinari_min' => 0, 'des' => null, 'fins' => null];
        foreach ($fetDia as $d => $f) {
            $i = $tramDe($d);
            if ($i === null) {
                $senseContracte['ordinari_min'] += max(0, $f['total'] - $f['compl'] - $f['extra']);
                $senseContracte['des'] = $senseContracte['des'] ? min($senseContracte['des'], $d) : $d;
                $senseContracte['fins'] = $senseContracte['fins'] ? max($senseContracte['fins'], $d) : $d;
                continue;
            }
            $m = (int) substr($d, 5, 2);
            foreach (['total', 'compl', 'extra', 'pendent'] as $k) $acc[$i][$m][$k] += $f[$k];
        }

        // Files per tram i per mes, i l'any sencer sumant els trams
        $any12 = array_fill(1, 12, null);
        $acumulatAny = 0;
        $filaMes = function (array $a, int $m, string $iniciMes, int &$acumulat) use ($avui) {
            $ordinari = max(0, $a['total'] - $a['compl'] - $a['extra']);
            $passat = $iniciMes <= $avui;
            $diferencia = $ordinari - $a['esperat'];
            if ($passat) $acumulat += $diferencia;

            return [
                'mes' => $m,
                'esperat_min' => $a['esperat'],
                'ordinari_min' => $ordinari,
                'diferencia_min' => $passat ? $diferencia : null,
                'acumulat_min' => $passat ? $acumulat : null,
                'complementaries_min' => $a['compl'],
                'extres_min' => $a['extra'],
                'pendents_validar_min' => $a['pendent'],
            ];
        };
        foreach ($trams as $i => $t) {
            $acumulat = 0;
            $files = [];
            foreach ($acc[$i] as $m => $a) {
                $iniciMes = max($t['des'], sprintf('%04d-%02d-01', $any, $m));
                $files[] = $filaMes($a, $m, $iniciMes, $acumulat);
                $any12[$m] = $any12[$m] ? array_map(fn ($x, $y) => $x + $y, $any12[$m], $a) : $a;
                $any12[$m] = array_combine(array_keys($a), array_values($any12[$m]));
            }
            $trams[$i] = [
                'pct' => $t['c'] / 100,
                'hores_setmanals' => round($t['c'] / 100 * Jornada::SETMANA_COMPLETA_H / 100, 2),
                'des' => $t['des'],
                'fins' => $t['fins'],
                'motiu' => $t['motiu'],
                'dies_laborables' => $trams[$i]['dies_laborables'],
                'modul_diari_min' => $trams[$i]['modul_diari_min'],
                'maxim_min' => $trams[$i]['maxim_min'],
                'mesos' => $files,
                'totals' => [
                    'esperat_min' => array_sum(array_column($files, 'esperat_min')),
                    'ordinari_min' => array_sum(array_column($files, 'ordinari_min')),
                    'saldo_min' => $acumulat,
                    'complementaries_min' => array_sum(array_column($files, 'complementaries_min')),
                    'extres_min' => array_sum(array_column($files, 'extres_min')),
                ],
            ];
        }

        $mesos = [];
        for ($m = 1; $m <= 12; $m++) {
            $mesos[] = $filaMes($any12[$m] ?? $buit(), $m, sprintf('%04d-%02d-01', $any, $m), $acumulatAny);
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
                'acumulat_min' => $acumulatAny,
                'complementaries_min' => array_sum(array_column($mesos, 'complementaries_min')),
                'extres_min' => array_sum(array_column($mesos, 'extres_min')),
            ],
            // Fitxatges en dies sense % de contracte: no compten al saldo.
            'sense_contracte' => $senseContracte['ordinari_min'] > 0 ? $senseContracte : null,
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
     * Minuts fets per dia (fitxatges tancats i no rebutjats). Les complementàries i
     * extraordinàries són les hores imputades a codis d'autorització, com a Codis.
     */
    private static function minutsFetsPerDia(int $userId, int $any): array
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

        $dies = [];
        foreach ($files as $f) {
            $d = substr((string) $f->date, 0, 10);
            $dies[$d] ??= ['total' => 0, 'compl' => 0, 'extra' => 0, 'pendent' => 0];
            $total = (int) round(((float) $f->total_hours_worked) * 60);
            $dies[$d]['total'] += $total;
            if ($f->status === 'pending') $dies[$d]['pendent'] += $total;
            if ($f->tipus_codi !== null) {
                $hores = (float) $f->extra_hours_authorized ?: ((float) $f->complementary_minutes) / 60;
                $dies[$d][$f->tipus_codi === 'extraordinaria' ? 'extra' : 'compl'] += (int) round($hores * 60);
            }
        }

        return $dies;
    }
}
