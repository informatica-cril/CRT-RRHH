<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excedencia_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('min_months')->nullable();
            $table->integer('max_months')->nullable();
            $table->integer('requires_seniority_months')->default(0);
            $table->boolean('job_reserve')->default(false);
            $table->integer('job_reserve_months')->nullable();
            $table->boolean('seniority_counts')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excedencia_types');
    }
};
