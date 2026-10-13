<?php

use App\Models\WorkLog;
use App\Services\WorkLogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Marge d'horari (WorkLogService::MARGE_HORARI_MIN) per als fitxatges ja fets: els trams fora d'horari
 * curts (com els 17 segons de sortir a les 16:00:17), dins de zona, pendents i tocant a un tram d'horari
 * aprovat dins de zona, passen a aprovats. És el que ara fa la segmentació en ajuntar-los.
 * Només l'estat del tram i les hores efectives; cada canvi queda a work_log_modifications i el down() el desfà.
 */
return new class extends Migration
{
    private const MARCA = 'Correcció 13-10-2026: marge d\'horari, tram curt fora d\'horari';

    public function up(): void
    {
        $ara = now();
        $curts = DB::table('work_log_segments as s')->join('work_logs as l', 'l.id', '=', 's.work_log_id')
            ->where('s.status', 'pending')->where('s.in_schedule', false)->where('s.in_zone', true)
            ->whereNull('s.kind')->where('s.duration_minutes', '<=', WorkLogService::MARGE_HORARI_MIN)
            ->get(['s.id', 's.work_log_id', 's.segment_number', 'l.user_id']);

        foreach ($curts as $s) {
            $veiAprovat = DB::table('work_log_segments')->where('work_log_id', $s->work_log_id)
                ->whereIn('segment_number', [$s->segment_number - 1, $s->segment_number + 1])
                ->where('status', 'approved')->where('in_schedule', true)->where('in_zone', true)->exists();
            if (! $veiAprovat) continue;

            DB::table('work_log_segments')->where('id', $s->id)->update(['status' => 'approved', 'updated_at' => $ara]);
            DB::table('work_log_modifications')->insert([
                'work_log_id' => $s->work_log_id, 'user_id' => $s->user_id, 'action' => 'approved',
                'old_values' => json_encode(['segment_id' => $s->id, 'status' => 'pending']),
                'new_values' => json_encode(['segment_id' => $s->id, 'status' => 'approved']),
                'comment' => self::MARCA, 'created_at' => $ara, 'updated_at' => $ara,
            ]);
            WorkLog::find($s->work_log_id)?->recalcularEfectivo();
        }
    }

    public function down(): void
    {
        foreach (DB::table('work_log_modifications')->where('comment', self::MARCA)->get() as $m) {
            $abans = json_decode((string) $m->old_values, true) ?: [];
            if (! empty($abans['segment_id'])) {
                DB::table('work_log_segments')->where('id', $abans['segment_id'])->update(['status' => 'pending']);
                WorkLog::find($m->work_log_id)?->recalcularEfectivo();
            }
            DB::table('work_log_modifications')->where('id', $m->id)->delete();
        }
    }
};
