<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PROTOCOL D'AUDIÈNCIA PRÈVIA (EIPD C6) com a màquina d'estats del tram:
     *  - audiencia_requested_at: quan s'obre l'audiència (notificació al treballador)
     *  - audiencia_deadline: fi del termini d'al·legacions — el RELLOTGE és de l'empresa:
     *    passat el termini sense al·legació, coordinació pot resoldre documentant que
     *    l'audiència es va oferir (el dret és a ser escoltat, no a bloquejar)
     *  - allegation / allegation_at: l'explicació del treballador
     * El rebuig SENSE audiència passa a ser tècnicament impossible (WorkLogSegmentController).
     */
    public function up(): void
    {
        Schema::table('work_log_segments', function (Blueprint $table) {
            $table->timestamp('audiencia_requested_at')->nullable()->after('home_radius_m');
            $table->date('audiencia_deadline')->nullable()->after('audiencia_requested_at');
            $table->text('allegation')->nullable()->after('audiencia_deadline');
            $table->timestamp('allegation_at')->nullable()->after('allegation');
        });

        // Nou tipus d'alerta per al treballador: audiència oberta sobre un marcatge
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('work_log_alerts', fn ($t) => $t->dropColumn('type'));
            \Illuminate\Support\Facades\Schema::table('work_log_alerts', fn ($t) => $t->enum('type', ['no_clock_in', 'no_clock_out', 'break_required', 'segment_rejected', 'out_of_zone', 'audiencia'])->nullable());
        } else {
            DB::statement("ALTER TABLE work_log_alerts MODIFY COLUMN type
                ENUM('no_clock_in','no_clock_out','break_required','segment_rejected','out_of_zone','audiencia') NOT NULL");
        }

        // Nou tipus de traça: al·legació presentada
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->dropColumn('action'));
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->enum('action', ['created', 'segmented', 'approved', 'rejected', 'modified', 'break_started', 'break_completed', 'break_skipped', 'registre_manual', 'allegacio'])->nullable());
        } else {
            DB::statement("ALTER TABLE work_log_modifications MODIFY COLUMN action
                ENUM('created','segmented','approved','rejected','modified',
                     'break_started','break_completed','break_skipped','registre_manual','allegacio') NOT NULL");
        }
    }

    public function down(): void
    {
        Schema::table('work_log_segments', function (Blueprint $table) {
            $table->dropColumn(['audiencia_requested_at', 'audiencia_deadline', 'allegation', 'allegation_at']);
        });
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\Schema::table('work_log_alerts', fn ($t) => $t->dropColumn('type'));
            \Illuminate\Support\Facades\Schema::table('work_log_alerts', fn ($t) => $t->enum('type', ['no_clock_in', 'no_clock_out', 'break_required', 'segment_rejected', 'out_of_zone'])->nullable());
        } else {
            DB::statement("ALTER TABLE work_log_alerts MODIFY COLUMN type
                ENUM('no_clock_in','no_clock_out','break_required','segment_rejected','out_of_zone') NOT NULL");
        }
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->dropColumn('action'));
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->enum('action', ['created', 'segmented', 'approved', 'rejected', 'modified', 'break_started', 'break_completed', 'break_skipped', 'registre_manual'])->nullable());
        } else {
            DB::statement("ALTER TABLE work_log_modifications MODIFY COLUMN action
                ENUM('created','segmented','approved','rejected','modified',
                     'break_started','break_completed','break_skipped','registre_manual') NOT NULL");
        }
    }
};
