<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aprovació automàtica dels fitxatges correctes (RRHH, 16-10-2026: aprovar-los tots d'un en un
 * cada dia era inassumible). Additiu:
 *   aprovacio_automatica_settings  interruptor i marge de minuts (una sola fila)
 *   work_logs.auto_approved_at     quan el va aprovar l'app sola (null = aprovat per una persona)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('aprovacio_automatica_settings')) {
            Schema::create('aprovacio_automatica_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(true);
                $table->unsignedSmallInteger('marge_minuts')->default(10);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('work_logs', 'auto_approved_at')) {
            Schema::table('work_logs', function (Blueprint $table) {
                $table->timestamp('auto_approved_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('work_logs', 'auto_approved_at')) {
            Schema::table('work_logs', fn (Blueprint $table) => $table->dropColumn('auto_approved_at'));
        }
        Schema::dropIfExists('aprovacio_automatica_settings');
    }
};
