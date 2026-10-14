<?php

use App\Models\WorkLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fitxatges aprovats (sobretot amb l'aprovació conjunta) que tenien trams encara pendents: sortien
 * «Aprovat» amb 0 min perquè les hores comptades només sumen trams aprovats. Ara aprovar el fitxatge
 * aprova els trams pendents (WorkLog::aprovaTramsPendents); aquí es fa el mateix amb els ja aprovats.
 * Només l'estat dels trams i les hores efectives; cada canvi queda a work_log_modifications i el down() el desfà.
 */
return new class extends Migration
{
    private const MARCA = 'Correcció 14-10-2026: fitxatge aprovat amb trams pendents';

    public function up(): void
    {
        $ara = now();
        $trams = DB::table('work_log_segments as s')->join('work_logs as l', 'l.id', '=', 's.work_log_id')
            ->where('l.status', 'approved')->where('s.status', 'pending')
            ->get(['s.id', 's.work_log_id', 'l.user_id']);

        foreach ($trams->groupBy('work_log_id') as $logId => $delLog) {
            $ids = $delLog->pluck('id')->all();
            DB::table('work_log_segments')->whereIn('id', $ids)->update(['status' => 'approved', 'updated_at' => $ara]);
            DB::table('work_log_modifications')->insert([
                'work_log_id' => $logId, 'user_id' => $delLog->first()->user_id, 'action' => 'approved',
                'old_values' => json_encode(['segment_ids' => $ids, 'status' => 'pending']),
                'new_values' => json_encode(['segment_ids' => $ids, 'status' => 'approved']),
                'comment' => self::MARCA, 'created_at' => $ara, 'updated_at' => $ara,
            ]);
            WorkLog::find($logId)?->recalcularEfectivo();
        }
    }

    public function down(): void
    {
        foreach (DB::table('work_log_modifications')->where('comment', self::MARCA)->get() as $m) {
            $abans = json_decode((string) $m->old_values, true) ?: [];
            if (! empty($abans['segment_ids'])) {
                DB::table('work_log_segments')->whereIn('id', $abans['segment_ids'])->update(['status' => 'pending']);
                WorkLog::find($m->work_log_id)?->recalcularEfectivo();
            }
            DB::table('work_log_modifications')->where('id', $m->id)->delete();
        }
    }
};
