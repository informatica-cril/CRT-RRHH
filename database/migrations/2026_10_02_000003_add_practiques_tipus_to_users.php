<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipus de conveni de pràctiques (FP, universitat, no laborals…) i les dades pròpies de cadascun.
 *
 * Cada modalitat demana coses diferents (ECTS a la universitat, cicle i tutor a l'FP, beca a les no
 * laborals): en lloc d'una columna per a cada dada, el detall va en JSON i el valida el controlador
 * segons el tipus. El tipus sí que és columna perquè s'hi filtra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'practiques_tipus')) {
                $table->string('practiques_tipus', 30)->nullable()->after('practiques');
                $table->json('practiques_detall')->nullable()->after('practiques_centre');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['practiques_detall', 'practiques_tipus'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
