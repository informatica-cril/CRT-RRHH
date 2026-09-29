<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->boolean('break_start_location_match')->nullable()->after('break_start_time');
            $table->boolean('break_end_location_match')->nullable()->after('break_end_time');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn(['break_start_location_match', 'break_end_location_match']);
        });
    }
};
