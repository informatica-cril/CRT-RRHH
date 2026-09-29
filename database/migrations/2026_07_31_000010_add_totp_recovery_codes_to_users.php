<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Còdis de recuperació del segon factor (ENS ALTA).
 *
 * Sense ells, perdre el mòbil = perdre el compte fins que un admin el reseteja a mà: és el
 * motiu habitual pel qual un 2FA acaba desactivat "temporalment" i no es torna a activar mai.
 *
 * `totp_recovery_codes` desa un JSON amb el HASH (sha256) de cada codi, i el JSON sencer va
 * xifrat amb Crypt (APP_KEY) — mateixa protecció que el secret TOTP. El codi en clar només
 * existeix al moment de generar-lo: es mostra un cop a l'usuari i no es pot recuperar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_recovery_codes')->nullable()->after('totp_confirmed');       // xifrat
            $table->timestamp('totp_recovery_generated_at')->nullable()->after('totp_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_recovery_codes', 'totp_recovery_generated_at']);
        });
    }
};
