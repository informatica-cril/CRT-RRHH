<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->integer('complementary_minutes')->nullable()->default(0)
                ->after('gps_gap_minutes')
                ->comment('Horas complementarias en minutos (sustituye conceptualmente a extra_hours_*)');
            $table->dateTime('break_start_time')->nullable()->after('complementary_minutes');
            $table->dateTime('break_end_time')->nullable()->after('break_start_time');
            $table->boolean('break_required')->nullable()->default(false)->after('break_end_time');
            $table->enum('break_status', ['pending', 'active', 'completed', 'skipped'])->nullable()->default('pending')->after('break_required');
            $table->boolean('segmented')->nullable()->default(false)->after('break_status')
                ->comment('Indica si el fichaje ha sido segmentado en tramos');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn([
                'complementary_minutes', 'break_start_time', 'break_end_time',
                'break_required', 'break_status', 'segmented'
            ]);
        });
    }
};
