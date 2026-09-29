<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tiempo de trabajo EFECTIVO: solo cuentan los tramos aprobados.
     * Se mantiene total_hours_worked como valor bruto (fin - inicio) para auditoría.
     */
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->decimal('effective_hours', 6, 2)->nullable()->after('hours_worked');
        });

        // Backfill de los fichajes existentes
        foreach (DB::table('work_logs')->get() as $log) {
            if ($log->segmented) {
                $mins = DB::table('work_log_segments')
                    ->where('work_log_id', $log->id)
                    ->where('status', 'approved')
                    ->sum('duration_minutes');
                $eff = round($mins / 60, 2);
            } else {
                // Sin segmentar: el efectivo es el mismo que las horas trabajadas ya calculadas
                $eff = $log->hours_worked;
            }
            DB::table('work_logs')->where('id', $log->id)->update(['effective_hours' => $eff]);
        }
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('effective_hours');
        });
    }
};
