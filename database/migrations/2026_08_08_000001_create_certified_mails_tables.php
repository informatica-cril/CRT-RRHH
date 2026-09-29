<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensatek_settings', function (Blueprint $table) {
            $table->id();
            $table->string('usuari_api')->nullable();
            $table->text('api_token')->nullable();
            $table->string('remitent')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->string('last_test_status')->nullable();
            $table->timestamps();
        });

        Schema::create('certified_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sender_id')->constrained('users');
            $table->enum('tipus', ['acuse', 'expedient']);
            $table->string('expedient_ref')->nullable();
            $table->string('assumpte');
            $table->text('cos');
            $table->boolean('acceptacio')->default(false);
            $table->timestamp('programat_at')->nullable();
            $table->json('adjunts_json')->nullable();
            $table->enum('estat_global', ['esborrany', 'enviat', 'programat', 'cancellat', 'error'])->default('esborrany');
            $table->string('error_txt')->nullable();
            $table->timestamps();
        });

        Schema::create('certified_mail_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certified_mail_id')->constrained('certified_mails')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('email');
            $table->string('nom');
            $table->unsignedBigInteger('id_mensaje')->nullable();
            $table->integer('estat')->nullable();
            $table->string('estat_txt')->nullable();
            $table->timestamp('estat_at')->nullable();
            $table->timestamps();
            $table->index(['certified_mail_id']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certified_mail_recipients');
        Schema::dropIfExists('certified_mails');
        Schema::dropIfExists('mensatek_settings');
    }
};
