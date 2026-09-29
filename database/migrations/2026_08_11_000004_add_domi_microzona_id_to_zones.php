<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vincle amb la sectorització de domi (11-08-2026).
 *
 * Les zones i microzones LES DECIDEIX Coordinació a l'app domiciliària (CP sencers
 * o subzones dibuixades a mà) i es publiquen aquí perquè RRHH pugui assignar-hi
 * treballadors. Aquesta columna és el vincle d'anada i tornada: una zona amb
 * domi_microzona_id la governa domi (nom, CP, estat); una zona sense, és local de RRHH.
 * La GEOMETRIA fina (polígons de subzona) no viatja: viu a domi, que és qui pinta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->unsignedBigInteger('domi_microzona_id')->nullable()->after('municipalities');
            $table->unique('domi_microzona_id');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropUnique(['domi_microzona_id']);
            $table->dropColumn('domi_microzona_id');
        });
    }
};
