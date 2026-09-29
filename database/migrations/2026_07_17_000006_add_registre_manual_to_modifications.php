<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Nou tipus de traça: 'registre_manual' — registre de jornada/hito sense GPS amb
     * justificació del treballador (mètode alternatiu, EIPD §6.4). Additiu.
     */
    public function up(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->dropColumn('action'));
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->enum('action', ['created', 'segmented', 'approved', 'rejected', 'modified', 'break_started', 'break_completed', 'break_skipped', 'registre_manual'])->nullable());
            return;
        }
        DB::statement("ALTER TABLE work_log_modifications MODIFY COLUMN action
            ENUM('created','segmented','approved','rejected','modified',
                 'break_started','break_completed','break_skipped','registre_manual') NOT NULL");
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->dropColumn('action'));
            \Illuminate\Support\Facades\Schema::table('work_log_modifications', fn ($t) => $t->enum('action', ['created', 'segmented', 'approved', 'rejected', 'modified', 'break_started', 'break_completed', 'break_skipped'])->nullable());
            return;
        }
        DB::statement("ALTER TABLE work_log_modifications MODIFY COLUMN action
            ENUM('created','segmented','approved','rejected','modified',
                 'break_started','break_completed','break_skipped') NOT NULL");
    }
};
