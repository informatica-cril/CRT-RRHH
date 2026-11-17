<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Horari rotatiu (opcional): la plantilla de sempre (`days`) és la setmana 1 i `rotacio` porta
 * {"inici": "AAAA-MM-DD" (dilluns de la setmana 1), "setmanes": [dies setmana 2, dies setmana 3, …]}.
 * NULL = horari fix de sempre. Additiva i reversible.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('work_schedules', function (Blueprint $t) {
            $t->json('rotacio')->nullable()->after('days');
        });
    }

    public function down(): void
    {
        Schema::table('work_schedules', function (Blueprint $t) {
            $t->dropColumn('rotacio');
        });
    }
};
