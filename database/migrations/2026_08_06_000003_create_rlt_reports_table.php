<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servei_parametres', function (Blueprint $table) {
            $table->id();
            $table->string('servei', 20);
            $table->string('clau', 60);
            $table->json('valor');
            $table->timestamp('rebut_ts');
            $table->timestamps();
            $table->unique(['servei', 'clau']);
        });

        Schema::create('rlt_reports', function (Blueprint $table) {
            $table->id();
            $table->string('tipus', 40);
            $table->date('periode_desde');
            $table->date('periode_fins');
            $table->json('serveis')->nullable();
            $table->json('fets')->nullable();
            $table->longText('text')->nullable();
            $table->enum('estat', ['esborrany', 'signat', 'lliurat'])->default('esborrany');
            $table->unsignedBigInteger('generat_per')->nullable();
            $table->timestamp('generat_ts')->nullable();
            $table->unsignedBigInteger('revisat_per')->nullable();
            $table->timestamp('revisat_ts')->nullable();
            $table->unsignedBigInteger('signat_per')->nullable();
            $table->timestamp('signat_ts')->nullable();
            $table->string('signatura_hash', 128)->nullable();
            $table->string('text_hash', 128)->nullable();
            $table->timestamp('lliurat_ts')->nullable();
            $table->string('lliurat_a', 160)->nullable();
            $table->string('nota_lliurament', 500)->nullable();
            $table->unsignedBigInteger('document_id')->nullable();
            $table->timestamps();
            $table->index(['tipus', 'periode_fins']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rlt_reports');
        Schema::dropIfExists('servei_parametres');
    }
};
