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

    /**
     * POST /api/v1/work-logs/{workLog}/corregir-zona
     * Body: { marques: ['entrada'|'sortida'|'pausa'], centre_id?, motiu }
     * «Corregir zona»: la persona era en un centre que no tenia assignat (o el GPS va fallar).
     * Les marques indicades passen a dins de zona i es recalculen trams, hores fora de zona i
     * efectiu, igual que quan s'assigna un centre. Centre i motiu queden a la traçabilitat.
     */
    public function corregir(Request $request, WorkLog $workLog, ReavaluaZona $zona)
    {
        $data = $request->validate([
            'marques' => 'required|array|min:1',
            'marques.*' => 'in:entrada,sortida,pausa',
            'centre_id' => 'nullable|integer|exists:ambulatory_centers,id',
            'motiu' => 'required|string|min:10|max:1000',
        ]);
        abort_if(! $workLog->getRawOriginal('end_time'), 422, 'La jornada encara és oberta: corregeix la zona quan tingui sortida.');

        $fora = fn ($v) => $v !== null && ! (bool) $v;
        $marques = array_values(array_filter(array_unique($data['marques']), fn ($m) => match ($m) {
            'entrada' => $fora($workLog->start_location_match),
            'sortida' => $fora($workLog->end_location_match),
            'pausa' => $fora($workLog->break_start_location_match) || $fora($workLog->break_end_location_match),
        }));
        abort_if(! $marques, 422, 'Cap de les marques triades no és fora de zona.');

        $centre = isset($data['centre_id']) ? \App\Models\AmbulatoryCenter::find($data['centre_id'])?->name : null;
        $zona->corregeixManual($workLog, $marques, $centre, trim($data['motiu']), $request->user()->id);

        return response()->json($workLog->fresh()->load('segments'));
    }
}
