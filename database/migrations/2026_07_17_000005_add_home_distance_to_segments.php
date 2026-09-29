<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidència per a la revisió humana (protocol d'audiència §6.2 EIPD):
     * distància al domicili i radi efectiu USAT en el hito (dinàmic segons precisió
     * GPS: mínim tècnic). Escalars sense cap dada de pacient.
     */
    public function up(): void
    {
        Schema::table('work_log_segments', function (Blueprint $table) {
            $table->unsignedInteger('home_distance_m')->nullable()->after('home_verification');
            $table->unsignedInteger('home_radius_m')->nullable()->after('home_distance_m');
        });
    }

    public function down(): void
    {
        Schema::table('work_log_segments', function (Blueprint $table) {
            $table->dropColumn(['home_distance_m', 'home_radius_m']);
        });
    }
};
