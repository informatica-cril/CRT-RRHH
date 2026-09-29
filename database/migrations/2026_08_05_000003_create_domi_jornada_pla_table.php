<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pla anual teòric de jornada que empeny la Domiciliària: per cada treballador i
 * dia laborable, la jornada que li toca segons la bossa anual (1726 h prorratejades)
 * i l'hora de sortida teòrica. El treballador ho veu al seu calendari laboral:
 * quins dies surt abans i quins fa jornada plena. Es refresca cada nit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domi_jornada_pla', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->date('date');
            $table->unsignedInteger('jornada_min');
            $table->time('sortida')->nullable();
            $table->time('finestra_fi')->nullable();
            $table->unsignedInteger('surt_abans_min')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domi_jornada_pla');
    }
};
