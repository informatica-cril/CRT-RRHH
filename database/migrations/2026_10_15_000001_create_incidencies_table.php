<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Incidències que envien els treballadors des de l'app; les gestionen RRHH i Superior. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('tipus', 30);
            $table->string('assumpte', 150);
            $table->text('descripcio');
            $table->string('estat', 20)->default('oberta');   // oberta | resolta
            $table->foreignId('resolta_per')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolta_at')->nullable();
            $table->text('nota_resolucio')->nullable();
            $table->timestamps();
            $table->index('estat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidencies');
    }
};
