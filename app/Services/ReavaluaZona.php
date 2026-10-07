<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogAlert;
use App\Models\WorkLogModification;
use App\Models\WorkLogSegment;
use Illuminate\Support\Facades\DB;

/**
 * Quan a una persona d'ambulatòria se li assigna un centre o un punt nou, els seus fitxatges
 * PENDENTS que havien quedat fora de zona es tornen a comprovar amb els centres i punts que
 * té ara. Si la marca hi cau dins, deixa de comptar com a fora de zona.
 *
 * - Mateixa regla que la pantalla de fitxar (isWithinAmbulatoryPoints): distància al punt
 *   ≤ radi + 100 m de tolerància del GPS.
 * - Només millora el veredicte (fora → dins), mai al revés, i només en fitxatges pendents:
 *   el que ja s'ha resolt no es toca.
 * - Necessita les coordenades de la marca. Un fitxatge amb incidència oberta les conserva
 *   (PurgeLocationData); si ja s'han purgat, no es pot recalcular.
 * - Domiciliària (codis postals) no: els polígons dels CP només els té el navegador.
 */
class ReavaluaZona
{
    /** Metres de cortesia del GPS (GEOLOCATION_TOLERANCE a src/services/geolocation.js). */
    private const TOLERANCIA_M = 100;

    /** Retorna quants fitxatges han quedat dins de zona. */
    public function perUsuari(User $user, int $autorId): int
    {
        if ($user->work_type !== 'AMBULATORIA') {
            return 0;
        }
        $punts = $this->punts($user);
        if ($punts->isEmpty()) {
            return 0;
        }

        $logs = WorkLog::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('end_time')
            ->where(fn ($q) => $q->where('start_location_match', false)->orWhere('end_location_match', false))
            ->get();

        $n = 0;
        foreach ($logs as $log) {
            $iniciDins = $log->start_location_match === false && $log->start_location_lat !== null
                && $this->dins($punts, (float) $log->start_location_lat, (float) $log->start_location_lng);
            $fiDins = $log->end_location_match === false && $log->end_location_lat !== null
                && $this->dins($punts, (float) $log->end_location_lat, (float) $log->end_location_lng);
            if (! $iniciDins && ! $fiDins) {
                continue;
            }
            $this->aplica($log, $iniciDins, $fiDins, $autorId);
            $n++;
        }

        return $n;
    }

    /** Punts manuals de la persona i els dels seus centres (com GeolocationController::ambulatoryPoints). */
    private function punts(User $user)
    {
        $manuals = $user->workLocations;
        $deCentres = $user->ambulatoryCenters()->with('workLocations')->get()->pluck('workLocations')->flatten();

        return $manuals->merge($deCentres)->unique('id')
            ->filter(fn ($p) => $p->lat !== null && $p->lng !== null)->values();
    }

    private function dins($punts, float $lat, float $lng): bool
    {
        foreach ($punts as $p) {
            if ($this->metres($lat, $lng, (float) $p->lat, (float) $p->lng) <= ((float) ($p->radius ?: 100)) + self::TOLERANCIA_M) {
                return true;
            }
        }

        return false;
    }

    private function metres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function aplica(WorkLog $workLog, bool $iniciDins, bool $fiDins, int $autorId): void
    {
        DB::transaction(function () use ($workLog, $iniciDins, $fiDins, $autorId) {
            $log = WorkLog::whereKey($workLog->id)->lockForUpdate()->first();
            $abans = ['start_location_match' => $log->start_location_match, 'end_location_match' => $log->end_location_match];

            $canvis = [];
            if ($iniciDins) $canvis['start_location_match'] = true;
            if ($fiDins) $canvis['end_location_match'] = true;
            $log->update($canvis);
            $log->refresh();

            $totDins = $log->start_location_match !== false && $log->end_location_match !== false;
            // Les marques de pausa no tenen cast: a MySQL arriben com 0/1 (o null si no n'hi ha).
            $foraMarca = fn ($v) => $v !== null && ! (bool) $v;
            $pausaFora = $foraMarca($log->break_start_location_match) || $foraMarca($log->break_end_location_match);

            // Els trams hereten la zona de la marca que els obre (WorkLogService::segmentWorkLog).
            // Si l'entrada ara és dins i cap marca de pausa no era fora, tots els trams són dins.
            // Dins de zona i d'horari → aprovat (mateixa regla que en segmentar), tret que s'hi
            // hagi obert audiència: aquell el resol una persona.
            if ($iniciDins && ! $pausaFora) {
                WorkLogSegment::where('work_log_id', $log->id)->where('status', 'pending')->where('in_zone', false)
                    ->get()->each(function (WorkLogSegment $s) {
                        $s->update(array_filter([
                            'in_zone' => true,
                            'status' => ($s->in_schedule && ! $s->audiencia_requested_at) ? 'approved' : null,
                        ], fn ($v) => $v !== null));
                    });
            }

            if ($totDins && ! $pausaFora) {
                $log->update([
                    'hours_out_of_area' => 0,
                    'hours_worked' => $log->total_hours_worked,
                    'hour_status' => $log->hour_status === 'out_of_area'
                        ? ((float) $log->extra_hours_unauthorized > 0 ? 'extra' : 'ok')
                        : $log->hour_status,
                ]);
                WorkLogAlert::where('work_log_id', $log->id)->where('type', 'out_of_zone')
                    ->whereNull('dismissed_at')->update(['dismissed_at' => now()]);
            }

            $log->refresh()->recalcularEfectivo();

            WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $autorId,
                'action' => 'modified',
                'old_values' => $abans,
                'new_values' => $canvis,
                'comment' => 'Zona recalculada amb l\'assignació nova (centre, punt, codis postals o municipis): '
                    . implode(' i ', array_filter([$iniciDins ? 'l\'entrada' : null, $fiDins ? 'la sortida' : null]))
                    . ' ara queda dins de zona.',
            ]);
        });
    }
}
