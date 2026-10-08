<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Alertes «fora de zona» actives de fitxatges on cap marca no és fora de zona: la sortida no
 * té marca (corregida amb «Corregir hora») i l'entrada i la pausa eren dins. Les creava la
 * resegmentació, que prenia «sense marca» per «fora de zona» (corregit a WorkLogService), i la
 * persona les veia com a bàner vermell cada dia. Es descarten.
 *
 * Només dades, cap columna. El down() torna a obrir les mateixes alertes.
 */
return new class extends Migration
{
    private const MARCA = '2026-10-08 00:00:04';   // dismissed_at amb què es reconeixen

    public function up(): void
    {
        $ids = DB::table('work_log_alerts as a')->join('work_logs as w', 'w.id', '=', 'a.work_log_id')
            ->where('a.type', 'out_of_zone')->whereNull('a.dismissed_at')
            ->whereNull('w.end_location_match')
            ->where('w.start_location_match', true)
            ->where(fn ($q) => $q->whereNull('w.break_start_location_match')->orWhere('w.break_start_location_match', true))
            ->where(fn ($q) => $q->whereNull('w.break_end_location_match')->orWhere('w.break_end_location_match', true))
            ->pluck('a.id');

        DB::table('work_log_alerts')->whereIn('id', $ids)->update(['dismissed_at' => self::MARCA]);
    }

    public function down(): void
    {
        DB::table('work_log_alerts')->where('type', 'out_of_zone')->where('dismissed_at', self::MARCA)
            ->update(['dismissed_at' => null]);
    }
};
