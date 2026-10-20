<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fitxatge automàtic (Direcció, 20-10-2026): les persones de gerència s'oblidaven de fitxar. Per a
 * qui ho té activat (només ho pot fer admin), l'app crea cada dia laborable l'entrada i la sortida
 * amb l'horari del dia (quadrant o plantilla). Additiu:
 *   users.fitxatge_automatic (+ qui i quan ho va activar)
 *   work_logs.fitxatge_automatic  el fitxatge l'ha creat l'app, no la persona
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'fitxatge_automatic')) {
                $table->boolean('fitxatge_automatic')->default(false);
                $table->foreignId('fitxatge_automatic_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('fitxatge_automatic_at')->nullable();
            }
        });
        Schema::table('work_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('work_logs', 'fitxatge_automatic')) {
                $table->boolean('fitxatge_automatic')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            if (Schema::hasColumn('work_logs', 'fitxatge_automatic')) $table->dropColumn('fitxatge_automatic');
        });
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fitxatge_automatic')) {
                $table->dropForeign(['fitxatge_automatic_by']);
                $table->dropColumn(['fitxatge_automatic', 'fitxatge_automatic_by', 'fitxatge_automatic_at']);
            }
        });
    }
};
