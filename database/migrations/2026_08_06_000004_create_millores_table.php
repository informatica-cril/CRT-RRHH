<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servei_senyals', function (Blueprint $table) {
            $table->id();
            $table->string('servei', 20);
            $table->string('clau', 80);
            $table->json('valor');
            $table->timestamp('rebut_ts');
            $table->timestamps();
            $table->unique(['servei', 'clau']);
        });

        Schema::create('millores', function (Blueprint $table) {
            $table->id();
            $table->string('servei', 20)->default('rrhh');
            $table->string('ambit', 60);
            $table->string('titol', 200);
            $table->text('problema');
            $table->text('proposta');
            $table->text('evidencia');
            $table->enum('impacte', ['alt', 'mitja', 'baix'])->default('mitja');
            $table->enum('esforc', ['petit', 'mitja', 'gran'])->default('mitja');
            $table->enum('estat', ['nova', 'acceptada', 'descartada', 'feta', 'preparada', 'preparacio_fallida'])
                  ->default('nova');
            $table->string('nota', 500)->nullable();
            $table->unsignedBigInteger('decidit_per')->nullable();
            $table->timestamp('decidit_ts')->nullable();
            $table->string('model', 60)->nullable();
            $table->timestamps();
            $table->index(['estat', 'impacte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('millores');
        Schema::dropIfExists('servei_senyals');
    }
};
