<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipus de relació del treballador: laboral o autònom (col·laborador no laboral).
 * Vegeu domi_crt.gt/docs/PROPOSTA-COLABORADOR-AUTONOM.md.
 *
 * NULLABLE A PROPÒSIT: els usuaris existents queden a NULL i el sistema els tracta
 * com a LABORALS (el costat segur: el registre horari de l'art. 34.9 ET és obligatori
 * per a ells). Cap valor per defecte que "classifiqui" 78 persones sense que RRHH
 * les hagi revisat una a una. La pantalla d'alta sí que exigeix triar.
 *
 * L'autònom: NO fitxa (els endpoints /domi/* de jornada li responen 409), no li
 * aplica el límit d'hores complementàries, i en lloc d'horari (work_schedule) té
 * disponibilitat_setmanal, que autogestiona des de domi amb marge > 72 h.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('relacio', ['laboral', 'autonom'])->nullable()
                ->after('pacte_complementaries')
                ->comment('Tipus de relacio. NULL = no classificat = es tracta com laboral');
            $table->decimal('disponibilitat_setmanal', 5, 2)->nullable()
                ->after('relacio')
                ->comment('Nomes autonoms: hores/setmana ofertes (substitueix work_schedule)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['relacio', 'disponibilitat_setmanal']);
        });
    }
};
