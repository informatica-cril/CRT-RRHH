<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SOL·LICITUDS D'EXERCICI DE DRETS (RGPD art. 15 a 22).
 *
 * La pantalla de privadesa oferia sis botons que deien a la persona «sol·licitud enviada, rebrà
 * resposta en 30 dies» i al darrere no hi havia ni crida ni desat: era un `alert()` del navegador.
 * Una sol·licitud de drets que es perd no és un detall d'interfície —el termini de l'art. 12.3 RGPD
 * corre des que s'ha rebut, i l'empresa quedava obligada per una promesa de la qual no li arribava
 * constància—. Aquí queda on desar-la i des d'on respondre-la.
 *
 * Es copia el patró de rendiment_allegacions: text + parell de marques de temps, invariants dobles
 * (CHECK a la BD i validació al controlador) i escriptura només del titular. La resposta no s'envia
 * per cap canal exterior: es desa i la Safata de pendents la mostra fins que algú la contesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('drets_sollicituds')) {
            return;
        }

        Schema::create('drets_sollicituds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            // Els sis drets que la política enumera. Enum i no text lliure: el que s'ofereix a la
            // pantalla i el que es pot desar han de ser la mateixa llista.
            $table->enum('dret', ['acces', 'rectificacio', 'supressio', 'limitacio', 'portabilitat', 'oposicio']);
            $table->text('detall')->nullable();
            $table->timestamp('presentada_ts');
            // Venciment de l'art. 12.3 RGPD (un mes). Es desa perquè el termini es compta des de la
            // recepció: si es calculés a la vista, canviaria de valor cada cop que es reobrís.
            $table->date('venciment');
            $table->string('ip', 45)->nullable();
            $table->text('resposta')->nullable();
            $table->timestamp('resposta_ts')->nullable();
            $table->unsignedBigInteger('respost_per')->nullable();
            $table->timestamps();

            $table->index('resposta_ts', 'drets_resposta_idx');
            $table->index(['user_id', 'presentada_ts'], 'drets_user_idx');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('respost_per')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE drets_sollicituds ADD CONSTRAINT chk_drets_resposta_amb_data
                CHECK (resposta IS NULL OR resposta_ts IS NOT NULL)');
            DB::statement('ALTER TABLE drets_sollicituds ADD CONSTRAINT chk_drets_data_amb_resposta
                CHECK (resposta_ts IS NULL OR resposta IS NOT NULL)');
            DB::statement('ALTER TABLE drets_sollicituds ADD CONSTRAINT chk_drets_resposta_posterior
                CHECK (resposta_ts IS NULL OR resposta_ts >= presentada_ts)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('drets_sollicituds');
    }
};
