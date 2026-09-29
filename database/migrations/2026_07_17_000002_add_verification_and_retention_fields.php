<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            // Mode de verificació: A = validat contra domicili (OSRM domi online),
            // B = validat només per zona (domi offline/no desplegat).
            $table->string('verification_mode', 1)->nullable()->after('hour_status');
            // Resultat de verificació de domicili (input opcional de domi):
            // verificat | fora_radi | no_disponible | pendent
            $table->string('home_verification', 20)->nullable()->after('verification_mode');
            // El fichatge cau fora del quadrant horari (bloqueig GPS / bandera)
            $table->boolean('out_of_schedule')->default(false)->after('home_verification');
            // Marca de purga de coordenades crues (minimització/retenció 48 mesos)
            $table->timestamp('coords_purged_at')->nullable()->after('out_of_schedule');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn(['verification_mode', 'home_verification', 'out_of_schedule', 'coords_purged_at']);
        });
    }
};
