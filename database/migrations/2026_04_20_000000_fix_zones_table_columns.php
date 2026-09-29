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
        Schema::table('zones', function (Blueprint $table) {
            // Add missing columns if they don't exist
            if (!Schema::hasColumn('zones', 'province')) {
                $table->string('province')->nullable()->after('type');
            }
            if (!Schema::hasColumn('zones', 'postal_codes')) {
                $table->json('postal_codes')->nullable()->after('province');
            }
            if (!Schema::hasColumn('zones', 'municipalities')) {
                $table->json('municipalities')->nullable()->after('postal_codes');
            }
            if (!Schema::hasColumn('zones', 'active')) {
                $table->boolean('active')->default(true)->after('municipalities');
            }
            if (!Schema::hasColumn('zones', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn(['province', 'postal_codes', 'municipalities', 'active', 'deleted_at']);
        });
    }
};
