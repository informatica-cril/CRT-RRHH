<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('chat_status')->default('offline');
            $table->timestamp('last_chat_heartbeat')->nullable();
        });

        Schema::table('chat_alerts', function (Blueprint $table) {
            $table->boolean('reviewed')->default(false);
            $table->string('content_excerpt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['chat_status', 'last_chat_heartbeat']);
        });

        Schema::table('chat_alerts', function (Blueprint $table) {
            $table->dropColumn(['reviewed', 'content_excerpt']);
        });
    }
};
