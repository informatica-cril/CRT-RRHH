<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fitxatges pendents que mostraven «-Xh (Fora Zona)» quan els trams ja les donaven per
 * bones: l'entrada és dins de zona (de primer o després de recalcular la zona), cap marca de
 * pausa no és fora i no queda cap tram fora de zona pendent. Per la regla de segmentació
 * (Direcció, 08-08-2026) una sortida fora de zona no descompta el temps anterior, així que
 * aquelles hores no es descompten: hours_out_of_area torna a 0 i hours_worked a les brutes.
 * La marca de sortida (hour_status i alerta) es manté per revisar-la.
 *
 * Només dades, cap columna. Cada canvi queda a work_log_modifications amb els valors
 * anteriors, i el down() els hi torna.
 */
return new class extends Migration
{
    private const MARCA = 'Correcció 08-10-2026: hores fora de zona ja resoltes pels trams';

    public function up(): void
    {
        $logs = DB::table('work_logs')
            ->where('status', 'pending')
            ->where('segmented', true)
            ->where('hours_out_of_area', '>', 0)
            ->where('start_location_match', true)
            ->where(fn ($q) => $q->whereNull('break_start_location_match')->orWhere('break_start_location_match', true))
            ->where(fn ($q) => $q->whereNull('break_end_location_match')->orWhere('break_end_location_match', true))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('work_log_segments')
                ->whereColumn('work_log_segments.work_log_id', 'work_logs.id')
                ->where('work_log_segments.in_zone', false)
                ->where('work_log_segments.status', 'pending'))
            ->get(['id', 'user_id', 'hours_out_of_area', 'hours_worked', 'total_hours_worked']);

        $ara = now();
        foreach ($logs as $l) {
            DB::table('work_logs')->where('id', $l->id)->update([
                'hours_out_of_area' => 0,
                'hours_worked' => $l->total_hours_worked,
                'updated_at' => $ara,
            ]);
            DB::table('work_log_modifications')->insert([
                'work_log_id' => $l->id, 'user_id' => $l->user_id, 'action' => 'modified',
                'old_values' => json_encode(['hours_out_of_area' => $l->hours_out_of_area, 'hours_worked' => $l->hours_worked]),
                'new_values' => json_encode(['hours_out_of_area' => 0, 'hours_worked' => $l->total_hours_worked]),
                'comment' => self::MARCA, 'created_at' => $ara, 'updated_at' => $ara,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('work_log_modifications')->where('comment', self::MARCA)->get() as $m) {
            $abans = json_decode((string) $m->old_values, true) ?: [];
            if ($abans) {
                DB::table('work_logs')->where('id', $m->work_log_id)->update($abans);
            }
            DB::table('work_log_modifications')->where('id', $m->id)->delete();
        }
    }
};
