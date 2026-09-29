<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_log_segments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('work_log_id');
            $table->integer('segment_number')->default(1);
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();
            $table->boolean('in_zone')->default(true);
            $table->boolean('in_schedule')->default(true);
            $table->integer('duration_minutes')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'modified'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('authorization_code_id')->nullable();
            $table->unsignedBigInteger('modified_by')->nullable();
            $table->timestamp('modified_at')->nullable();
            $table->timestamps();

            $table->foreign('work_log_id')->references('id')->on('work_logs')->onDelete('cascade');
            $table->foreign('modified_by')->references('id')->on('users')->onDelete('set null');

            $table->index('work_log_id', 'wls_work_log_id_idx');
            $table->index('status', 'wls_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_log_segments');
    }
};
