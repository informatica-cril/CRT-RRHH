<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('viewed_at')->nullable();
            $table->dateTime('signed_at')->nullable();
            $table->string('document_hash')->nullable();
            $table->string('signature_hash')->nullable();
            $table->string('timestamp_source')->nullable();
            $table->text('timestamp_token')->nullable();
            $table->text('signing_payload')->nullable();
            $table->string('legal_basis')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['document_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatures');
    }
};
