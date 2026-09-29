<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pacte d'hores complementàries, acceptat per la persona treballadora i PER QUINZENA.
 *
 * ENCÀRREC (Direcció, 03-08-2026): el professional ha de poder autoritzar les seves hores
 * complementàries **per als quinze dies següents**, i d'aquesta autorització depèn que la
 * planificació pugui fer servir el 28%.
 *
 * ── PER QUÈ PER QUINZENA I NO UN TIC PERMANENT ──────────────────────────────────────
 *   L'**art. 12.5 de l'Estatut dels Treballadors** exigeix que el pacte d'hores
 *   complementàries sigui **escrit i voluntari**, i que la seva realització concreta es
 *   preavisi. Un tic permanent a la fitxa, posat per administració, no acredita res: no diu
 *   qui va consentir, ni quan, ni a què.
 *
 *   Amb acceptació quinzenal, cada període té la seva pròpia declaració de voluntat, amb data
 *   i límit d'hores. Si algú discuteix les hores que se li van programar, el que s'exhibeix és
 *   l'acceptació d'aquella quinzena, no un booleà.
 *
 * ── EL QUE ES GUARDA, I PER QUÈ CADA COSA ───────────────────────────────────────────
 *   · `period_start` / `period_end` — la quinzena concreta. Fora d'ella el pacte no val.
 *   · `max_hours` — el sostre que la persona accepta. Acceptar «hores complementàries» sense
 *     dir quantes no és un consentiment informat.
 *   · `text_version` — QUINA redacció va acceptar. Si demà es canvia el text, les acceptacions
 *     anteriors continuen sent interpretables.
 *   · `accepted_ip` / `accepted_agent` — la traça mínima que converteix un clic en una prova.
 *   · `revoked_at` — la revocació **no esborra l'acceptació**: la tanca. Un pacte esborrat no
 *     acredita que va existir, i el que s'ha de poder provar és tot el recorregut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complementary_pacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('max_hours', 6, 2)->default(0);

            $table->timestamp('accepted_at');
            $table->string('accepted_ip', 45)->nullable();
            $table->string('accepted_agent', 255)->nullable();
            $table->string('text_version', 20)->default('1.0');

            /* La revocació tanca el pacte cap endavant; el que ja s'hagi prestat no es toca. */
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();

            $table->timestamps();

            /* Una acceptació viva per persona i quinzena: dues acceptacions del mateix període
               farien que no se sabés quina és la bona. */
            $table->unique(['user_id', 'period_start'], 'u_user_period');
            $table->index(['period_start', 'period_end'], 'k_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complementary_pacts');
    }
};
