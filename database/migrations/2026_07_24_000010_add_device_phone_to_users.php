<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Número de telèfon de la TABLET corporativa del treballador (SIM d'empresa).
     * És propietat de l'empresa i va lligat a la tablet, no a la persona. domi el consumeix
     * per resoldre l'enllaç wa.me del fisio actual (trucada/xat natiu del pacient).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'device_phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('device_phone', 20)->nullable()->after('dni');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('device_phone');
        });
    }
};
