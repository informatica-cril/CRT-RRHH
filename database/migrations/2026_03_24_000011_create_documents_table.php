<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable(); // Text content
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable(); // Path to uploaded PDF
            $table->string('file_mime')->nullable();
            $table->string('category')->default('general');
            $table->boolean('requires_signature')->default(false);
            $table->boolean('is_urgent')->default(false);
            $table->json('target_users')->nullable(); // null or "all" or array of user IDs
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
