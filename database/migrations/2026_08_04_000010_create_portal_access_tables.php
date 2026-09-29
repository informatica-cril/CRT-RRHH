<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal d'accés únic (CRT Accés).
 *
 * `portal_app_grants` — a quines apps pot entrar cada treballador. La targeta
 * només surt al llançador si hi ha grant; treure el grant tanca la porta.
 *
 * `portal_sso_tokens` — bitllets d'un sol ús per saltar del portal a una app.
 * Es guarda NOMÉS el hash SHA-256 del token (mai el token en clar), amb
 * caducitat curta i marca d'ús: un token robat de la base no serveix, un
 * token reutilitzat tampoc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_app_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app', 30);
            $table->string('granted_by', 100)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'app']);
        });

        Schema::create('portal_sso_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('app', 30);
            $table->char('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->timestamps();
            $table->index(['app', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_sso_tokens');
        Schema::dropIfExists('portal_app_grants');
    }
};
