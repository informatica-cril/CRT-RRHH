<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->integer('gps_gap_minutes')->nullable()->default(0)->after('hours_out_of_area')
                ->comment('Minutos sin GPS registrados durante la jornada');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('gps_gap_minutes');
        });
    }
};
