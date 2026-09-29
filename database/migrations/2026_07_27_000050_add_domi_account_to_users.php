<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte d'accés a Domiciliària associat a la persona. RRHH és la font de veritat del
 * personal (inclòs quin usuari/perfil tindrà a domi); domi crea la compte llegint-ho
 * d'aquí pel canal d'integració existent (bulk-index). No acobla lògica de domi a RRHH:
 * només diu QUI és a domi. Vegeu docs/DESPLEGAMENT-INFORMATICA.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('domi_username', 15)->nullable()->after('second_factor_at');
            $table->string('domi_privilege', 30)->nullable()->after('domi_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['domi_username', 'domi_privilege']);
        });
    }
};
