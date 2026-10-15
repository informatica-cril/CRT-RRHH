<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estat de gestió de les incidències (només el veuen RRHH i Superior): nova, en_curs, esperant,
 * resolta, descartada. El treballador continua veient només «estat» (oberta / resolta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidencies', function (Blueprint $table) {
            $table->string('estat_gestio', 20)->default('nova')->after('estat');
        });
        DB::table('incidencies')->where('estat', 'resolta')->update(['estat_gestio' => 'resolta']);
    }

    public function down(): void
    {
        Schema::table('incidencies', fn (Blueprint $table) => $table->dropColumn('estat_gestio'));
    }
};
