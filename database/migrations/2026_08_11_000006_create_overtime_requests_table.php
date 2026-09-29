<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sol·licituds d'hores EXTRAORDINÀRIES forçades des de domi (Coordinació) que
 * necessiten l'autorització de RRHH com a compromís de pagament ABANS de programar-se.
 * Flux: domi crea (pending) → RRHH autoritza (emet authorization_code 1,25×) → domi programa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('hours', 5, 2);                 // hores extraordinàries sol·licitades
            $table->date('period_from');
            $table->date('period_to');
            $table->string('requested_by', 120);            // qui ho força a domi (coordinació)
            $table->string('reason', 500)->nullable();
            $table->string('domi_ref', 120)->nullable();    // referència del bloc a domi (per lligar-ho)
            $table->enum('status', ['pending', 'authorized', 'denied'])->default('pending');
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->foreignId('authorization_code_id')->nullable()->constrained('authorization_codes')->nullOnDelete();
            $table->string('denial_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('domi_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
