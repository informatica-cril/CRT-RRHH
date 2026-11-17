<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Document ocult: RRHH el pot amagar als treballadors sense esborrar-lo (conserva firmes i historial).
 * Additiva i reversible; per defecte tots els documents són visibles.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->boolean('ocult')->default(false)->after('is_urgent');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $t) {
            $t->dropColumn('ocult');
        });
    }
};
