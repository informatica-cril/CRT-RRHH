<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('recoverable')->default(false);
            $table->boolean('remunerated')->default(true);
            $table->integer('max_days')->nullable();
            $table->integer('max_per_year')->nullable();
            $table->integer('max_lifetime')->nullable();
            $table->boolean('requires_justification')->default(false);
            $table->integer('advance_notice_hours')->default(0);
            $table->boolean('extends_with_travel')->default(false);
            $table->integer('extra_days_travel')->nullable();
            $table->string('category')->default('personal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_types');
    }
};
