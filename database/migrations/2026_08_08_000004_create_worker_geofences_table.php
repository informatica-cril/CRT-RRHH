<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_geofences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('cps')->nullable();
            $table->json('poligons')->nullable();
            $table->string('font')->default('domi');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_geofences');
    }
};
