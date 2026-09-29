<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distingeix hores complementàries (pactades, parcials, factor 1,00) d'hores
 * extraordinàries (jornada completa, art. 20.1 conveni XII: 80 h/any a 1,25×).
 * El codi d'autorització és el compromís de pagament de l'empresa; el tipus i el
 * factor permeten portar-ne dos comptadors separats per treballador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authorization_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('authorization_codes', 'type')) {
                $table->enum('type', ['complementaria', 'extraordinaria'])
                      ->default('complementaria')->after('authorized_hours');
            }
            if (! Schema::hasColumn('authorization_codes', 'factor')) {
                $table->decimal('factor', 3, 2)->default(1.00)->after('type');
            }
            // Origen d'una sol·licitud forçada des de domi (traça del flux de sobrecàrrega).
            if (! Schema::hasColumn('authorization_codes', 'domi_origen')) {
                $table->string('domi_origen', 120)->nullable()->after('factor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('authorization_codes', function (Blueprint $table) {
            foreach (['type', 'factor', 'domi_origen'] as $c) {
                if (Schema::hasColumn('authorization_codes', $c)) $table->dropColumn($c);
            }
        });
    }
};
