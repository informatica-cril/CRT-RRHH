<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/v1/work-logs/aprova-seleccionats { ids: [...] }
 *
 * Aprovar d'un en un recarregava tota la llista a cada clic: amb centenars de pendents, RRHH no se'n
 * sortia. Aquí es fa el mateix que el botó «Aprovar» (estat aprovat i, si hi havia hores descomptades
 * per fora de zona, es recuperen), per a una selecció, en una sola transacció i amb constància al
 * historial de cada fitxatge. Només es toquen els que encara són pendents.
 */
class WorkLogBulkController extends Controller
{
    public function aprova(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:1000',
            'ids.*' => 'integer',
        ]);
        $autor = $request->user();
        $ara = now();

        $aprovats = DB::transaction(function () use ($data, $autor, $ara) {
            $logs = WorkLog::whereIn('id', $data['ids'])->where('status', 'pending')->lockForUpdate()->get();
            foreach ($logs as $l) {
                $canvis = ['status' => 'approved'];
                if ($l->hour_status === 'out_of_area' || (float) $l->hours_out_of_area > 0) {
                    $canvis += $l->canvisRestauraForaZona();
                }
                $l->forceFill($canvis)->save();
                $trams = $l->aprovaTramsPendents($autor->id);
                if ($trams) $canvis['trams_aprovats'] = $trams;
                DB::table('work_log_modifications')->insert([
                    'work_log_id' => $l->id, 'user_id' => $autor->id, 'action' => 'approved',
                    'old_values' => json_encode(['status' => 'pending']), 'new_values' => json_encode($canvis),
                    'comment' => 'Aprovació conjunta des de la llista de registres', 'created_at' => $ara, 'updated_at' => $ara,
                ]);
            }
            return $logs->count();
        });

        try {
            AuditLog::create([
                'user_id' => $autor->id, 'action' => 'APPROVE_WORKLOG', 'entity_type' => 'work_log',
                'description' => "Aprovació conjunta de {$aprovats} fitxatges",
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'aprovats' => $aprovats,
            // Els que no eren pendents (algú ja els havia resolt) no es toquen i es diu.
            'omesos'   => count(array_unique($data['ids'])) - $aprovats,
        ]);
    }

    /**
     * POST /api/v1/work-logs/rebutja-seleccionats { ids: [...], motiu }
     *
     * El mateix que el botó «Rebutjar» de cada fila (estat rebutjat amb el motiu), per a una selecció.
     * Només es toquen els pendents. El rebuig de trams concrets continua passant per l'audiència
     * prèvia del detall (WorkLogSegmentController::reject): aquí no es toca cap tram.
     */
    public function rebutja(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:1000',
            'ids.*' => 'integer',
            'motiu' => 'required|string|min:5|max:1000',
        ]);
        $autor = $request->user();
        $ara = now();
        $motiu = trim($data['motiu']);

        $rebutjats = DB::transaction(function () use ($data, $autor, $ara, $motiu) {
            $logs = WorkLog::whereIn('id', $data['ids'])->where('status', 'pending')->lockForUpdate()->get();
            foreach ($logs as $l) {
                $l->forceFill(['status' => 'rejected', 'rejection_reason' => $motiu])->save();
                DB::table('work_log_modifications')->insert([
                    'work_log_id' => $l->id, 'user_id' => $autor->id, 'action' => 'rejected',
                    'old_values' => json_encode(['status' => 'pending']),
                    'new_values' => json_encode(['status' => 'rejected', 'rejection_reason' => $motiu]),
                    'comment' => 'Rebuig conjunt des de la llista de registres: ' . $motiu, 'created_at' => $ara, 'updated_at' => $ara,
                ]);
            }
            return $logs->count();
        });

        $this->audita($request, 'REJECT_WORKLOG', "Rebuig conjunt de {$rebutjats} fitxatges: {$motiu}");

        return response()->json(['rebutjats' => $rebutjats, 'omesos' => count(array_unique($data['ids'])) - $rebutjats]);
    }

    /**
     * POST /api/v1/work-logs/reobre-seleccionats { ids: [...], motiu }
     *
     * «Tornar a revisar» per a una selecció (RRHH i admin): els fitxatges ja resolts tornen a pendent.
     * Com WorkLogReobrirController::fitxatge, no s'esborra res: la decisió anterior i el motiu
     * queden a la traçabilitat. Els que ja eren pendents no es toquen.
     */
    public function reobre(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:1000',
            'ids.*' => 'integer',
            'motiu' => 'required|string|min:10|max:1000',
        ]);
        $autor = $request->user();
        $ara = now();
        $motiu = trim($data['motiu']);

        $reoberts = DB::transaction(function () use ($data, $autor, $ara, $motiu) {
            $logs = WorkLog::whereIn('id', $data['ids'])->where('status', '!=', 'pending')->lockForUpdate()->get();
            foreach ($logs as $l) {
                $abans = $l->status;
                $l->forceFill(['status' => 'pending'])->save();
                DB::table('work_log_modifications')->insert([
                    'work_log_id' => $l->id, 'user_id' => $autor->id, 'action' => 'modified',
                    'old_values' => json_encode(['status' => $abans]), 'new_values' => json_encode(['status' => 'pending']),
                    'comment' => 'Fitxatge reobert per revisar de nou: ' . $motiu, 'created_at' => $ara, 'updated_at' => $ara,
                ]);
            }
            return $logs->count();
        });

        $this->audita($request, 'REOPEN_WORKLOG', "Revisió de nou conjunta de {$reoberts} fitxatges: {$motiu}");

        return response()->json(['reoberts' => $reoberts, 'omesos' => count(array_unique($data['ids'])) - $reoberts]);
    }

    /** Constància a audit_logs. Mai pot fer caure l'operació. */
    private function audita(Request $request, string $accio, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => $accio, 'entity_type' => 'work_log',
                'description' => $descripcio,
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
