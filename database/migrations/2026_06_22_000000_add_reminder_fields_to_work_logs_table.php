<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->timestamp('reminder1_sent_at')->nullable()->after('auto_closed');
            $table->timestamp('reminder2_sent_at')->nullable()->after('reminder1_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn(['reminder1_sent_at', 'reminder2_sent_at']);
        });
    }
};
