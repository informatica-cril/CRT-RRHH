<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\ReavaluaZona;
use Illuminate\Http\Request;

/**
 * Recalcular la zona dels fitxatges pendents quan RRHH o administració canvien els codis postals
 * o els municipis d'una persona (domiciliària).
 *
 * Els polígons dels codis postals només els té el navegador (geoPolygonService), així que la
 * comprovació la fa la pantalla de qui desa l'assignació amb la mateixa lògica que en fitxar
 * (checkUserLocation), i aquí només s'aplica el resultat. Per als centres i punts d'ambulatòria
 * ho fa el servidor sol (ReavaluaZona::perUsuari).
 */
class WorkLogZonaController extends Controller
{
    /**
     * GET /api/v1/work-logs/fora-zona/{user}
     * Fitxatges pendents d'aquesta persona amb alguna marca fora de zona i les coordenades d'aquestes
     * marques. Són fitxatges EN CONFLICTE (EIPD §6.3: es poden veure per resoldre'ls) i la consulta
     * queda a l'auditoria.
     */
    public function foraZona(Request $request, User $user)
    {
        $logs = WorkLog::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('end_time')
            ->where(fn ($q) => $q->where('start_location_match', false)->orWhere('end_location_match', false))
            ->get()
            ->filter(fn ($l) => ($l->start_location_match === false && $l->start_location_lat !== null)
                || ($l->end_location_match === false && $l->end_location_lat !== null))
            ->values();

        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'VIEW_COORDS', 'entity_type' => 'user', 'entity_id' => $user->id,
                'description' => "Coordenades de {$logs->count()} fitxatges fora de zona per recalcular la zona amb l'assignació nova",
                'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) { /* l'auditoria no pot bloquejar */ }

        return response()->json($logs->map(fn ($l) => [
            'id' => $l->id,
            'date' => $l->date?->toDateString(),
            'inici' => $l->start_location_match === false && $l->start_location_lat !== null
                ? ['lat' => (float) $l->start_location_lat, 'lng' => (float) $l->start_location_lng] : null,
            'fi' => $l->end_location_match === false && $l->end_location_lat !== null
                ? ['lat' => (float) $l->end_location_lat, 'lng' => (float) $l->end_location_lng] : null,
        ]));
    }

    /**
     * POST /api/v1/work-logs/reavalua-zona
     * Body: { user_id, resultats: [{ work_log_id, inici_dins, fi_dins }] }
     * Només millora el veredicte (fora → dins) i només en fitxatges pendents d'aquella persona.
     */
    public function reavalua(Request $request, ReavaluaZona $zona)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'resultats' => 'present|array|max:500',
            'resultats.*.work_log_id' => 'required|integer',
            'resultats.*.inici_dins' => 'nullable|boolean',
            'resultats.*.fi_dins' => 'nullable|boolean',
        ]);

        $n = 0;
        foreach ($data['resultats'] as $r) {
            $log = WorkLog::whereKey($r['work_log_id'])->where('user_id', $data['user_id'])->where('status', 'pending')->first();
            if (! $log) continue;
            $iniciDins = ! empty($r['inici_dins']) && $log->start_location_match === false && $log->start_location_lat !== null;
            $fiDins = ! empty($r['fi_dins']) && $log->end_location_match === false && $log->end_location_lat !== null;
            if (! $iniciDins && ! $fiDins) continue;
            $zona->aplica($log, $iniciDins, $fiDins, $request->user()->id);
            $n++;
        }

        return response()->json(['fitxatges_reavaluats' => $n]);
    }
}
