<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fitxatges amb sortida que seguien marcats «En curs» (hour_status = in_progress): tancats amb
 * «Corregir hora» o pel pont de domi, que no actualitzaven l'estat. Passen a 'ok'.
 *
 * Només dades, cap columna. Cada canvi queda a work_log_modifications i el down() el desfà.
 */
return new class extends Migration
{
    private const MARCA = 'Correcció 08-10-2026: jornada tancada que seguia «en curs»';

    public function up(): void
    {
        $ara = now();
        DB::table('work_logs')->whereNotNull('end_time')->where('hour_status', 'in_progress')
            ->orderBy('id')->select(['id', 'user_id'])
            ->chunk(500, function ($logs) use ($ara) {
                DB::table('work_logs')->whereIn('id', $logs->pluck('id'))->update(['hour_status' => 'ok']);
                DB::table('work_log_modifications')->insert($logs->map(fn ($l) => [
                    'work_log_id' => $l->id, 'user_id' => $l->user_id, 'action' => 'modified',
                    'old_values' => json_encode(['hour_status' => 'in_progress']),
                    'new_values' => json_encode(['hour_status' => 'ok']),
                    'comment' => self::MARCA, 'created_at' => $ara, 'updated_at' => $ara,
                ])->all());
            });
    }

    public function down(): void
    {
        $ids = DB::table('work_log_modifications')->where('comment', self::MARCA)->pluck('work_log_id');
        foreach ($ids->chunk(500) as $tros) {
            DB::table('work_logs')->whereIn('id', $tros)->update(['hour_status' => 'in_progress']);
        }
        DB::table('work_log_modifications')->where('comment', self::MARCA)->delete();
    }
};
