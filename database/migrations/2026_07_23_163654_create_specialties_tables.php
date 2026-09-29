<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Especialitat clínica del treballador (fisioterapeuta), configurable des de RRHH.
 * FONT DE VERITAT per a l'atribució de pacients a domi. N:M: un fisio pot tenir-ne
 * diverses. Els `code` han de coincidir amb els de domi (crt_especialitat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // general, respiratori, sol_pelvia, limfatic
            $table->string('name', 60);
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('specialty_user', function (Blueprint $table) {
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['specialty_id', 'user_id']);
        });

        // Catàleg inicial (mateixos codis que domi). insertOrIgnore -> idempotent.
        DB::table('specialties')->insertOrIgnore([
            ['code' => 'general',     'name' => 'Rehabilitació general', 'sort' => 1, 'active' => true],
            ['code' => 'respiratori', 'name' => 'Respiratòria',          'sort' => 2, 'active' => true],
            ['code' => 'sol_pelvia',  'name' => 'Sòl pelvià',            'sort' => 3, 'active' => true],
            ['code' => 'limfatic',    'name' => 'Drenatge limfàtic',     'sort' => 4, 'active' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('specialty_user');
        Schema::dropIfExists('specialties');
    }
};
