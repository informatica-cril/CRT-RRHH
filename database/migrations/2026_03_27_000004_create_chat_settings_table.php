<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_settings', function (Blueprint $table) {
            $table->id();
            $table->json('forensic_keywords');
            $table->text('chat_policy_text');
            $table->boolean('require_policy_acceptance')->default(true);
            $table->timestamps();
        });

        Schema::create('chat_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('chat_messages')->onDelete('cascade');
            $table->string('keyword');
            $table->timestamps();
        });

        Schema::create('chat_policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->unique(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_policy_acceptances');
        Schema::dropIfExists('chat_alerts');
        Schema::dropIfExists('chat_settings');
    }
};
