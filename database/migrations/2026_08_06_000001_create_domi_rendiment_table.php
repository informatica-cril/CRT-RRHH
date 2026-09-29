<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domi_rendiment', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('periode_desde');
            $table->date('periode_fins');
            $table->unsignedInteger('sessions_firmades')->default(0);
            $table->unsignedTinyInteger('compliment_pct')->nullable();
            $table->unsignedTinyInteger('documental_pct')->nullable();
            $table->unsignedSmallInteger('altes_sense_informe')->default(0);
            $table->unsignedSmallInteger('processos_tancats')->nullable();
            $table->unsignedTinyInteger('adherencia_pct')->nullable();
            $table->unsignedSmallInteger('processos_sota_70pct')->nullable();
            $table->unsignedTinyInteger('puntualitat_pct')->nullable();
            $table->unsignedSmallInteger('retard_mitja_min')->nullable();
            $table->unsignedSmallInteger('aportacions')->nullable();
            $table->boolean('prou_mostra')->default(false);
            $table->unsignedSmallInteger('agraiments')->nullable();
            $table->unsignedSmallInteger('queixes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'periode_desde', 'periode_fins'], 'u_rend_periode');
        });

        Schema::create('domi_rendiment_mitjana', function (Blueprint $table) {
            $table->id();
            $table->date('periode_desde');
            $table->date('periode_fins');
            $table->unsignedSmallInteger('n')->default(0);
            $table->decimal('sessions_firmades', 8, 1)->nullable();
            $table->decimal('compliment_pct', 5, 1)->nullable();
            $table->decimal('documental_pct', 5, 1)->nullable();
            $table->timestamps();
            $table->unique(['periode_desde', 'periode_fins'], 'u_mitj_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domi_rendiment_mitjana');
        Schema::dropIfExists('domi_rendiment');
    }
};
