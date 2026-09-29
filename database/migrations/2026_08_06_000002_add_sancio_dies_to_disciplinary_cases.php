<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->unsignedSmallInteger('resolucio_dies')->nullable()->after('resolucio_tipus');
            $table->timestamp('rlt_informat_ts')->nullable()->after('resolucio_motivacio');
        });
    }

    public function down(): void
    {
        Schema::table('disciplinary_cases', function (Blueprint $table) {
            $table->dropColumn(['resolucio_dies', 'rlt_informat_ts']);
        });
    }
};
