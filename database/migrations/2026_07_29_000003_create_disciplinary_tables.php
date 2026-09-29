<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Procediment disciplinari (a RRHH). El motor ACREDITA i PROPOSA; una PERSONA qualifica i signa
 * (RGPD art. 22). Només dades imputables. Autònom fora de la via disciplinària laboral. Prescripció
 * ET 60.2. Historial append-only (cadena de custòdia). L'evidència objectiva es consulta a domi (F1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catàleg de faltes (parametrització XII Conveni + ET)
        Schema::create('disciplinary_fault_types', function (Blueprint $table) {
            $table->id();
            $table->string('clau', 40)->unique();
            $table->string('descripcio');
            $table->enum('grau_base', ['lleu', 'menys_greu', 'greu', 'molt_greu']);
            $table->string('base_conveni', 40)->nullable();
            $table->string('base_et', 40)->nullable();
            $table->unsignedSmallInteger('prescripcio_dies');
            $table->boolean('reincidencia_puja')->default(false);
            $table->unsignedSmallInteger('llindar_reincidencia')->nullable();
            $table->boolean('requereix_apercebiment')->default(false);
            $table->boolean('requereix_afectacio_servei')->default(false);
            $table->timestamps();
        });

        // Elements objectius (acumulador per a reincidència)
        Schema::create('disciplinary_elements', function (Blueprint $table) {
            $table->id();
            $table->string('professional', 64);                 // identificador domi (fisio)
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipus_falta', 40)->nullable();
            $table->date('data_fet');
            $table->date('data_coneixement');
            $table->string('font', 80)->nullable();
            $table->string('valor', 120)->nullable();
            $table->string('descripcio')->nullable();
            $table->enum('imputable', ['si', 'no', 'condicional'])->nullable();
            $table->enum('estat', ['nou', 'valorat', 'inclos_en_cas', 'descartat'])->default('nou');
            $table->unsignedBigInteger('case_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['professional', 'tipus_falta']);
        });

        // Cas disciplinari (procediment amb garanties)
        Schema::create('disciplinary_cases', function (Blueprint $table) {
            $table->id();
            $table->string('professional', 64);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('vincle', ['laboral', 'autonom'])->default('laboral');
            $table->string('tipus_falta', 40)->nullable();
            $table->enum('gravetat', ['lleu', 'menys_greu', 'greu', 'molt_greu'])->nullable();
            $table->enum('estat', ['esborrany', 'instruccio', 'comunicat', 'alegacions', 'resolt', 'arxivat', 'executat'])->default('esborrany');
            $table->date('data_coneixement')->nullable();
            $table->date('data_prescripcio')->nullable();
            $table->boolean('reincidencia_acreditada')->default(false);
            $table->boolean('afectacio_servei_acreditada')->default(false);
            $table->string('afectacio_confirmada_per', 64)->nullable();
            $table->boolean('apercebiment_previ')->default(false);
            $table->boolean('te_evidencia_licita')->default(false);
            $table->boolean('es_representant')->default(false);
            $table->boolean('expedient_contradictori')->default(false);
            $table->timestamp('comunicat_ts')->nullable();
            $table->date('termini_alegacions')->nullable();
            $table->timestamp('alegacions_ts')->nullable();
            $table->timestamp('audiencia_rlt_ts')->nullable();
            $table->string('sancio_proposada', 120)->nullable();
            $table->enum('resolucio_tipus', ['amonestacio', 'suspensio', 'trasllat', 'inhabilitacio', 'acomiadament', 'arxiu'])->nullable();
            $table->text('resolucio_motivacio')->nullable();
            $table->string('resolt_per', 64)->nullable();
            $table->timestamp('resolt_ts')->nullable();
            $table->string('obert_per', 64)->nullable();
            $table->timestamp('obert_ts')->nullable();
            $table->timestamps();
            $table->index(['professional', 'estat']);
            $table->index('data_prescripcio');
        });

        // Historial APPEND-ONLY (cadena de custòdia)
        Schema::create('disciplinary_case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('disciplinary_cases')->cascadeOnDelete();
            $table->string('estat_de', 20)->nullable();
            $table->string('estat_a', 20)->nullable();
            $table->string('actor', 64)->nullable();
            $table->string('nota', 500)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('case_id');
        });

        // Documents (esborranys; signatura HUMANA)
        Schema::create('disciplinary_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->string('professional', 64)->nullable();
            $table->enum('tipus', ['apercebiment', 'amonestacio', 'plec_carrecs', 'resolucio', 'carta_acomiadament']);
            $table->longText('contingut')->nullable();
            $table->enum('estat', ['esborrany', 'signat', 'notificat'])->default('esborrany');
            $table->string('generat_per', 64)->nullable();
            $table->string('signat_per', 64)->nullable();
            $table->timestamp('signat_ts')->nullable();
            $table->timestamps();
            $table->index('case_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinary_documents');
        Schema::dropIfExists('disciplinary_case_events');
        Schema::dropIfExists('disciplinary_cases');
        Schema::dropIfExists('disciplinary_elements');
        Schema::dropIfExists('disciplinary_fault_types');
    }
};
