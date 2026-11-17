<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derivació d'incidències: a qui s'envia («rrhh» per defecte, com fins ara, o «informatica») i quines
 * persones són de l'equip d'informàtica (poden veure i respondre només les que els arriben).
 * Només afegeix columnes: no toca el rol ni cap dada existent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('incidencies', 'destinatari')) {
            Schema::table('incidencies', fn (Blueprint $t) => $t->string('destinatari', 20)->default('rrhh')->after('tipus'));
        }
        if (! Schema::hasColumn('users', 'es_informatica')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('es_informatica')->default(false));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('incidencies', 'destinatari')) {
            Schema::table('incidencies', fn (Blueprint $t) => $t->dropColumn('destinatari'));
        }
        if (Schema::hasColumn('users', 'es_informatica')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('es_informatica'));
        }
    }
};
