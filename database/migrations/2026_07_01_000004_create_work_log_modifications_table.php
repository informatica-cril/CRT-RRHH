<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_log_modifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_log_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('action', [
                'created', 'segmented', 'approved', 'rejected', 'modified',
                'break_started', 'break_completed', 'break_skipped'
            ]);
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('work_log_id')->references('id')->on('work_logs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('work_log_id', 'wlm_work_log_id_idx');
            $table->index('user_id', 'wlm_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_log_modifications');
    }
};
