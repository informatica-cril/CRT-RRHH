<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('break_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->integer('threshold_hours')->default(5)
                ->comment('Horas seguidas antes de pausa obligatoria');
            $table->integer('break_duration_minutes')->default(20);
            $table->boolean('auto_start')->default(false)
                ->comment('Si true, la pausa empieza automáticamente al superar el umbral');
            $table->integer('grace_period_minutes')->default(0)
                ->comment('Minutos de tolerancia antes de forzar');
            $table->timestamps();
        });

        // Insertar configuración por defecto
        DB::table('break_settings')->insert([
            'enabled' => true,
            'threshold_hours' => 5,
            'break_duration_minutes' => 20,
            'auto_start' => false,
            'grace_period_minutes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('break_settings');
    }
};
