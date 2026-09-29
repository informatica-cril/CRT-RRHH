<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Qui treballa cada dia d'una setmana, i en quin horari.
 *
 * PER QUÈ EXISTEIX (petició de Direcció, 31-07-2026)
 *   RRHH necessita saber QUI ESTÀ DISPONIBLE. Fins ara això s'obtenia mirant dues coses per
 *   separat —l'horari contractat d'una banda i les absències aprovades de l'altra— i creuant-
 *   les de cap. Amb 75 treballadors i 55 horaris diferents, això no es fa de cap.
 *
 * COM ES CALCULA CADA CASELLA, per ordre de prioritat
 *   1. FESTIU        · la data és a `holidays` → ningú treballa, i es diu quin festiu és.
 *   2. ABSENT        · hi ha una absència APROVADA (`approved = 1`) que cobreix la data.
 *                      Les pendents (`approved = null`) i les denegades (`0`) NO compten:
 *                      qui té vacances demanades i sense aprovar segueix havent de venir.
 *   3. NO TREBALLA   · l'horari del contracte no té aquell dia de la setmana.
 *   4. TREBALLA      · amb els trams horaris del seu contracte.
 *
 * ⚠️ LÍMITS QUE S'HAN DE SABER EN MIRAR LA TAULA
 *   · Diu qui HAURIA de treballar segons contracte i absències, no qui ha fitxat. Per al
 *     que ha passat de veritat hi ha el control de presència (`work_logs`).
 *   · Un treballador sense horari assignat surt marcat com a tal, no com a disponible: no
 *     tenir horari no vol dir estar lliure, vol dir que no ho sabem.
 *   · Les baixes mèdiques obertes sense data de fi es tracten com a absència fins a
 *     `end_date`; si no n'hi ha, cobreixen tota la setmana consultada.
 */
class DisponibilitatService
{
    /** Estats possibles d'una casella. */
    public const TREBALLA    = 'treballa';
    public const ABSENT      = 'absent';
    public const FESTIU      = 'festiu';
    public const NO_TREBALLA = 'no_treballa';
    public const SENSE_HORARI = 'sense_horari';

    /**
     * @param  string  $dilluns  Data del dilluns de la setmana (Y-m-d).
     * @param  array   $filtres  zona_id, ubicacio_id, centre_id, rol (tots opcionals)
     */
    public function setmana(string $dilluns, array $filtres = []): array
    {
        $inici = CarbonImmutable::parse($dilluns)->startOfWeek(CarbonImmutable::MONDAY);
        $fi    = $inici->addDays(6);

        $dies = [];
        for ($i = 0; $i < 7; $i++) { $dies[] = $inici->addDays($i); }

        /* Festius de la setmana, indexats per data per no consultar-los dins del bucle. */
        $festius = Holiday::whereBetween('date', [$inici->toDateString(), $fi->toDateString()])
            ->get()
            ->keyBy(fn ($h) => CarbonImmutable::parse($h->date)->toDateString());

        $usuaris = $this->usuaris($filtres);

        /* Absències aprovades que TOQUEN la setmana. La condició és de solapament de rangs
           (comença abans que acabi la setmana i acaba després que comenci), no d'estar-hi
           continguda: una baixa de tres mesos no comença ni acaba dins d'aquests set dies. */
        $absencies = Absence::with('absenceType')
            ->where('approved', true)
            ->whereIn('user_id', $usuaris->pluck('id'))
            ->where('start_date', '<=', $fi->toDateString())
            ->where(function ($q) use ($inici) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $inici->toDateString());
            })
            ->get()
            ->groupBy('user_id');

        $files = $usuaris->map(function (User $u) use ($dies, $festius, $absencies) {
            return $this->fila($u, $dies, $festius, $absencies->get($u->id) ?? collect());
        })->values();

        return [
            'setmana'  => [
                'inici'   => $inici->toDateString(),
                'fi'      => $fi->toDateString(),
                'etiqueta' => 'Setmana ' . $inici->isoWeek() . ' · ' . $inici->format('d/m') . ' – ' . $fi->format('d/m/Y'),
            ],
            'dies'     => array_map(fn (CarbonImmutable $d) => [
                'data'   => $d->toDateString(),
                'nom'    => $this->nomDia((int) $d->dayOfWeekIso),
                'festiu' => $festius->get($d->toDateString())?->name,
            ], $dies),
            'filtres'  => $filtres,
            'files'    => $files,
            'resum'    => $this->resum($files, $dies),
        ];
    }

    /**
     * La mateixa informació, en la forma que necessita la PROGRAMACIÓ de domi.
     *
     * Diferències respecte de `setmana()`, i totes són a posta:
     *   · va per rang de dates lliure, no per setmana natural: una programació mira endavant.
     *   · NO hi surt el motiu de l'absència, només que n'hi ha. domi ha de saber que la
     *     persona no hi és; per què no hi és és dada de salut i es queda a RRHH.
     *   · va per identificador de RRHH, que és l'ID únic entre les dues aplicacions.
     *
     * @param  array<int>  $ids  IDs de RRHH; buit = tota la plantilla activa.
     */
    public function perDomi(string $desde, string $fins, array $ids = []): array
    {
        $d = CarbonImmutable::parse($desde);
        $f = CarbonImmutable::parse($fins);

        $q = User::query()->with('workSchedule')->where('active', true)->where('role', '!=', 'service');
        if ($ids) { $q->whereIn('id', $ids); }
        $usuaris = $q->get();

        $festius = Holiday::whereBetween('date', [$desde, $fins])->pluck('date')
            ->map(fn ($x) => CarbonImmutable::parse($x)->toDateString())->all();

        $absencies = Absence::where('approved', true)
            ->whereIn('user_id', $usuaris->pluck('id'))
            ->where('start_date', '<=', $fins)
            ->where(function ($q) use ($desde) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $desde);
            })
            ->get(['user_id', 'start_date', 'end_date'])
            ->groupBy('user_id');

        $declaracions = \App\Models\ComplementarySlotDeclaration::whereIn('user_id', $usuaris->pluck('id'))
            ->where('month', '>=', $d->startOfMonth()->toDateString())
            ->where('month', '<=', $f->startOfMonth()->toDateString())
            ->get()->groupBy('user_id');

        $persones = $usuaris->map(function (User $u) use ($d, $f, $absencies, $declaracions) {
            $trams = $this->tramsPerDia($u->workSchedule?->days);
            $noDisponibles = [];

            foreach (($absencies->get($u->id) ?? collect()) as $a) {
                $ini = CarbonImmutable::parse($a->start_date)->max($d);
                $fi  = $a->end_date ? CarbonImmutable::parse($a->end_date)->min($f) : $f;
                for ($x = $ini; $x <= $fi; $x = $x->addDay()) { $noDisponibles[] = $x->toDateString(); }
            }

            return [
                'rrhh_id'      => $u->id,
                'nom'          => $u->name,
                'te_horari'    => $u->workSchedule !== null,
                /* Trams per dia de la setmana (1 = dilluns). Amb això domi sap no només SI
                   pot programar, sinó ENTRE QUINES HORES. */
                'horari_setmanal' => $trams,
                'absent'       => array_values(array_unique($noDisponibles)),
                /* Franges complementàries del mes natural, declarades per la persona aquí.
                   domi les rep: no es declaren mai a domi. */
                'complementaries' => ($declaracions->get($u->id) ?? collect())->map(fn ($c) => [
                    'mes'    => $c->month->toDateString(),
                    'estat'  => $c->status,
                    'hores'  => (float) $c->hours,
                    'franges' => $c->slots,
                    'motiu_retirada' => $c->withdrawal_reason,
                ])->values(),
            ];
        })->values();

        return [
            'desde'    => $desde,
            'fins'     => $fins,
            'festius'  => array_values(array_unique($festius)),
            'persones' => $persones,
            /* Es diu explícitament, perquè qui consumeixi això no ho hagi de deduir. */
            'nota'     => 'Absències APROVADES només. El motiu no s\'hi inclou a posta.',
        ];
    }

    /** Treballadors que entren al quadre, ja filtrats. */
    private function usuaris(array $filtres): Collection
    {
        $q = User::query()
            ->with(['workSchedule', 'zones:id,name', 'workLocations:id,name', 'ambulatoryCenters:id,name',
                    'lots:id,code,name', 'departments:id,code,name'])
            ->where('active', true)
            /* El personal de sistema no és plantilla: no ha de sortir a un quadre de torns. */
            ->where('role', '!=', 'service');

        if (!empty($filtres['lot_id'])) {
            $q->whereHas('lots', fn ($l) => $l->where('lots.id', $filtres['lot_id']));
        }
        if (!empty($filtres['departament_id'])) {
            $q->whereHas('departments', fn ($d) => $d->where('departments.id', $filtres['departament_id']));
        }
        if (!empty($filtres['zona_id'])) {
            $q->whereHas('zones', fn ($z) => $z->where('zones.id', $filtres['zona_id']));
        }
        if (!empty($filtres['ubicacio_id'])) {
            $q->whereHas('workLocations', fn ($l) => $l->where('work_locations.id', $filtres['ubicacio_id']));
        }
        if (!empty($filtres['centre_id'])) {
            $q->whereHas('ambulatoryCenters', fn ($c) => $c->where('ambulatory_centers.id', $filtres['centre_id']));
        }
        if (!empty($filtres['rol'])) {
            $q->where('role', $filtres['rol']);
        }

        return $q->orderBy('name')->get();
    }

    /** @param  Collection<int,CarbonImmutable>|array  $dies */
    private function fila(User $u, array $dies, Collection $festius, Collection $absencies): array
    {
        $horari = $u->workSchedule;
        $trams  = $this->tramsPerDia($horari?->days);

        /* Horari DESAGREGAT: el contracte i, a part, les complementàries. No s'hi sumen mai.
           La jornada completa no en fa cap (només extraordinàries, 80 h/any a 1,25×); el
           parcial sense pacte tampoc. El sostre ja ve retallat pel tope de 1726 h. Les
           complementàries setmanals es reparteixen a parts iguals entre els dies que ja
           treballa, i es pinten com un tram que allarga el darrer tram de contracte. */
        $setmanalContracte = (float) ($horari?->total_hours_weekly ?? 0);
        $completaJornada   = $u->isFullTime();
        $ratioUsable       = $completaJornada ? 0.0 : $u->usableComplementaryRatio();
        $teComplementaries = !$completaJornada && $u->pacte_complementaries && $ratioUsable > 0.0001;
        $complSetH         = $teComplementaries ? round($setmanalContracte * $ratioUsable, 2) : 0.0;
        $diesFeiners       = count($trams);   // dies de la setmana amb tram de contracte
        $complMinDia       = ($teComplementaries && $diesFeiners > 0)
            ? (int) round($complSetH * 60 / $diesFeiners) : 0;

        $caselles = [];
        $minuts   = 0;

        foreach ($dies as $d) {
            $data = $d->toDateString();

            if ($festiu = $festius->get($data)) {
                $caselles[] = ['data' => $data, 'estat' => self::FESTIU, 'detall' => $festiu->name];
                continue;
            }

            $abs = $absencies->first(function ($a) use ($data) {
                $desde = CarbonImmutable::parse($a->start_date)->toDateString();
                $fins  = $a->end_date ? CarbonImmutable::parse($a->end_date)->toDateString() : null;
                return $desde <= $data && ($fins === null || $fins >= $data);
            });
            if ($abs) {
                $caselles[] = ['data' => $data, 'estat' => self::ABSENT,
                               'detall' => $abs->absenceType->name ?? 'Absència aprovada'];
                continue;
            }

            if (!$horari) {
                /* Sense horari NO és «disponible»: és «no ho sabem». Barrejar les dues coses
                   faria comptar com a disponible qui potser no ho està. */
                $caselles[] = ['data' => $data, 'estat' => self::SENSE_HORARI, 'detall' => null];
                continue;
            }

            $delDia = $trams[(int) $d->dayOfWeekIso] ?? [];
            if (!$delDia) {
                $caselles[] = ['data' => $data, 'estat' => self::NO_TREBALLA, 'detall' => null];
                continue;
            }

            foreach ($delDia as $t) { $minuts += $this->minuts($t['start'], $t['end']); }
            $cela = ['data' => $data, 'estat' => self::TREBALLA,
                     'detall' => implode(' i ', array_map(fn ($t) => $t['start'] . '–' . $t['end'], $delDia)),
                     'trams'  => $delDia];
            /* Tram complementari del dia, desagregat: allarga el darrer tram de contracte. */
            if ($complMinDia > 0) {
                $fiContracte = end($delDia)['end'];
                $cela['compl_tram'] = ['start' => $fiContracte, 'end' => $this->sumaMinuts($fiContracte, $complMinDia)];
            }
            $caselles[] = $cela;
        }

        return [
            'id'          => $u->id,
            'nom'         => $u->name,
            'rol'         => $u->role,
            'horari'      => $horari?->name,
            'hores_setmana' => round($minuts / 60, 2),
            /* Bloc complementari, desagregat del contracte. */
            'compl_pacte'        => $teComplementaries,
            'compl_ratio_pct'    => $teComplementaries ? (int) round($ratioUsable * 100) : 0,
            'compl_hores_setmana' => $complSetH,
            'nomes_extraordinaries' => $completaJornada,
            'lots'        => $u->lots->pluck('code')->all(),
            'departaments' => $u->departments->pluck('name')->all(),
            'zones'       => $u->zones->pluck('name')->all(),
            'ubicacions'  => $u->workLocations->pluck('name')->all(),
            'centres'     => $u->ambulatoryCenters->pluck('name')->all(),
            'dies'        => $caselles,
        ];
    }

    /**
     * El JSON `days` d'un horari: [{day:1, name:"Dilluns", start:"08:00", end:"14:00"}, ...].
     * Un mateix dia hi pot sortir més d'un cop (jornada partida), i per això s'agrupa en
     * comptes de indexar-lo: indexar-lo per `day` es menjaria el segon tram i les jornades
     * partides comptarien la meitat d'hores.
     *
     * @return array<int,array<int,array{start:string,end:string}>>
     */
    private function tramsPerDia($days): array
    {
        if (is_string($days)) { $days = json_decode($days, true); }
        if (!is_array($days)) { return []; }

        $out = [];
        foreach ($days as $d) {
            $dia = (int) ($d['day'] ?? 0);
            if ($dia < 1 || $dia > 7) { continue; }
            /* Alguns horaris porten `active`; si hi és i és fals, el dia no compta. */
            if (array_key_exists('active', $d) && !$d['active']) { continue; }
            $inici = (string) ($d['start'] ?? '');
            $fi    = (string) ($d['end'] ?? '');
            if ($inici === '' || $fi === '') { continue; }
            $out[$dia][] = ['start' => $inici, 'end' => $fi];
        }
        foreach ($out as &$trams) {
            usort($trams, fn ($a, $b) => strcmp($a['start'], $b['start']));
        }
        return $out;
    }

    /** Suma minuts a una hora «HH:MM» i la torna formatada (topada a 23:59). */
    private function sumaMinuts(string $hora, int $min): string
    {
        [$h, $m] = array_pad(explode(':', $hora), 2, '0');
        $total = min(23 * 60 + 59, (int) $h * 60 + (int) $m + $min);
        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function minuts(string $inici, string $fi): int
    {
        [$h1, $m1] = array_pad(explode(':', $inici), 2, '0');
        [$h2, $m2] = array_pad(explode(':', $fi), 2, '0');
        $d = ((int) $h2 * 60 + (int) $m2) - ((int) $h1 * 60 + (int) $m1);
        /* Un tram que acaba abans de començar és un torn de nit (22:00–06:00). */
        return $d >= 0 ? $d : $d + 24 * 60;
    }

    private function nomDia(int $iso): string
    {
        return ['Dilluns', 'Dimarts', 'Dimecres', 'Dijous', 'Divendres', 'Dissabte', 'Diumenge'][$iso - 1] ?? '';
    }

    /** Comptadors per dia, que és el que RRHH mira primer. */
    private function resum(Collection $files, array $dies): array
    {
        $perDia = [];
        foreach ($dies as $i => $d) {
            $c = [self::TREBALLA => 0, self::ABSENT => 0, self::FESTIU => 0,
                  self::NO_TREBALLA => 0, self::SENSE_HORARI => 0];
            foreach ($files as $f) {
                $estat = $f['dies'][$i]['estat'] ?? self::NO_TREBALLA;
                $c[$estat] = ($c[$estat] ?? 0) + 1;
            }
            $perDia[] = ['data' => $d->toDateString()] + $c;
        }
        return [
            'treballadors' => $files->count(),
            'sense_horari' => $files->filter(fn ($f) => $f['horari'] === null)->count(),
            'per_dia'      => $perDia,
        ];
    }
}
