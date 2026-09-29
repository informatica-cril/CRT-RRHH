<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Declaració MENSUAL de franges d'hores complementàries (encàrrec de Direcció, 04-08-2026).
 *
 * El pacte (`complementary_pacts`) acredita el CONSENTIMENT i el sostre d'hores; aquesta
 * taula acredita el CALENDARI concret: quines franges ofereix la persona per al mes natural
 * següent. Les franges han de ser ADJACENTS a la jornada contractada (comencen quan la
 * jornada acaba o acaben quan comença): la validació és al controlador.
 *
 * ── PER QUÈ LA CONFIRMACIÓ ÉS IRREVOCABLE UNILATERALMENT ───────────────────────────
 *   Sobre aquestes franges, domi agenda pacients i els avisa. Retirar-les després no és
 *   neutre: desprograma persones. Per això `withdrawal_requested` no retira res: obre un
 *   flux que resol coordinació (a domi), i la resolució torna aquí via el compte de servei.
 *   El registre no s'esborra mai: `withdrawn` tanca, no fa desaparèixer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complementary_slot_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->date('month');                     // primer dia del mes natural declarat
            $table->json('slots');                     // [{dia:1-5, inici:"HH:MM", fi:"HH:MM"}]
            $table->decimal('hours', 5, 1)->default(0); // hores setmanals ofertes

            $table->string('status', 30)->default('confirmed'); // confirmed | withdrawal_requested | withdrawn
            $table->timestamp('confirmed_at');
            $table->string('confirmed_ip', 45)->nullable();

            $table->string('withdrawal_reason', 300)->nullable();
            $table->timestamp('withdrawal_requested_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_note', 255)->nullable();

            $table->timestamps();
            $table->unique(['user_id', 'month'], 'u_user_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complementary_slot_declarations');
    }
};
