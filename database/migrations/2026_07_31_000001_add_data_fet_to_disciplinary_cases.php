<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data de COMISSIÓ del fet al cas disciplinari (ET art. 60.2).
 *
 * Sense aquesta columna el cas només desava la data de CONEIXEMENT, i qualsevol recàlcul de la
 * prescripció (per exemple en requalificar la gravetat) perdia el topall dur dels 6 mesos des de la
 * comissió: una falta ja prescrita podia "reviure" més enllà del límit legal absolut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->date('data_fet')->nullable()->after('data_coneixement');
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->dropColumn('data_fet');
        });
    }
};
