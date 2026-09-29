<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_locations', function (Blueprint $blueprint) {
            // Add columns missing in some environments
            if (!Schema::hasColumn('work_locations', 'active')) {
                $blueprint->boolean('active')->default(true)->after('radius');
            }
            if (!Schema::hasColumn('work_locations', 'deleted_at')) {
                $blueprint->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_locations', function (Blueprint $blueprint) {
            $blueprint->dropColumn('active');
            $blueprint->dropSoftDeletes();
        });
    }
};
