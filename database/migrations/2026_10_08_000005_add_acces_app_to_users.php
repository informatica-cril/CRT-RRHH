<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «Accés a l'app»: una persona pot seguir activa a la plantilla (horaris, informes, domi pot
 * fitxar en nom seu) però sense poder iniciar sessió a aquesta app. Additiva: per defecte sí.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('acces_app')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('acces_app');
        });
    }
};
