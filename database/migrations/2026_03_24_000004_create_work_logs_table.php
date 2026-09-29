<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->dateTime('start_time');
            $table->dateTime('end_time')->nullable();
            $table->decimal('start_location_lat', 10, 7)->nullable();
            $table->decimal('start_location_lng', 10, 7)->nullable();
            $table->decimal('end_location_lat', 10, 7)->nullable();
            $table->decimal('end_location_lng', 10, 7)->nullable();
            $table->decimal('total_hours_worked', 5, 2)->default(0);
            $table->string('authorized_extra_code')->nullable();
            $table->decimal('extra_hours_authorized', 5, 2)->default(0);
            $table->decimal('extra_hours_unauthorized', 5, 2)->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');

            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
