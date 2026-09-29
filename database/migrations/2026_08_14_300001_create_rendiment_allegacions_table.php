<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canal per REBATRE una xifra de rendiment (art. 20.3 ET amb informació prèvia; art. 15, 16 i 21
 * RGPD). La pantalla de Direcció ja afirmava que la persona afectada pot conèixer el seu càlcul i
 * rebatre'l; fins ara no hi havia on. Aquí queda: un escrit seu, amb data, sobre UN indicador d'UN
 * període, que arriba a Direcció i que ha de tenir resposta escrita.
 *
 * Es copia l'idioma de l'audiència prèvia dels trams (work_log_segments): text + parell de
 * marques de temps, invariants dobles (CHECK a la BD + guarda llegible al model) i escriptura
 * només del titular.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendiment_allegacions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('periode_desde');
            $table->date('periode_fins');
            // Clau de l'indicador rebatut (catàleg a PerformanceController::INDICADORS).
            $table->string('indicador', 40);
            $table->text('text');
            $table->timestamp('presentada_ts');
            $table->text('resposta')->nullable();
            $table->timestamp('resposta_ts')->nullable();
            $table->unsignedBigInteger('respost_per')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'periode_desde', 'periode_fins'], 'rend_alleg_user_periode_idx');
            // La safata de Direcció busca les que encara no tenen resposta.
            $table->index('resposta_ts', 'rend_alleg_resposta_idx');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('respost_per')->references('id')->on('users')->nullOnDelete();
        });

        // Garantia DURA: cap resposta sense data, cap data sense resposta, i cap període invertit.
        // Sense això, un script podria deixar una al·legació "contestada" sense text ni traça.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE rendiment_allegacions ADD CONSTRAINT chk_rend_alleg_resposta_amb_data
                CHECK (resposta IS NULL OR resposta_ts IS NOT NULL)");
            DB::statement("ALTER TABLE rendiment_allegacions ADD CONSTRAINT chk_rend_alleg_data_amb_resposta
                CHECK (resposta_ts IS NULL OR resposta IS NOT NULL)");
            DB::statement("ALTER TABLE rendiment_allegacions ADD CONSTRAINT chk_rend_alleg_periode
                CHECK (periode_fins >= periode_desde)");
            DB::statement("ALTER TABLE rendiment_allegacions ADD CONSTRAINT chk_rend_alleg_resposta_posterior
                CHECK (resposta_ts IS NULL OR resposta_ts >= presentada_ts)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rendiment_allegacions');
    }
};
