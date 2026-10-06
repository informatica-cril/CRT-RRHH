<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLog;
use App\Models\WorkLogModification;
use App\Models\WorkLogSegment;
use App\Services\WorkLogService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «Corregir hora»: RRHH i administració corregeixen l'hora d'entrada o de sortida d'un
 * fitxatge (p. ex. una sortida oblidada que es va fitxar l'endemà).
 *
 * Les hores es tornen a calcular al servidor amb les mateixes regles que en fitxar la sortida,
 * i els trams es tornen a fer (WorkLogService::segmentWorkLog). El valor anterior, els trams
 * que hi havia i el motiu queden a la traçabilitat. El fitxatge torna a pendent per validar-lo.
 *
 * Hores en hora de Madrid sense zona: el text que arriba és el que es desa.
 */
class WorkLogCorreccioController extends Controller
{
    public function __construct(private WorkLogService $workLogs) {}

    /** POST /api/v1/work-logs/{workLog}/corregir-hora   Body: { start_time?, end_time?, motiu } */
    public function corregir(Request $request, WorkLog $workLog)
    {
        $data = $request->validate([
            'start_time' => 'nullable|date_format:Y-m-d H:i',
            'end_time' => 'nullable|date_format:Y-m-d H:i',
            'motiu' => 'required|string|min:10|max:1000',
        ]);
        abort_if(empty($data['start_time']) && empty($data['end_time']), 422, 'Indica l\'hora d\'entrada o la de sortida.');

        $segments = WorkLogSegment::where('work_log_id', $workLog->id)->get();
        // Jornades de domi segmentades per hitos: els trams són l'evidència de les visites.
        abort_if($segments->whereNotNull('kind')->isNotEmpty(), 422,
            'Aquesta jornada té visites registrades des de domi i no es pot corregir des d\'aquí. Avisa Informàtica.');
        abort_if($segments->where('status', 'rejected')->isNotEmpty(), 422,
            'Aquest fitxatge té trams rebutjats. Torna\'ls a pendent amb «Revisar de nou» abans de corregir l\'hora.');

        $rawInici = $workLog->getRawOriginal('start_time');
        $rawFi = $workLog->getRawOriginal('end_time');
        $inici = Carbon::parse($data['start_time'] ?? $rawInici, 'Europe/Madrid')->seconds(0);
        $fi = ! empty($data['end_time']) ? Carbon::parse($data['end_time'], 'Europe/Madrid')
            : ($rawFi ? Carbon::parse($rawFi, 'Europe/Madrid') : null);
        abort_if(! $fi, 422, 'La jornada encara és oberta: indica també l\'hora de sortida.');
        abort_if($fi->lte($inici), 422, 'La sortida ha de ser posterior a l\'entrada.');
        abort_if($inici->diffInHours($fi) > 24, 422, 'Una jornada no pot durar més de 24 hores.');
        abort_if($fi->gt(Carbon::now('Europe/Madrid')), 422, 'La sortida no pot ser en el futur.');
        abort_if($inici->toDateString() !== Carbon::parse($workLog->date)->toDateString(), 422,
            'L\'entrada ha de ser del mateix dia del fitxatge.');

        DB::transaction(function () use ($request, $workLog, $data, $segments, $rawInici, $rawFi, $inici, $fi) {
            $log = WorkLog::whereKey($workLog->id)->lockForUpdate()->first();

            $total = round($inici->diffInSeconds($fi) / 3600, 4);
            $foraZona = round((float) ($log->hours_out_of_area ?? 0), 4);
            $log->update([
                'start_time' => $inici->toDateTimeString(),
                'end_time' => $fi->toDateTimeString(),
                'total_hours_worked' => $total,
                'hours_worked' => round(max(0, $total - $foraZona), 4),
                'complementary_minutes' => intval(round(max(0, $total - 8) * 60)),
                'status' => 'pending',
                'segmented' => false,
            ]);

            // Els trams es tornen a fer amb les hores noves (mateixes regles que en fitxar la sortida).
            WorkLogSegment::where('work_log_id', $log->id)->delete();
            $this->workLogs->segmentWorkLog($log->fresh(), $request->user());

            WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $request->user()->id,
                'action' => 'modified',
                'old_values' => [
                    'start_time' => $rawInici,
                    'end_time' => $rawFi,
                    'trams' => $segments->map(fn ($s) => [
                        'n' => $s->segment_number,
                        'inici' => $s->getRawOriginal('start_time'),
                        'fi' => $s->getRawOriginal('end_time'),
                        'estat' => $s->status,
                        'allegacio' => $s->allegation,
                    ])->values()->all(),
                ],
                'new_values' => ['start_time' => $inici->toDateTimeString(), 'end_time' => $fi->toDateTimeString()],
                'comment' => 'Hora corregida (entrada ' . substr((string) $rawInici, 11, 5) . ' → ' . $inici->format('H:i')
                    . ', sortida ' . ($rawFi ? substr((string) $rawFi, 11, 5) : 'oberta') . ' → ' . $fi->format('H:i') . '): ' . $data['motiu'],
            ]);
        });

        return response()->json($workLog->fresh()->load('segments'));
    }
}
