<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purga i retencions segons l'EIPD definitiva (Annex V):
 *
 *  1) COORDENADES BRUTES (work_logs, work_log_segments, location_tracking.lat/lng):
 *     purga als 30 DIES NATURALS del marcatge — EXCEPTE si el fichatge té una
 *     INCIDÈNCIA OBERTA (alerta no resolta o tram pendent d'audiència): mentre
 *     s'estudia, la dada es manté; un cop resolta amb fermesa, la purga següent
 *     l'elimina. Execució DIÀRIA sense intervenció humana.
 *     A location_tracking es NULLIFIQUEN lat/lng però es CONSERVEN accuracy i
 *     event_type: són les "precisions agregades del pilot" (Annex V, fins a
 *     resolució del radi definitiu +1 any).
 *
 *  2) RETENCIÓ 4 ANYS (art. 34.9 ET): work_logs (amb trams, modificacions i alertes)
 *     i auditoria d'ubicació (ACCESS_LOCATION_DATA / DOMI_*) se suprimeixen al venciment.
 *
 * Per defecte DRY-RUN. Amb --apply executa.
 */
class PurgeLocationData extends Command
{
    const COORDS_RETENTION_DAYS = 30;

    const REGISTRE_RETENTION_YEARS = 4;

    protected $signature = 'locations:purge {--apply : Executa la purga (per defecte, dry-run)}';

    protected $description = 'Purga diària EIPD: coordenades a 30 dies (excepte incidència oberta) i registre/auditoria a 4 anys';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $coordsCutoff = now()->subDays(self::COORDS_RETENTION_DAYS)->toDateTimeString();
        $registreCutoff = now()->subYears(self::REGISTRE_RETENTION_YEARS)->toDateTimeString();

        $this->info(($apply ? '⚠️  PURGA' : '🔎 DRY-RUN') . " — coordenades abans de {$coordsCutoff} · registre abans de {$registreCutoff}");

        // ── Fichatges amb INCIDÈNCIA OBERTA: exempts de purga de coordenades ──
        // (alerta no resolta O tram pendent d'audiència — "mentre s'estudia tot")
        $ambIncidencia = DB::table('work_logs')
            ->where(function ($q) {
                $q->whereExists(fn ($s) => $s->from('work_log_alerts')
                    ->whereColumn('work_log_alerts.work_log_id', 'work_logs.id')
                    ->whereNull('dismissed_at'))
                    ->orWhereExists(fn ($s) => $s->from('work_log_segments')
                        ->whereColumn('work_log_segments.work_log_id', 'work_logs.id')
                        ->where('status', 'pending'));
            })
            ->pluck('id');

        // 1a) Coordenades de work_logs (>30 dies, sense incidència oberta, no purgats)
        $logsQ = DB::table('work_logs')
            ->where('created_at', '<', $coordsCutoff)
            ->whereNull('coords_purged_at')
            ->whereNotIn('id', $ambIncidencia)
            ->where(fn ($q) => $q->whereNotNull('start_location_lat')->orWhereNotNull('end_location_lat'));
        $nLogs = $logsQ->count();
        if ($apply && $nLogs > 0) {
            $logsQ->update([
                'start_location_lat' => null, 'start_location_lng' => null,
                'end_location_lat' => null, 'end_location_lng' => null,
                'start_location_distance' => null, 'end_location_distance' => null,
                'coords_purged_at' => now(),
            ]);
        }

        // 1b) Coordenades dels TRAMS (mateixa regla; es conserven veredicte, distància i
        //     radi — metadades del marcatge, 4 anys segons Annex V)
        $segsQ = DB::table('work_log_segments')
            ->where('created_at', '<', $coordsCutoff)
            ->whereNotIn('work_log_id', $ambIncidencia)
            ->where(fn ($q) => $q->whereNotNull('start_lat')->orWhereNotNull('end_lat'));
        $nSegs = $segsQ->count();
        if ($apply && $nSegs > 0) {
            $segsQ->update(['start_lat' => null, 'start_lng' => null, 'end_lat' => null, 'end_lng' => null]);
        }

        // 1c) location_tracking: NULL de lat/lng conservant accuracy (estadística del pilot).
        //     Des que la migració 2026_08_08_000002 va SUPRIMIR latitude/longitude de la taula,
        //     aquest pas ja no té res a nul·lificar —la coordenada ja no existeix ni un sol dia, que
        //     és més garantia que esborrar-la als 30— però la consulta seguia demanant la columna i
        //     petava cada nit ABANS d'arribar a la retenció dels 4 anys (art. 34.9 ET), que per tant
        //     no s'executava mai. El pas es comprova contra l'esquema real i s'omet si no escau.
        $trkTeCoords = Schema::hasColumn('location_tracking', 'latitude');
        $nTrk = 0;
        if ($trkTeCoords) {
            $trkQ = DB::table('location_tracking')
                ->where('created_at', '<', $coordsCutoff)
                ->whereNotNull('latitude');
            $nTrk = $trkQ->count();
            if ($apply && $nTrk > 0) {
                $trkQ->update(['latitude' => null, 'longitude' => null]);
            }
        }

        // 2a) Registre de jornada a 4 anys: supressió (amb trams, modificacions i alertes)
        $oldLogIds = DB::table('work_logs')->where('date', '<', $registreCutoff)->pluck('id');
        $nOld = $oldLogIds->count();
        if ($apply && $nOld > 0) {
            foreach ($oldLogIds->chunk(500) as $chunk) {
                DB::table('work_log_segments')->whereIn('work_log_id', $chunk)->delete();
                DB::table('work_log_modifications')->whereIn('work_log_id', $chunk)->delete();
                DB::table('work_log_alerts')->whereIn('work_log_id', $chunk)->delete();
                DB::table('work_logs')->whereIn('id', $chunk)->delete();
            }
        }

        // 2b) Auditoria d'ubicació a 4 anys
        $audQ = DB::table('audit_logs')
            ->where('created_at', '<', $registreCutoff)
            ->where(fn ($q) => $q->where('action', 'ACCESS_LOCATION_DATA')->orWhere('action', 'like', 'DOMI_%'));
        $nAud = $audQ->count();
        if ($apply && $nAud > 0) {
            $audQ->delete();
        }

        // 2c) location_tracking: supressió total al límit dur de 4 anys (la finestra
        //     "resolució del radi +1 any" la tanca abans el DPD per resolució)
        $trkOldQ = DB::table('location_tracking')->where('created_at', '<', $registreCutoff);
        $nTrkOld = $trkOldQ->count();
        if ($apply && $nTrkOld > 0) {
            $trkOldQ->delete();
        }

        $this->table(['Operació', 'Files'], [
            ['Coordenades work_logs → NULL (30 dies)', $nLogs],
            ['Coordenades trams → NULL (30 dies)', $nSegs],
            ['location_tracking lat/lng → NULL (30 dies, accuracy conservada)', $trkTeCoords ? $nTrk : 'n/a — columnes ja suprimides de la taula'],
            ['Fichatges amb incidència OBERTA (exempts)', $ambIncidencia->count()],
            ['work_logs suprimits (4 anys)', $nOld],
            ['Auditoria ubicació suprimida (4 anys)', $nAud],
            ['location_tracking suprimit (4 anys)', $nTrkOld],
        ]);

        if (! $apply) {
            $this->warn('DRY-RUN: cap canvi aplicat. Executa amb --apply.');
        }

        return self::SUCCESS;
    }
}
