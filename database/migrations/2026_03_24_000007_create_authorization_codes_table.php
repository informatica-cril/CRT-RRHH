<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('concept');
            $table->decimal('authorized_hours', 5, 2);
            $table->time('time_slot_start');
            $table->time('time_slot_end');
            $table->unsignedBigInteger('generated_by');
            $table->unsignedBigInteger('user_id')->nullable(); // null = any worker
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->boolean('used')->default(false);
            $table->dateTime('used_at')->nullable();
            $table->boolean('revoked')->default(false);
            $table->dateTime('revoked_at')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamps();

            $table->foreign('generated_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authorization_codes');
    }
};
