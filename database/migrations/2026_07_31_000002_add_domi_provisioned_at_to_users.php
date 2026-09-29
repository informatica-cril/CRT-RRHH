<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `domi_provisioned_at` — quan domi va CONFIRMAR que el compte existeix.
 *
 * ── EL PROBLEMA QUE RESOL (31-07-2026) ──────────────────────────────────────────────
 * Fins ara «aquesta persona ja té compte a domi» es deduïa de `domi_username IS NOT NULL`.
 * I això funciona mentre l'única manera d'omplir aquell camp sigui que domi respongui.
 *
 * Però l'Excel d'alta massiva porta una columna `usuari_domi`, i l'importador la desa
 * directament a `domi_username`. O sigui que després d'una càrrega massiva TOTHOM sembla
 * aprovisionat sense que domi hagi creat res, i `domi:provisiona-pendents` no els veu:
 *
 *     41 domiciliaris actius · la comanda només en veia 31 com a pendents
 *
 * Amb la plantilla sencera donada d'alta de nou —que és el que passarà en posar les apps a
 * producció— això vol dir 80 fitxes a RRHH i cap compte a domi, sense cap avís.
 *
 * A partir d'ara són dues coses diferents, que és el que sempre havien de ser:
 *     domi_username        → el nom d'usuari que VOLEM que tingui (el pot dir l'Excel)
 *     domi_provisioned_at  → quan domi ha dit que SÍ, que el compte hi és
 *
 * ── DADES QUE JA HI HA ──────────────────────────────────────────────────────────────
 * No es marca ningú com a aprovisionat. Els que ja tenen `domi_username` es queden amb
 * `domi_provisioned_at` a NULL i sortiran com a pendents la primera vegada. Marcar-los
 * seria endevinar: no sabem si el compte de domi existeix de debò, i el cost d'equivocar-se
 * cap a aquest costat és que ningú se n'assabenti. Reaprovisionar algú que ja hi és, en
 * canvi, no fa mal: domi ho tracta com a idempotent i torna el compte que ja tenia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('domi_provisioned_at')->nullable()->after('domi_privilege');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('domi_provisioned_at');
        });
    }
};
