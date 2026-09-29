<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Afegeix el mode 'auto' d'inici de pausa: la col·locació del bloc la decideix
 * el planificador de la Domiciliària cada dia, triant el buit entre visites que
 * no bloqueja cap tram aprofitable de la jornada. El singleton passa d'offset a
 * auto (decisió de Direcció, 05-08-2026); l'override d'hora fixa per treballador
 * continua guanyant sobre el mode global.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MODIFY és sintaxi de MySQL: a sqlite (la BD dels tests) petava i deixava
        // TOTA la bateria sense poder arrencar. La columna s'hi refà com a text.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE break_settings MODIFY break_start_mode ENUM('offset','fixed','auto') NOT NULL DEFAULT 'auto'");
        } else {
            Schema::table('break_settings', function (Blueprint $table) {
                $table->string('break_start_mode', 10)->default('auto')->change();
            });
        }
        DB::table('break_settings')->where('break_start_mode', 'offset')->update(['break_start_mode' => 'auto']);
    }

    public function down(): void
    {
        DB::table('break_settings')->where('break_start_mode', 'auto')->update(['break_start_mode' => 'offset']);
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE break_settings MODIFY break_start_mode ENUM('offset','fixed') NOT NULL DEFAULT 'offset'");
        }
    }
};
