<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garantia DURA d'integritat de l'audiència prèvia (EIPD C6): fa IMPOSSIBLE, a nivell de BD,
 * que un tram tingui un termini d'al·legacions anterior a l'obertura, o termini/al·legació
 * sense audiència oberta. Cap seed, script o bug ho pot violar (MySQL 8+/9 fa complir CHECK).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Els CHECK amb nom són de MySQL 8+/9: a sqlite (la BD dels tests) no s'hi entra.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE work_log_segments ADD CONSTRAINT chk_audiencia_termini_no_anterior
            CHECK (audiencia_deadline IS NULL OR audiencia_requested_at IS NULL
                   OR audiencia_deadline >= DATE(audiencia_requested_at))");

        DB::statement("ALTER TABLE work_log_segments ADD CONSTRAINT chk_audiencia_termini_amb_obertura
            CHECK (audiencia_deadline IS NULL OR audiencia_requested_at IS NOT NULL)");

        DB::statement("ALTER TABLE work_log_segments ADD CONSTRAINT chk_audiencia_allegacio_amb_obertura
            CHECK (allegation IS NULL OR audiencia_requested_at IS NOT NULL)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        DB::statement("ALTER TABLE work_log_segments DROP CONSTRAINT chk_audiencia_termini_no_anterior");
        DB::statement("ALTER TABLE work_log_segments DROP CONSTRAINT chk_audiencia_termini_amb_obertura");
        DB::statement("ALTER TABLE work_log_segments DROP CONSTRAINT chk_audiencia_allegacio_amb_obertura");
    }
};
