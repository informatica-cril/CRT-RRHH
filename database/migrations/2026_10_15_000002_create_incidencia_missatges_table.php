<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Converses de les incidències dins de l'app (RRHH respon i el treballador contesta) en lloc del correu.
 * incidencies.vist_treballador_at: última vegada que el treballador va obrir les seves incidències,
 * per avisar-lo de les respostes noves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidencia_missatges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incidencia_id')->constrained('incidencies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('text');
            $table->timestamps();
            $table->index('incidencia_id');
        });
        Schema::table('incidencies', function (Blueprint $table) {
            $table->timestamp('vist_treballador_at')->nullable()->after('nota_resolucio');
        });
    }

    public function down(): void
    {
        Schema::table('incidencies', fn (Blueprint $table) => $table->dropColumn('vist_treballador_at'));
        Schema::dropIfExists('incidencia_missatges');
    }
};
