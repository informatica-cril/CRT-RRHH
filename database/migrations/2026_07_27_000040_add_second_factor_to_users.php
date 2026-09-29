<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segon factor per usuari (ENS ALTA), igual que a domi (docs/PROPOSTA-CREDENCIALS-ENS §4).
 * 'dispositiu' (llave d'empresa) per defecte | 'totp' (Google Authenticator) per a qui
 * l'admin autoritzi. El secret TOTP es desa XIFRAT (Crypt de Laravel, APP_KEY).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('second_factor', ['dispositiu', 'totp'])->default('dispositiu')->after('must_change_password');
            $table->text('totp_secret')->nullable()->after('second_factor');          // xifrat
            $table->boolean('totp_confirmed')->default(false)->after('totp_secret');
            $table->string('second_factor_by', 60)->nullable()->after('totp_confirmed');
            $table->timestamp('second_factor_at')->nullable()->after('second_factor_by');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['second_factor', 'totp_secret', 'totp_confirmed', 'second_factor_by', 'second_factor_at']);
        });
    }
};
