<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La política de segon factor, decidida des de l'aplicació i no des del servidor.
 *
 * Direcció (01-08-2026): «vull decidir-ho jo». Fins ara vivia a `config/security.php`, que
 * llegeix el .env: canviar-la volia dir demanar-ho a informàtica, esperar un desplegament i
 * fiar-se que el valor ha quedat com toca. O sigui que la decisió era de Direcció i el
 * comandament el tenia una altra persona.
 *
 * ── TRES MODES, i no n'hi ha d'haver més ───────────────────────────────────────────
 *   'off'    · ningú està obligat. Qui vulgui pot enrolar-se pel seu compte (fase de
 *              registre). És on estem avui.
 *   'roles'  · obligatori NOMÉS per als rols de la llista. Serveix per començar per
 *              administració i RRHH, que són els comptes amb més abast, sense tocar els 75
 *              professionals de carrer el mateix dia.
 *   'all'    · obligatori per a tota la plantilla.
 *
 * ── EL .ENV SEGUEIX SENT LA XARXA ──────────────────────────────────────────────────
 * Si la taula no hi és —desplegament a mig fer, base restaurada d'una còpia antiga— es cau
 * al valor de config/security.php i tot es comporta com abans. Mai al revés: una taula que
 * falta no pot obrir de bat a bat una porta que estava tancada.
 *
 * ── PER QUÈ ES GUARDA QUI HO CANVIA ────────────────────────────────────────────────
 * És una mesura de l'ENS de categoria ALTA: qui afluixa el segon factor de tota una
 * plantilla ha de constar. Sense això, «algú ho va desactivar» no té resposta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('second_factor_settings', function (Blueprint $t) {
            $t->id();
            $t->string('mode', 10)->default('off');       // off | roles | all
            $t->json('roles')->nullable();                 // quins rols, si mode = roles
            $t->string('changed_by')->nullable();          // correu de qui ho ha decidit
            $t->timestamp('changed_at')->nullable();
            $t->timestamps();
        });

        /* Una sola fila, i neix en 'off': engegar l'obligatorietat amb una migració seria
           deixar gent fora de l'aplicació sense que ningú ho hagi decidit. */
        DB::table('second_factor_settings')->insert([
            'mode' => 'off', 'roles' => json_encode([]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('second_factor_settings');
    }
};
