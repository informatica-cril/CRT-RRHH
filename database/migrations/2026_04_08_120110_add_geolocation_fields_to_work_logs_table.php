<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->boolean('location_match')->nullable()->after('end_location_lng');
            $table->boolean('start_location_match')->nullable()->after('location_match');
            $table->decimal('start_location_distance', 8, 2)->nullable()->after('start_location_match');
            $table->boolean('end_location_match')->nullable()->after('start_location_distance');
            $table->decimal('end_location_distance', 8, 2)->nullable()->after('end_location_match');
            $table->string('hour_status')->nullable()->after('end_location_distance');
            $table->decimal('hours_worked', 5, 2)->nullable()->after('hour_status');
            $table->decimal('hours_out_of_area', 5, 2)->nullable()->after('hours_worked');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn([
                'location_match', 'start_location_match', 'start_location_distance',
                'end_location_match', 'end_location_distance',
                'hour_status', 'hours_worked', 'hours_out_of_area'
            ]);
        });
    }
};