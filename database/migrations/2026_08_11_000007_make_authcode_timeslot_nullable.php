<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Les hores extraordinàries autoritzades per un PERÍODE (art. 20.1) no tenen franja
 * horària concreta, a diferència de les complementàries pactades. La franja passa a
 * ser opcional. Aditiu: els codis amb franja segueixen igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MODIFY és sintaxi de MySQL; a sqlite (tests) es fa amb el constructor d'esquema.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE authorization_codes MODIFY time_slot_start TIME NULL');
            DB::statement('ALTER TABLE authorization_codes MODIFY time_slot_end TIME NULL');
        } else {
            Schema::table('authorization_codes', function (Blueprint $table) {
                $table->time('time_slot_start')->nullable()->change();
                $table->time('time_slot_end')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE authorization_codes MODIFY time_slot_start TIME NOT NULL");
            DB::statement("ALTER TABLE authorization_codes MODIFY time_slot_end TIME NOT NULL");
        } else {
            Schema::table('authorization_codes', function (Blueprint $table) {
                $table->time('time_slot_start')->nullable(false)->change();
                $table->time('time_slot_end')->nullable(false)->change();
            });
        }
    }
};
