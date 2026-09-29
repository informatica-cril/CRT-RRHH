<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('break_settings', function (Blueprint $table) {
            $table->enum('break_start_mode', ['offset', 'fixed'])->default('offset')
                ->comment('offset = X minuts després de l\'inici del fitxatge; fixed = a una hora fixa configurada')
                ->after('grace_period_minutes');
            $table->integer('break_start_offset_minutes')->default(300)
                ->comment('Minuts després de l\'inici del fitxatge per activar la pausa (mode offset)')
                ->after('break_start_mode');
            $table->time('break_start_fixed_time')->default('12:00:00')
                ->comment('Hora fixa a la qual s\'activa la pausa (mode fixed)')
                ->after('break_start_offset_minutes');
        });

        // Actualitzar el registre existent amb valors per defecte coherents
        DB::table('break_settings')->update([
            'break_start_mode' => 'offset',
            'break_start_offset_minutes' => 300,
            'break_start_fixed_time' => '12:00:00',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('break_settings', function (Blueprint $table) {
            $table->dropColumn(['break_start_mode', 'break_start_offset_minutes', 'break_start_fixed_time']);
        });
    }
};
