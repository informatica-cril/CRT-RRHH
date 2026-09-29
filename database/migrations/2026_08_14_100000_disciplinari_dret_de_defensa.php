<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dret de defensa i traçabilitat del procediment disciplinari.
 *
 *  · disciplinary_cases: les al·legacions passen a ser un ACTE DEL TREBALLADOR (text, autor,
 *    adjunt) i la renúncia al termini també, amb el seu id i la seva data. Fins ara alegacions_ts
 *    l'omplia la part acusadora, cosa que buida de contingut l'audiència (ET 55.1, conveni 55.K.6).
 *  · disciplinary_documents: hash del text del motor i hash del text SIGNAT. Sense ells no es podia
 *    saber si el que va rebre el treballador era l'escrit generat o un text substituït a mà.
 *  · disciplinary_elements: origen de la prova. 'pont' només l'escriu el servidor després de
 *    contrastar el fet amb l'app domiciliària; el que es tecleja queda 'manual' i el plec ho diu.
 *
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('disciplinary_cases', 'alegacions_text')) {
                $table->longText('alegacions_text')->nullable()->after('alegacions_ts');
            }
            if (! Schema::hasColumn('disciplinary_cases', 'alegacions_per')) {
                $table->unsignedBigInteger('alegacions_per')->nullable()->after('alegacions_text');
            }
            if (! Schema::hasColumn('disciplinary_cases', 'alegacions_adjunt_nom')) {
                $table->string('alegacions_adjunt_nom', 160)->nullable()->after('alegacions_per');
            }
            if (! Schema::hasColumn('disciplinary_cases', 'alegacions_adjunt')) {
                $table->longText('alegacions_adjunt')->nullable()->after('alegacions_adjunt_nom');
            }
            if (! Schema::hasColumn('disciplinary_cases', 'renuncia_termini_ts')) {
                $table->timestamp('renuncia_termini_ts')->nullable()->after('alegacions_adjunt');
            }
            if (! Schema::hasColumn('disciplinary_cases', 'renuncia_termini_per')) {
                $table->unsignedBigInteger('renuncia_termini_per')->nullable()->after('renuncia_termini_ts');
            }
        });

        Schema::table('disciplinary_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('disciplinary_documents', 'motor_hash')) {
                $table->string('motor_hash', 64)->nullable()->after('contingut');
            }
            if (! Schema::hasColumn('disciplinary_documents', 'contingut_hash')) {
                $table->string('contingut_hash', 64)->nullable()->after('motor_hash');
            }
            if (! Schema::hasColumn('disciplinary_documents', 'apartat_del_motor')) {
                $table->boolean('apartat_del_motor')->default(false)->after('contingut_hash');
            }
        });

        Schema::table('disciplinary_elements', function (Blueprint $table) {
            if (! Schema::hasColumn('disciplinary_elements', 'origen')) {
                // NULL a propòsit per a les files antigues: d'aquelles no es pot acreditar l'origen,
                // i el plec ho ha de dir així en comptes d'afirmar el que no consta.
                $table->enum('origen', ['manual', 'pont'])->nullable()->after('font');
            }
        });

        // Les úniques files antigues amb origen ACREDITAT: les que va escriure el servidor en
        // rebutjar un tram de fitxatge (WorkLogSegmentController), mai un formulari.
        DB::table('disciplinary_elements')
            ->whereNull('origen')
            ->where('font', 'like', 'marcatge:%')
            ->update(['origen' => 'pont']);
    }

    public function down(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->dropColumn(['alegacions_text', 'alegacions_per', 'alegacions_adjunt_nom',
                'alegacions_adjunt', 'renuncia_termini_ts', 'renuncia_termini_per']);
        });
        Schema::table('disciplinary_documents', function (Blueprint $table) {
            $table->dropColumn(['motor_hash', 'contingut_hash', 'apartat_del_motor']);
        });
        Schema::table('disciplinary_elements', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
