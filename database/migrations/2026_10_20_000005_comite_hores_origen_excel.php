<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hores sindicals de l'Excel de RRHH al Comitè (RRHH, 20-10-2026). Additiu:
 *   comite_hores.origen_ref  clau de la fila de l'Excel d'on surt l'hora: tornar a importar el mateix
 *                            Excel no la duplica. L'Excel no porta franja horària (només la durada):
 *                            les hores importades es desen a partir de les 00:00 i la pantalla no
 *                            mostra la franja quan hi ha origen_ref.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('comite_hores', 'origen_ref')) {
            Schema::table('comite_hores', function (Blueprint $table) {
                $table->string('origen_ref', 40)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comite_hores', 'origen_ref')) {
            Schema::table('comite_hores', function (Blueprint $table) {
                $table->dropUnique(['origen_ref']);
                $table->dropColumn('origen_ref');
            });
        }
    }
};
