<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sortides fitxades sense GPS (registre manual) que es van validar amb coordenades buides, és a dir
 * contra el punt 0,0: sortien «fora de zona a ~4.600 km». Una sortida sense posició no té veredicte.
 * - end_location_match i end_location_distance passen a null.
 * - location_match pren el de l'entrada.
 * - Si el fitxatge era «fora de zona» només per això (entrada dins, cap pausa fora i sense hores
 *   fora de zona), hour_status torna a 'ok' (o 'extra' si té extres pendents).
 *
 * Només dades, cap columna. Cada canvi queda a work_log_modifications i el down() el desfà.
 */
return new class extends Migration
{
    private const MARCA = 'Correcció 13-10-2026: sortida sense GPS validada contra el punt 0,0';

    public function up(): void
    {
        $ara = now();
        $logs = DB::table('work_logs')
            ->whereNotNull('end_time')
            ->whereNull('end_location_lat')
            ->where(fn ($q) => $q->whereNotNull('end_location_distance')->orWhere('end_location_match', false))
            ->get(['id', 'user_id', 'end_location_match', 'end_location_distance', 'location_match', 'hour_status',
                'start_location_match', 'break_start_location_match', 'break_end_location_match',
                'hours_out_of_area', 'extra_hours_unauthorized']);

        foreach ($logs as $l) {
            $abans = ['end_location_match' => $l->end_location_match, 'end_location_distance' => $l->end_location_distance,
                'location_match' => $l->location_match, 'hour_status' => $l->hour_status];
            $nou = ['end_location_match' => null, 'end_location_distance' => null, 'location_match' => $l->start_location_match];

            $entradaDins = $l->start_location_match === null || (bool) $l->start_location_match;
            $pausaFora = ($l->break_start_location_match !== null && ! $l->break_start_location_match)
                || ($l->break_end_location_match !== null && ! $l->break_end_location_match);
            if ($l->hour_status === 'out_of_area' && $entradaDins && ! $pausaFora && (float) $l->hours_out_of_area <= 0) {
                $nou['hour_status'] = (float) $l->extra_hours_unauthorized > 0 ? 'extra' : 'ok';
            }

            DB::table('work_logs')->where('id', $l->id)->update($nou + ['updated_at' => $ara]);
            DB::table('work_log_modifications')->insert([
                'work_log_id' => $l->id, 'user_id' => $l->user_id, 'action' => 'modified',
                'old_values' => json_encode($abans), 'new_values' => json_encode($nou),
                'comment' => self::MARCA, 'created_at' => $ara, 'updated_at' => $ara,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('work_log_modifications')->where('comment', self::MARCA)->get() as $m) {
            $abans = json_decode((string) $m->old_values, true) ?: [];
            if ($abans) DB::table('work_logs')->where('id', $m->work_log_id)->update($abans);
            DB::table('work_log_modifications')->where('id', $m->id)->delete();
        }
    }
};
