<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segell d'integritat de tota l'app (FNMT / RFC 3161).
 *
 *  · integrity_events: llibre APPEND-ONLY d'esdeveniments hashejats i ENCADENATS (cada hash inclou
 *    l'anterior → qualsevol alteració trenca la cadena). Cobreix acusaments, firmes, modificacions,
 *    auditoria… (fonts configurables a config/integrity.php).
 *  · daily_seals: tancament diari. Calcula l'arrel del dia, l'encadena amb el segell anterior i hi
 *    incorpora un SEGELL DE TEMPS de la FNMT (token RFC 3161) → inalterabilitat oposable a tercers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_seals', function (Blueprint $table) {
            $table->id();
            $table->date('seal_date')->unique();
            $table->unsignedInteger('events_count')->default(0);
            $table->unsignedBigInteger('first_event_id')->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->char('prev_seal_hash', 64)->nullable();     // cadena entre dies
            $table->char('root_hash', 64)->nullable();          // arrel del dia (cap de cadena)
            $table->string('tsa_provider', 40)->nullable();
            $table->enum('tsa_status', ['pendent', 'segellat', 'error'])->default('pendent');
            $table->longText('tsa_token')->nullable();          // TimeStampToken DER en base64
            $table->timestamp('tsa_time')->nullable();          // genTime del token
            $table->string('tsa_error', 255)->nullable();
            $table->timestamp('sealed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integrity_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at')->nullable();
            $table->string('source', 48);                       // taula d'origen
            $table->unsignedBigInteger('source_id')->nullable();
            $table->char('payload_hash', 64);                   // sha256 del contingut canònic
            $table->char('prev_hash', 64)->nullable();
            $table->char('hash', 64);                           // sha256(prev|source|source_id|occurred_at|payload)
            $table->foreignId('seal_id')->nullable()->constrained('daily_seals')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['source', 'source_id']);            // cada fila d'origen s'incorpora un cop
            $table->index('seal_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_events');
        Schema::dropIfExists('daily_seals');
    }
};
