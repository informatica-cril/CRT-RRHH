<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rastre de les decisions (quan es va decidir i per què es va denegar) i
 * justificant mèdic de l'absència. Idempotent: es pot tornar a passar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            if (! Schema::hasColumn('absences', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('absences', 'denial_reason')) {
                $table->text('denial_reason')->nullable()->after('approved_at');
            }
            // Part mèdic / justificant: 12 dels 14 tipus d'absència en demanen un i
            // no hi havia manera de pujar-lo. Dada de salut → disc privat, mai públic.
            if (! Schema::hasColumn('absences', 'justificant_path')) {
                $table->string('justificant_path')->nullable();
            }
            if (! Schema::hasColumn('absences', 'justificant_name')) {
                $table->string('justificant_name')->nullable();
            }
            if (! Schema::hasColumn('absences', 'justificant_uploaded_at')) {
                $table->dateTime('justificant_uploaded_at')->nullable();
            }
        });

        Schema::table('excedencias', function (Blueprint $table) {
            if (! Schema::hasColumn('excedencias', 'approved_at')) {
                $table->dateTime('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('excedencias', 'denial_reason')) {
                $table->text('denial_reason')->nullable()->after('approved_at');
            }
        });

        /* Les resolucions de gestió viatgen pel mecanisme d'avisos que ja existeix.
           La columna és un ENUM i cal ampliar-lo: a MySQL amb MODIFY i a sqlite (tests)
           refent-la com a text — allà l'enum és un CHECK que també rebutjaria el valor nou. */
        if (DB::getDriverName() !== 'mysql') {
            Schema::table('work_log_alerts', function (Blueprint $table) {
                $table->string('type', 30)->change();
            });
        }
        if (DB::getDriverName() === 'mysql') {
            $tipus = (string) DB::selectOne(
                "SELECT COLUMN_TYPE ct FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'work_log_alerts' AND COLUMN_NAME = 'type'"
            )?->ct;
            if ($tipus !== '' && ! str_contains($tipus, "'resolucio_rrhh'")) {
                DB::statement("ALTER TABLE work_log_alerts MODIFY type ENUM(
                    'no_clock_in','no_clock_out','break_required','segment_rejected',
                    'out_of_zone','audiencia','resolucio_rrhh') NOT NULL");
            }
        }
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            foreach (['approved_at', 'denial_reason', 'justificant_path', 'justificant_name', 'justificant_uploaded_at'] as $c) {
                if (Schema::hasColumn('absences', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
        Schema::table('excedencias', function (Blueprint $table) {
            foreach (['approved_at', 'denial_reason'] as $c) {
                if (Schema::hasColumn('excedencias', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
