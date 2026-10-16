<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AprovacioAutomaticaSetting;
use App\Models\AuditLog;
use App\Models\WorkLog;
use App\Services\AprovacioAutomatica;
use Illuminate\Http\Request;

/**
 * Aprovació automàtica dels fitxatges correctes: la configuració (interruptor i marge de minuts)
 * i «Aprovar tots els correctes» per als pendents que ja hi havia (veure App\Services\AprovacioAutomatica).
 */
class AprovacioAutomaticaController extends Controller
{
    /** GET /api/v1/aprovacio-automatica */
    public function show()
    {
        return response()->json(AprovacioAutomaticaSetting::getSettings());
    }

    /** PUT /api/v1/aprovacio-automatica */
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'boolean',
            'marge_minuts' => 'integer|min:0|max:60',
        ]);
        $conf = AprovacioAutomaticaSetting::getSettings();
        $conf->update($data);
        $this->audita($request, 'Aprovació automàtica ' . ($conf->enabled ? 'activada' : 'desactivada') . " (marge {$conf->marge_minuts} min)");

        return response()->json($conf->fresh());
    }

    /**
     * POST /api/v1/work-logs/aprova-correctes { ids?: [...] }
     * Aprova els pendents tancats que compleixen la regla (els de la llista, si se'n passen).
     * Els que no, es queden pendents; es diu quants i per què.
     */
    public function aprovaCorrectes(Request $request)
    {
        $data = $request->validate([
            'ids' => 'nullable|array|max:5000',
            'ids.*' => 'integer',
        ]);
        $marge = AprovacioAutomaticaSetting::getSettings()->marge_minuts;

        $q = WorkLog::with('user.workSchedule')->where('status', 'pending')->whereNotNull('end_time');
        if (! empty($data['ids'])) $q->whereIn('id', $data['ids']);

        $aprovats = 0;
        $motius = [];
        $q->orderBy('id')->chunkById(200, function ($logs) use ($marge, &$aprovats, &$motius) {
            foreach ($logs as $log) {
                $motiu = AprovacioAutomatica::motiu($log, $marge);
                if ($motiu === null) {
                    AprovacioAutomatica::aprova($log, 'amb «Aprovar tots els correctes»');
                    $aprovats++;
                } else {
                    $motius[$motiu] = ($motius[$motiu] ?? 0) + 1;
                }
            }
        });
        arsort($motius);
        $this->audita($request, "Aprovació conjunta dels correctes: {$aprovats} fitxatges");

        return response()->json(['aprovats' => $aprovats, 'pendents' => array_sum($motius), 'motius' => $motius]);
    }

    private function audita(Request $request, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'APPROVE_WORKLOG', 'entity_type' => 'work_log',
                'description' => $descripcio,
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
