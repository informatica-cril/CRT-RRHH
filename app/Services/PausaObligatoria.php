<?php

namespace App\Services;

use App\Models\BreakSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogModification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pausa obligatòria portada pel SERVIDOR.
 *
 * Abans la pausa només l'obria i la tancava la pantalla del treballador: si tancava la
 * sessió (ordinadors compartits per diverses persones), la pausa no es registrava mai. Ara
 * la tasca `worklogs:pausa-automatica` l'obre a l'hora que toca i la tanca en acabar la
 * durada, encara que no hi hagi cap pantalla oberta. La pantalla, si hi és, només la mostra.
 *
 * Hores en hora de Madrid sense zona (mateix conveni que start_time / end_time).
 */
class PausaObligatoria
{
    /**
     * Hora en què toca la pausa d'aquesta jornada, o null si no n'hi toca.
     * Mateixes regles que feia la pantalla (WorkerDashboard: startBreakCheckTimer).
     */
    public function horaPausa(WorkLog $log, User $worker, BreakSetting $settings): ?Carbon
    {
        // La columna no té cast: a MySQL arriba com 0/1 (o null si no hi ha excepció).
        $override = $worker->break_override === null ? null : (bool) $worker->break_override;
        $enabled = $override ?? (bool) $settings->enabled;
        if (! $enabled) {
            return null;
        }

        $rawStart = $log->getRawOriginal('start_time');
        if (! $rawStart) {
            return null;
        }
        $clockIn = Carbon::parse($rawStart, 'Europe/Madrid');

        // L'override forçat a "sí" salta el llindar; si no, cal que la jornada prevista el superi.
        if ($override !== true
            && $this->horesPrevistes($worker, $clockIn) <= (float) $settings->threshold_hours) {
            return null;
        }

        // "00:00:00" és el valor per defecte de la columna quan no s'ha fixat cap hora.
        $horaPropia = $worker->break_override_time;
        $horaPropia = $horaPropia instanceof Carbon ? $horaPropia->format('H:i:s') : $horaPropia;
        $teHoraPropia = $horaPropia && $horaPropia !== '00:00:00' && $horaPropia !== '00:00';
        $mode = $teHoraPropia ? 'fixed' : $settings->break_start_mode;

        if ($mode === 'fixed') {
            $fixa = $teHoraPropia ? $horaPropia : ($settings->break_start_fixed_time ?: '12:00');
            $fixa = $fixa instanceof Carbon ? $fixa->format('H:i') : substr((string) $fixa, 0, 5);
            [$fh, $fm] = array_map('intval', explode(':', $fixa));
            $hora = $clockIn->copy()->setTime($fh, $fm, 0);
            if ($hora->lt($clockIn)) {
                // Ha fitxat després de l'hora fixa: si fa més de 30 min, ja no toca pausa; si no, ara.
                return $clockIn->diffInMinutes($hora, true) > 30 ? null : $clockIn;
            }

            return $hora;
        }

        if ($mode === 'auto') {
            // Hora que el motor de Domiciliària ha col·locat a la ruta d'avui.
            $pb = DB::table('planned_breaks')
                ->where('user_id', $worker->id)
                ->where('date', $clockIn->toDateString())
                ->first();
            if ($pb && $pb->start_time) {
                [$ph, $pm] = array_map('intval', explode(':', substr((string) $pb->start_time, 0, 5)));

                return $clockIn->copy()->setTime($ph, $pm, 0);
            }
            // Sense pla per avui: cau a l'offset perquè la pausa no es perdi.
        }

        $offset = (int) ($settings->break_start_offset_minutes ?: $settings->threshold_hours * 60);

        return $clockIn->copy()->addMinutes($offset);
    }

    /** Hores previstes al dia de la setmana del fitxatge (primer tram, com la pantalla). */
    private function horesPrevistes(User $worker, Carbon $dia): float
    {
        $schedule = $worker->workSchedule;
        if (! $schedule) {
            return 0;
        }
        $jsDay = (int) $dia->format('w'); // 0=Dg .. 6=Ds
        foreach (\App\Models\WorkSchedule::normalizeDays($schedule->days) as $d) {
            if ($d['day'] === $jsDay) {
                if (! $d['active']) {
                    return 0;
                }
                [$sh, $sm] = array_map('intval', explode(':', $d['start']));
                [$eh, $em] = array_map('intval', explode(':', $d['end']));

                return max(0, (($eh * 60 + $em) - ($sh * 60 + $sm)) / 60);
            }
        }

        return 0;
    }

    /**
     * Obre la pausa si encara no està oberta ni feta. Retorna true si l'ha oberta.
     * Comprovar i escriure amb la fila bloquejada: la pantalla pot demanar-la alhora.
     */
    public function obrir(WorkLog $workLog, Carbon $ara, int $durada): bool
    {
        return DB::transaction(function () use ($workLog, $ara, $durada) {
            $log = WorkLog::whereKey($workLog->id)->lockForUpdate()->first();
            if (! $log || $log->end_time
                || in_array($log->break_status, ['active', 'completed', 'skipped'], true)
                || $log->break_end_time) {
                return false;
            }

            $log->update([
                'break_start_time' => $ara->toDateTimeString(),
                'break_status' => 'active',
                'break_required' => true,
            ]);

            WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $log->user_id,
                'action' => 'break_started',
                'new_values' => ['break_start_time' => $ara->toDateTimeString()],
                'comment' => "Pausa obligatòria iniciada automàticament ({$durada} minuts)",
            ]);

            return true;
        });
    }

    /**
     * Hora de fi d'una pausa oberta: mai més tard de inici + durada (si la pantalla es reobre
     * més tard, la pausa no s'allarga) ni de la sortida de la jornada.
     */
    public function horaFi(WorkLog $log, Carbon $ara, int $durada): Carbon
    {
        $fi = $ara->copy();
        $rawInici = $log->getRawOriginal('break_start_time');
        if ($rawInici) {
            $limit = Carbon::parse($rawInici, 'Europe/Madrid')->addMinutes($durada);
            if ($limit->lt($fi)) {
                $fi = $limit;
            }
        }
        $rawSortida = $log->getRawOriginal('end_time');
        if ($rawSortida) {
            $sortida = Carbon::parse($rawSortida, 'Europe/Madrid');
            if ($sortida->lt($fi)) {
                $fi = $sortida;
            }
        }

        return $fi;
    }

    /** Tanca una pausa oberta que ja ha durat el que toca. Retorna true si l'ha tancada. */
    public function tancarSiHaAcabat(WorkLog $workLog, Carbon $ara, int $durada): bool
    {
        return DB::transaction(function () use ($workLog, $ara, $durada) {
            $log = WorkLog::whereKey($workLog->id)->lockForUpdate()->first();
            if (! $log || $log->break_status !== 'active' || $log->break_end_time) {
                return false;
            }
            $fi = $this->horaFi($log, $ara, $durada);
            // Encara en curs (ni ha passat la durada ni s'ha tancat la jornada).
            if ($fi->gte($ara) && ! $log->getRawOriginal('end_time')) {
                return false;
            }

            $log->update([
                'break_end_time' => $fi->toDateTimeString(),
                'break_status' => 'completed',
            ]);

            WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $log->user_id,
                'action' => 'break_completed',
                'new_values' => ['break_end_time' => $fi->toDateTimeString()],
                'comment' => 'Pausa obligatòria completada automàticament',
            ]);

            return true;
        });
    }
}
