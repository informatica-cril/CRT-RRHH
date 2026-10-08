<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fitxatges aprovats amb hores brutes més grans que el temps entre entrada i sortida: l'aprovació
 * d'un fitxatge fora de zona hi sumava les hores de fora de zona (corregit a WorkLog::
 * canvisRestauraForaZona). total_hours_worked i hours_worked tornen a les brutes reals.
 * L'efectiu no es toca (surt dels trams).
 *
 * Només dades, cap columna. Cada canvi queda a work_log_modifications i el down() el desfà.
 */
return new class extends Migration
{
    private const MARCA = 'Correcció 08-10-2026: hores brutes duplicades en aprovar fora de zona';

    public function up(): void
    {
        $ara = now();
        $logs = DB::table('work_logs')->whereNotNull('end_time')->whereNotNull('start_time')
            ->get(['id', 'user_id', 'start_time', 'end_time', 'total_hours_worked', 'hours_worked']);

        foreach ($logs as $l) {
            $brutes = round(max(0, strtotime($l->end_time) - strtotime($l->start_time)) / 3600, 2);
            if ((float) $l->total_hours_worked <= $brutes + 0.1) continue;

            DB::table('work_logs')->where('id', $l->id)->update([
                'total_hours_worked' => $brutes, 'hours_worked' => min((float) $l->hours_worked, $brutes), 'updated_at' => $ara,
            ]);
            DB::table('work_log_modifications')->insert([
                'work_log_id' => $l->id, 'user_id' => $l->user_id, 'action' => 'modified',
                'old_values' => json_encode(['total_hours_worked' => $l->total_hours_worked, 'hours_worked' => $l->hours_worked]),
                'new_values' => json_encode(['total_hours_worked' => $brutes, 'hours_worked' => min((float) $l->hours_worked, $brutes)]),
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
