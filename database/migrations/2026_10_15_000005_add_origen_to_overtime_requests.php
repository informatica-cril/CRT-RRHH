<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les sol·licituds d'hores fora d'horari ara també les pot fer el mateix treballador des del
 * comptador («Demanar autorització»). «origen» distingeix domi (coordinació les força) de
 * treballador; «work_log_id» lliga la sol·licitud a la jornada on s'han fet les hores perquè,
 * en autoritzar-la, el codi s'apliqui sol a aquell fitxatge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->string('origen', 20)->default('domi')->after('domi_ref');
            $table->foreignId('work_log_id')->nullable()->after('origen')->constrained('work_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_log_id');
            $table->dropColumn('origen');
        });
    }
};
