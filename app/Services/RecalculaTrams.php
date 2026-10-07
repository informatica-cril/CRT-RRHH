<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogAlert;
use App\Models\WorkLogModification;
use App\Models\WorkLogSegment;
use Illuminate\Support\Facades\DB;

/**
 * Quan canvia l'horari d'una persona (o la plantilla que fa servir), els trams dels seus
 * fitxatges PENDENTS es tornen a calcular amb l'horari nou: el que abans sortia «fora
 * d'horari» pot passar a ser dins d'horari, i al revés.
 *
 * El registre NO es toca: l'hora d'entrada i de sortida queden igual. Només canvia com es
 * classifiquen els trams. No es recalcula res que ja hagi decidit una persona:
 * - fitxatges aprovats o rebutjats,
 * - trams aprovats o rebutjats a mà, amb audiència oberta o amb al·legació,
 * - jornades amb visites de domi (els trams són l'evidència de les visites).
 */
class RecalculaTrams
{
    /** Més enrere d'aquests dies no es recalcula (els fitxatges vells els resol una persona). */
    private const DIES = 62;

    public function __construct(private WorkLogService $workLogs) {}

    /** @param iterable<int> $userIds  Retorna quants fitxatges s'han recalculat. */
    public function perUsuaris(iterable $userIds, User $autor): int
    {
        $logs = WorkLog::whereIn('user_id', collect($userIds)->all())
            ->where('status', 'pending')
            ->whereNotNull('end_time')
            ->where('segmented', true)
            ->whereDate('date', '>=', now('Europe/Madrid')->subDays(self::DIES)->toDateString())
            ->get();

        $n = 0;
        foreach ($logs as $log) {
            if ($this->recalcula($log, $autor)) $n++;
        }

        return $n;
    }

    private function recalcula(WorkLog $log, User $autor): bool
    {
        $trams = WorkLogSegment::where('work_log_id', $log->id)->get();
        $tocatPerUnaPersona = $trams->contains(fn ($s) => $s->kind !== null
            || $s->status === 'rejected'
            || $s->modified_by !== null
            || $s->audiencia_requested_at !== null
            || ($s->allegation ?? '') !== '');
        if ($tocatPerUnaPersona) {
            return false;
        }

        $abans = $trams->map(fn ($s) => [
            'n' => $s->segment_number, 'inici' => $s->getRawOriginal('start_time'), 'fi' => $s->getRawOriginal('end_time'),
            'horari' => (bool) $s->in_schedule, 'estat' => $s->status,
        ])->values()->all();

        DB::transaction(function () use ($log, $autor, $abans) {
            WorkLogSegment::where('work_log_id', $log->id)->delete();
            // L'avís de fora de zona es torna a crear si encara toca: així no queda duplicat.
            WorkLogAlert::where('work_log_id', $log->id)->where('type', 'out_of_zone')->whereNull('dismissed_at')
                ->update(['dismissed_at' => now()]);
            $log->update(['segmented' => false]);
            $this->workLogs->segmentWorkLog($log->fresh(), $autor);

            WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $autor->id,
                'action' => 'modified',
                'old_values' => ['trams' => $abans],
                'new_values' => ['motiu' => 'canvi d\'horari'],
                'comment' => 'Trams recalculats amb l\'horari nou (les hores d\'entrada i sortida no canvien).',
            ]);
        });

        return true;
    }
}
