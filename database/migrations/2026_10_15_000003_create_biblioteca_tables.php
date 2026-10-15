<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentació: biblioteca de consulta (carpetes i fitxers) per a tota la plantilla. Diferent de
 * «documents», que són avisos a persones concretes amb firma. Els fitxers van al disc privat
 * (storage/app/biblioteca) i només es descarreguen amb sessió.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biblioteca_carpetes', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 150);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();
        });
        Schema::create('biblioteca_fitxers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carpeta_id')->constrained('biblioteca_carpetes')->cascadeOnDelete();
            $table->string('nom', 255);
            $table->string('path', 255);
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('mida')->default(0);
            $table->foreignId('pujat_per')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('carpeta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biblioteca_fitxers');
        Schema::dropIfExists('biblioteca_carpetes');
    }
};
