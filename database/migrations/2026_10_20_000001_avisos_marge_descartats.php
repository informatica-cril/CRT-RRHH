<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('avisos_marge_descartats')) {
            Schema::create('avisos_marge_descartats', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->date('inici_ratxa');
                $table->foreignId('descartat_per')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['user_id', 'inici_ratxa']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_marge_descartats');
    }
};
