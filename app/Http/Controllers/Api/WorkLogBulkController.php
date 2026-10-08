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
}
