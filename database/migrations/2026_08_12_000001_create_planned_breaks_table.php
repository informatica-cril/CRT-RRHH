<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pausa diària decidida pel motor de programació de Domiciliària (mode 'auto'):
 * cada dia pot tenir una hora diferent, triada al forat natural de la ruta.
 * El fitxatge la fa complir a l'hora que digui aquesta taula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planned_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('source', 20)->default('domi');
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planned_breaks');
    }
};
