<?php

namespace App\Services;

use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Lògica de negoci del fichatge, extreta de WorkLogController (el controlador
 * conserva HTTP: validació, auditoria d'accés a ubicació i resposta).
 * Codi mogut TAL QUAL — el comportament el fixen els tests de caracterització
 * (WorkLogCaracteritzacioTest).
 */
class WorkLogService
{
    /**
     * Fetch current time from worldtimeapi.org (immune to server clock drift).
     * Falls back to Carbon::now() if the external API is unreachable.
     */
    public function getAccurateTime(): Carbon
    {
        try {
            // Timeout curt: cada fichatge crida aquí. connectTimeout baix per fallar
            // ràpid si worldtimeapi no respon (caigudes conegudes) i no penjar la petició.
            $response = Http::connectTimeout(1)->timeout(2)
                ->get('https://worldtimeapi.org/api/timezone/Europe/Madrid');
            if ($response->successful()) {
                return Carbon::parse($response->json('datetime'))->setTimezone('Europe/Madrid');
            }
        } catch (\Throwable $e) {
            // External API unreachable — fall back to local clock
        }

        return Carbon::now();
    }

    /**
     * ¿El día del fichaje es un día con horario activo en el cuadrante del trabajador?
     * (base per al bloqueig/bandera "fora de quadrant"). Un dia és laborable si té
     * start/end i no està explícitament desactivat.
     */
    public function isScheduledDay($user, Carbon $when): bool
    {
        $schedule = $user->workSchedule ?? null;
        if (! $schedule || ! is_array($schedule->days)) {
            return true; // sense horari definit → no bloquejar
        }
        $jsDay = (int) $when->copy()->setTimezone('Europe/Madrid')->dayOfWeek; // 0=Dg..6=Ds
        foreach ($schedule->days as $d) {
            $dd = $d['day'] ?? $d['day_num'] ?? null;
            if ((int) $dd === $jsDay && ($d['active'] ?? true) !== false && ! empty($d['start']) && ! empty($d['end'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿El treballador té una hora complementària autoritzada (codi vigent) en aquest moment?
     * Les hores complementàries amb codi són horari permès → el GPS no s'ha de bloquejar.
     */
    public function hasActiveAuthCode($user, Carbon $when): bool
    {
        return \App\Models\AuthorizationCode::where('revoked', false)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhereNull('user_id');
            })
            ->where('valid_from', '<=', $when)
            ->where('valid_to', '>=', $when)
            ->exists();
    }

    /**
     * Jornada particionada per hitos de domi: en tancar, la visita provisional oberta
     * (end==start, el fisio no va marcar sortida) es tanca al moment del tancament i
     * es crea el desplaçament final des de l'últim límit fins al tancament.
     */
    public function closeMilestoneTail(WorkLog $workLog): void
    {
        $end = Carbon::parse($workLog->getRawOriginal('end_time'), 'Europe/Madrid');
        $segments = \App\Models\WorkLogSegment::where('work_log_id', $workLog->id)
            ->orderBy('segment_number')->get();
        if ($segments->isEmpty() || $segments->whereNotNull('kind')->isEmpty()) {
            return; // no és una jornada per hitos
        }

        // Convenció BD: strings en hora de Madrid directa → per a diferències, parsejar
        // el RAW amb aquesta zona (veure WorkLogController::store()/update()).
        $openVisit = $segments->last(fn ($s) => $s->kind === 'visita' && $s->status === 'pending' && $s->end_time->eq($s->start_time));
        if ($openVisit) {
            $visitStart = Carbon::parse($openVisit->getRawOriginal('start_time'), 'Europe/Madrid');
            if ($visitStart->lt($end)) {
                $openVisit->update([
                    'end_time' => $end,
                    'duration_minutes' => (int) $visitStart->diffInMinutes($end),
                    'status' => 'pending', // sense sortida marcada → revisió humana
                ]);
                // Protocol d'audiència (EIPD C6): visita sense sortida = incidència
                \App\Http\Controllers\Api\WorkLogSegmentController::obrirAudiencia($openVisit->fresh());
                $segments = $segments->fresh();
            }
        }

        $rawLastEnd = $segments->map(fn ($s) => $s->getRawOriginal('end_time'))->filter()->max();
        $lastBoundary = Carbon::parse($rawLastEnd, 'Europe/Madrid');
        if ($lastBoundary->lt($end)) {
            \App\Models\WorkLogSegment::create([
                'work_log_id' => $workLog->id,
                'segment_number' => ((int) $segments->max('segment_number')) + 1,
                'kind' => 'desplacament',
                'start_time' => $lastBoundary, 'end_time' => $end,
                'in_zone' => true,
                'in_schedule' => $this->isScheduledDay($workLog->user, $end),
                'duration_minutes' => (int) $lastBoundary->diffInMinutes($end),
                'status' => 'pending',
            ]);
        }

        $workLog->refresh()->recalcularEfectivo();
    }

    /**
     * Segmenta automáticamente un fichaje en tramos (dentro/fuera zona, dentro/fuera horario).
     */
    public function segmentWorkLog(WorkLog $workLog, $user): void
    {
        // start_time/end_time es guarden en hora de Madrid directa (no UTC): veure
        // WorkLogController::store()/update().
        $startTime = Carbon::parse($workLog->getRawOriginal('start_time'), 'Europe/Madrid');
        $endTime = Carbon::parse($workLog->getRawOriginal('end_time'), 'Europe/Madrid');

        // Obtener horario del trabajador
        $workSchedule = $workLog->user->workSchedule;
        $dayOfWeek = $startTime->copy()->setTimezone('Europe/Madrid')->dayOfWeekIso;

        // Finestres de l'horari d'aquell dia, en hora de Madrid sense zona (com start_time i end_time).
        // - El dia es busca per la clau 'day' (0=Dg..6=Ds), no per la posició a l'array: un horari
        //   de 7 dies (de diumenge a dissabte) agafava el dia anterior.
        // - Un dia pot tenir diversos trams (jornada partida): abans només es mirava el primer i
        //   la resta de la jornada sortia «fora d'horari». Els trams que es toquen s'ajunten.
        $finestres = [];
        if ($workSchedule) {
            $localDate = $startTime->toDateString();
            $trams = collect(\App\Models\WorkSchedule::normalizeDays($workSchedule->days))
                ->filter(fn ($d) => $d['day'] === ($dayOfWeek % 7) && $d['active'])
                ->map(fn ($d) => [Carbon::parse("{$localDate} {$d['start']}", 'Europe/Madrid'),
                                  Carbon::parse("{$localDate} {$d['end']}", 'Europe/Madrid')])
                ->filter(fn ($f) => $f[1]->gt($f[0]))
                ->sortBy(fn ($f) => $f[0]->getTimestamp())->values();
            foreach ($trams as [$a, $b]) {
                $ultim = count($finestres) - 1;
                if ($ultim >= 0 && $a->lte($finestres[$ultim][1])) {
                    if ($b->gt($finestres[$ultim][1])) $finestres[$ultim][1] = $b;
                } else {
                    $finestres[] = [$a, $b];
                }
            }
        }

        // Regla de segmentació per marques (Direcció, 08-08-2026): cada tram hereta
        // l'estat de zona de la marca que l'OBRE. Una detecció de fora de zona trenca
        // el tram en aquell instant i només afecta cap endavant: el temps anterior,
        // acreditat dins de zona, no es penalitza. La sortida fora de zona no invalida
        // la jornada; genera l'alerta i queda com a esdeveniment per revisar.
        $startInZone = (bool) ($workLog->start_location_match ?? false);
        $endInZone = (bool) ($workLog->end_location_match ?? false);

        // Àncores de zona: marques amb veredicte conegut, en ordre temporal. La pausa
        // (inici i represa) és una marca més: si porta veredicte, obre tram nou.
        $anchors = [[$startTime->copy(), $startInZone]];
        foreach ([['break_start_time', 'break_start_location_match'],
                  ['break_end_time', 'break_end_location_match']] as [$tCol, $mCol]) {
            $rawT = $workLog->getRawOriginal($tCol);
            if ($rawT !== null && $workLog->{$mCol} !== null) {
                $t = Carbon::parse($rawT, 'Europe/Madrid');
                if ($t->gt($startTime) && $t->lt($endTime)) {
                    $anchors[] = [$t, (bool) $workLog->{$mCol}];
                }
            }
        }
        usort($anchors, fn ($a, $b) => $a[0] <=> $b[0]);

        $zonaA = function (Carbon $t) use ($anchors): bool {
            $v = $anchors[0][1];
            foreach ($anchors as [$at, $am]) {
                if ($at->lte($t)) $v = $am;
            }
            return $v;
        };
        $talls = function (Carbon $from, Carbon $to) use ($anchors): array {
            $punts = [$from->copy()];
            foreach ($anchors as [$at]) {
                if ($at->gt($from) && $at->lt($to)) $punts[] = $at->copy();
            }
            $punts[] = $to->copy();
            $trossos = [];
            for ($i = 0; $i < count($punts) - 1; $i++) {
                if ($punts[$i]->lt($punts[$i + 1])) $trossos[] = [$punts[$i], $punts[$i + 1]];
            }
            return $trossos;
        };

        $segments = [];

        $iniciJornada = $startTime->copy();
        if ($finestres) {
            // Talls als inicis i finals dels trams de l'horari que cauen dins la jornada.
            $punts = [$startTime->copy()];
            foreach ($finestres as [$a, $b]) {
                foreach ([$a, $b] as $t) {
                    if ($t->gt($startTime) && $t->lt($endTime)) $punts[] = $t->copy();
                }
            }
            $punts[] = $endTime->copy();
            usort($punts, fn ($x, $y) => $x <=> $y);

            for ($i = 0; $i < count($punts) - 1; $i++) {
                [$p, $q] = [$punts[$i], $punts[$i + 1]];
                if (! $p->lt($q)) continue;
                $dinsHorari = collect($finestres)->contains(fn ($f) => $p->gte($f[0]) && $q->lte($f[1]));
                if ($dinsHorari) {
                    // Dins d'horari: trossejat per les àncores de zona; dins de zona → aprovat.
                    foreach ($talls($p, $q) as [$tIni, $tFi]) {
                        $dins = $zonaA($tIni);
                        $segments[] = [
                            'start_time' => $tIni, 'end_time' => $tFi,
                            'start_lat' => null, 'start_lng' => null, 'end_lat' => null, 'end_lng' => null,
                            'in_zone' => $dins, 'in_schedule' => true,
                            'duration_minutes' => intval($tIni->diffInMinutes($tFi)),
                            'status' => $dins ? 'approved' : 'pending',
                        ];
                    }
                } else {
                    // Fora d'horari (abans, entre trams o després): un sol tram pendent de revisió.
                    $segments[] = [
                        'start_time' => $p->copy(), 'end_time' => $q->copy(),
                        'start_lat' => null, 'start_lng' => null, 'end_lat' => null, 'end_lng' => null,
                        'in_zone' => $zonaA($p), 'in_schedule' => false,
                        'duration_minutes' => intval($p->diffInMinutes($q)), 'status' => 'pending',
                    ];
                }
            }
        } else {
            foreach ($talls($startTime, $endTime) as [$tIni, $tFi]) {
                $dins = $zonaA($tIni);
                $segments[] = [
                    'start_time' => $tIni, 'end_time' => $tFi,
                    'start_lat' => null, 'start_lng' => null, 'end_lat' => null, 'end_lng' => null,
                    'in_zone' => $dins, 'in_schedule' => true,
                    'duration_minutes' => intval($tIni->diffInMinutes($tFi)),
                    'status' => $dins ? 'approved' : 'pending',
                ];
            }
        }

        // La posició de l'entrada i la de la sortida van al tram que obren o tanquen, caigui on caigui
        // respecte a l'horari: abans només es copiaven als trams de fora d'horari i un tram que
        // començava amb l'entrada dins d'horari quedava sense cap posició.
        if ($segments) {
            $primer = 0;
            $ultim = count($segments) - 1;
            if (empty($segments[$primer]['start_lat']) && $segments[$primer]['start_time']->equalTo($iniciJornada)) {
                $segments[$primer]['start_lat'] = $workLog->start_location_lat;
                $segments[$primer]['start_lng'] = $workLog->start_location_lng;
            }
            if (empty($segments[$ultim]['end_lat']) && $segments[$ultim]['end_time']->equalTo($endTime)) {
                $segments[$ultim]['end_lat'] = $workLog->end_location_lat;
                $segments[$ultim]['end_lng'] = $workLog->end_location_lng;
            }
        }

        foreach ($segments as $i => $segData) {
            $segData['work_log_id'] = $workLog->id;
            $segData['segment_number'] = $i + 1;
            \App\Models\WorkLogSegment::create($segData);
        }

        $workLog->update(['segmented' => true]);

        // Inicializar tiempo efectivo con los tramos auto-aprobados (dentro de zona y horario)
        $workLog->refresh()->recalcularEfectivo();

        // Alerta si qualsevol de les dues marques ha estat fora de zona. La de sortida
        // no torna pendent cap tram anterior: és un esdeveniment puntual per revisar.
        $pausaFora = collect($anchors)->skip(1)->contains(fn ($a) => $a[1] === false);
        if (! $startInZone || ! $endInZone || $pausaFora) {
            $motius = [];
            if (! $startInZone) $motius[] = 'entrada fora de zona: els trams oberts amb aquesta marca queden pendents de revisió';
            if (! $endInZone) $motius[] = 'sortida fora de zona: el temps anterior acreditat dins de zona es conserva';
            if ($pausaFora) $motius[] = 'marca de pausa fora de zona: el tram que obre queda pendent de revisió';
            \App\Models\WorkLogAlert::create([
                'work_log_id' => $workLog->id, 'user_id' => $workLog->user_id,
                'type' => 'out_of_zone',
                'message' => 'Fitxatge amb marca fora de zona (' . implode('; ', $motius) . ').',
                'scheduled_at' => now(), 'sent_at' => now(),
            ]);
        }

        \App\Models\WorkLogModification::create([
            'work_log_id' => $workLog->id, 'user_id' => $user->id,
            'action' => 'segmented',
            'new_values' => ['segment_count' => count($segments)],
            'comment' => 'Fichatge segmentat automàticament en ' . count($segments) . ' trams',
        ]);
    }
}
