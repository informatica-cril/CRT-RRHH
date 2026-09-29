<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_log_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_log_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->enum('type', [
                'no_clock_in', 'no_clock_out', 'break_required',
                'segment_rejected', 'out_of_zone'
            ]);
            $table->text('message')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('dismissed_at')->nullable();
            $table->timestamps();

            $table->foreign('work_log_id')->references('id')->on('work_logs')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index('user_id', 'wla_user_id_idx');
            $table->index('scheduled_at', 'wla_scheduled_at_idx');
            $table->index('sent_at', 'wla_sent_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_log_alerts');
    }
};
