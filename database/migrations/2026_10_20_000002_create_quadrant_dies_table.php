<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quadrant (RRHH, 19-10-2026): RRHH i admin canvien l'horari d'una persona dia a dia (els fisios
 * d'ambulatori fan un horari un mes i un altre el mes següent). Un dia amb fila aquí mana sobre la
 * plantilla setmanal de la fitxa; un dia sense fila segueix la plantilla com fins ara. Additiu.
 *   trams             [{start: "HH:MM", end: "HH:MM"}, ...]  (jornada partida = més d'un tram)
 *   lliure            el dia no es treballa (trams buit)
 *   work_schedule_id  plantilla d'on es van treure els trams (per al nom i el color), o null si és a mida
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quadrant_dies')) {
            Schema::create('quadrant_dies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('dia');
                $table->json('trams')->nullable();
                $table->boolean('lliure')->default(false);
                $table->foreignId('work_schedule_id')->nullable()->constrained()->nullOnDelete();
                $table->string('nota', 255)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'dia']);
                $table->index('dia');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quadrant_dies');
    }
};
