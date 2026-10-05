<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alumnat en pràctiques: les hores del conveni de pràctiques pactat amb el centre formatiu.
 *
 * Les hores fetes no es desen: surten dels fitxatges APROVATS dins del període, de manera que
 * quan RRHH valida un fitxatge es resten soles del total i no hi ha cap comptador que es pugui
 * desquadrar. Aquí només hi ha el que pacta el conveni.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'practiques')) {
                $table->boolean('practiques')->default(false)->after('entitat');
                $table->decimal('practiques_hores', 6, 2)->nullable()->after('practiques');
                $table->date('practiques_inici')->nullable()->after('practiques_hores');
                $table->date('practiques_fi')->nullable()->after('practiques_inici');
                $table->string('practiques_centre', 150)->nullable()->after('practiques_fi');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['practiques_centre', 'practiques_fi', 'practiques_inici', 'practiques_hores', 'practiques'] as $c) {
                if (Schema::hasColumn('users', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
