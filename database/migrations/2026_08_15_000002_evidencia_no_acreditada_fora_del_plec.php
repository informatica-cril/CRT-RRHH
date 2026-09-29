<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * L'EVIDÈNCIA D'ORIGEN NO ACREDITAT DEIXA DE PODER ENTRAR SOLA A UN ESCRIT.
 *
 * Situació que es corregeix. Hi ha set elements de prova amb la font dins l'espai reservat al pont
 * («domi:retards», «domi:tecniques_no») i amb `origen` a NULL. Aquesta combinació avui és
 * impossible: l'únic camí que pot escriure una font «domi:…» és DisciplinaryController::bulkElements,
 * que contrasta el fet amb l'app domiciliària abans de copiar-lo i sempre hi deixa `origen='pont'`;
 * i el formulari manual té la font «domi:…» prohibida des de la mateixa validació. Són, doncs, files
 * anteriors a aquella guarda, de les quals no consta ni que vinguessin del pont ni qui les va posar
 * (sis tenen `created_by` a NULL).
 *
 * S'ha consultat el pont abans de decidir: per als tres professionals afectats i per a tot el
 * període que cobreixen, la recollida de domi respon correctament i torna ZERO fets a tots els
 * blocs. No és només que l'origen no es pugui acreditar; és que el sistema que hauria d'haver-los
 * originat no els té. Per això no s'hi estampa `origen='pont'`: seria fabricar la traçabilitat que
 * precisament falta.
 *
 * Tampoc s'esborren. Un element pot ser el que sosté un expedient ja comunicat (tres estan inclosos
 * al cas 2) i fer-lo desaparèixer alteraria un procediment viu i la cadena de custòdia. El que es fa
 * és treure'ls la aptitud per fonamentar un escrit: `apte_plec` a 0. A partir d'aquí no entren al
 * relat de fets, no compten per a la reincidència i no generen suggeriments d'obertura, fins que una
 * persona ho decideixi EXPRESSAMENT i deixi per escrit per què (apte_plec_motiu/_per/_ts).
 *
 * Idempotent: columnes guardades per hasColumn i marcatge acotat a la condició que el defineix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_elements', function (Blueprint $table) {
            if (! Schema::hasColumn('disciplinary_elements', 'apte_plec')) {
                // Per defecte 1: el que neix pels camins d'avui ja porta origen acreditat i no ha de
                // patir cap fricció nova. La marca és l'excepció, no la norma.
                $table->boolean('apte_plec')->default(true)->after('origen');
            }
            if (! Schema::hasColumn('disciplinary_elements', 'apte_plec_motiu')) {
                $table->string('apte_plec_motiu', 500)->nullable()->after('apte_plec');
            }
            if (! Schema::hasColumn('disciplinary_elements', 'apte_plec_per')) {
                $table->unsignedBigInteger('apte_plec_per')->nullable()->after('apte_plec_motiu');
            }
            if (! Schema::hasColumn('disciplinary_elements', 'apte_plec_ts')) {
                $table->timestamp('apte_plec_ts')->nullable()->after('apte_plec_per');
            }
        });

        // Font de l'espai del pont SENSE origen acreditat: fora del plec fins a decisió expressa.
        // No es toca cap fila que ja porti una decisió escrita (apte_plec_ts), per si la migració
        // es torna a executar després que algú n'hagi rehabilitat alguna.
        DB::table('disciplinary_elements')
            ->whereNull('origen')
            ->where('font', 'like', 'domi:%')
            ->whereNull('apte_plec_ts')
            ->update(['apte_plec' => false]);
    }

    public function down(): void
    {
        Schema::table('disciplinary_elements', function (Blueprint $table) {
            $table->dropColumn(['apte_plec', 'apte_plec_motiu', 'apte_plec_per', 'apte_plec_ts']);
        });
    }
};
