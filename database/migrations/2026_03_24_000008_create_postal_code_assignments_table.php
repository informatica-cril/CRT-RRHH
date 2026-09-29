<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_code_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->json('postal_codes'); // Array of CP strings e.g. ["08001","08002"]
            $table->date('valid_from');
            $table->date('valid_to')->nullable(); // null = currently active
            $table->text('notes')->nullablae();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index(['user_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_code_assignments');
    }
};
