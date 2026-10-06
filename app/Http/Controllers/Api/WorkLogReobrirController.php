<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLog;
use App\Models\WorkLogModification;
use App\Models\WorkLogSegment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * «Revisar de nou»: RRHH i administració tornen a pendent un fitxatge o un tram que ja
 * s'havia resolt (normalment per coordinació), per tornar-lo a validar si no és coherent.
 *
 * No esborra res: la decisió anterior queda a la traçabilitat amb qui la va prendre, i el
 * motiu de la reobertura s'hi afegeix. Si després es rebutja el tram, l'audiència prèvia
 * continua sent obligatòria (WorkLogSegmentController::reject).
 */
class WorkLogReobrirController extends Controller
{
    /** POST /api/v1/work-logs/{workLog}/reobrir   Body: { motiu } */
    public function fitxatge(Request $request, WorkLog $workLog)
    {
        $data = $request->validate(['motiu' => 'required|string|min:10|max:1000']);

        DB::transaction(function () use ($request, $workLog, $data) {
            $log = WorkLog::whereKey($workLog->id)->lockForUpdate()->first();
            abort_if($log->status === 'pending', 422, 'El fitxatge ja està pendent de revisió.');

            $abans = $log->status;
            $log->update(['status' => 'pending']);

            WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $request->user()->id,
                'action' => 'modified',
                'old_values' => ['status' => $abans],
                'new_values' => ['status' => 'pending'],
                'comment' => 'Fitxatge reobert per revisar de nou: ' . $data['motiu'],
            ]);
        });

        return response()->json($workLog->fresh());
    }

    /** POST /api/v1/work-logs/{workLog}/segments/{segment}/reobrir   Body: { motiu } */
    public function tram(Request $request, WorkLog $workLog, WorkLogSegment $segment)
    {
        abort_if($segment->work_log_id !== $workLog->id, 404);
        $data = $request->validate(['motiu' => 'required|string|min:10|max:1000']);

        DB::transaction(function () use ($request, $workLog, $segment, $data) {
            $seg = WorkLogSegment::whereKey($segment->id)->lockForUpdate()->first();
            abort_if($seg->status === 'pending', 422, 'El tram ja està pendent de revisió.');

            $abans = $seg->status;
            $seg->update([
                'status' => 'pending',
                'modified_by' => $request->user()->id,
                'modified_at' => now(),
            ]);
            // El tram torna a no comptar fins que es resolgui de nou.
            $workLog->recalcularEfectivo();
            // Si el fitxatge estava resolt, també torna a pendent: hi ha un tram per revisar.
            if ($workLog->fresh()->status !== 'pending') {
                $workLog->update(['status' => 'pending']);
            }

            WorkLogModification::create([
                'work_log_id' => $workLog->id,
                'user_id' => $request->user()->id,
                'action' => 'modified',
                'old_values' => ['segment' => $seg->segment_number, 'status' => $abans],
                'new_values' => ['segment' => $seg->segment_number, 'status' => 'pending'],
                'comment' => 'Tram ' . $seg->segment_number . ' reobert per revisar de nou: ' . $data['motiu'],
            ]);
        });

        return response()->json($segment->fresh());
    }
}
