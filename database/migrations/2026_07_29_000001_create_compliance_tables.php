<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compliment / Governança de l'expedient del treballador — CONSTÀNCIA interna.
 *
 * Dues taules: els documents de governança (versionats i publicables) i l'acusament individual de
 * cada treballador (prova que la informació va ser PRÈVIA i acreditable — LOPDGDD art. 90). És el que
 * habilita lícitament el motor disciplinari: sense aquesta constància, el sistema no s'ha d'encendre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compliance_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('tipus', ['politica_algoritmica', 'info_art90', 'ropa', 'eipd', 'altre'])->default('altre');
            $table->string('titol');
            $table->string('versio', 20)->default('1.0');
            $table->longText('contingut');                    // markdown
            $table->string('base_legal')->nullable();
            $table->enum('estat', ['esborrany', 'publicat', 'arxivat'])->default('esborrany');
            $table->boolean('requereix_acus')->default(false); // acusament individual del treballador?
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tipus', 'estat']);
        });

        Schema::create('compliance_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compliance_document_id')->constrained('compliance_documents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('document_versio', 20)->nullable(); // versió acusada (snapshot immutable)
            $table->timestamp('acknowledged_at');
            $table->string('ip', 64)->nullable();
            $table->timestamps();
            $table->unique(['compliance_document_id', 'user_id'], 'cmpl_ack_doc_user_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_acknowledgements');
        Schema::dropIfExists('compliance_documents');
    }
};
