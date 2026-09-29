<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacte d'hores complementàries (art. 12.5 ET): amb el tick actiu, la jornada es pot
 * estendre fins al 30% de la bàsica contractada. La planificació des de domi es limita
 * al 28% (el 2% restant queda reservat per a extensions de sessions demanades pel
 * mateix fisio a l'última sessió).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('pacte_complementaries')->default(false)
                ->after('device_phone')
                ->comment('Pacte hores complementaries signat (art. 12.5 ET)');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pacte_complementaries');
        });
    }
};
