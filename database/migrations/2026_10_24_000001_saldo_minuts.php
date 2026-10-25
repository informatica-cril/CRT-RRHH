<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saldo de minuts (RRHH, 24-10-2026): els minuts que es fitxen fins a una finestra (5 per defecte)
 * abans de l'entrada o després de la sortida, i els que es deixen de fer en entrar tard o sortir
 * d'hora, van a un saldo per persona amb un tope (±15). No substitueix el registre de jornada:
 * és un llibre a part. Additiu i desactivat per defecte.
 *   saldo_minuts_settings  interruptor, finestra, tope i data d'activació (una sola fila)
 *   saldo_minuts_apunts    una línia per fitxatge (tipus 'fitxatge') o per decisió de gestió (tipus 'ajust')
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('saldo_minuts_settings')) {
            Schema::create('saldo_minuts_settings', function (Blueprint $table) {
                $table->id();
                $table->boolean('enabled')->default(false);
                $table->unsignedSmallInteger('finestra_minuts')->default(5);
                $table->unsignedSmallInteger('tope_minuts')->default(15);
                $table->date('actiu_des')->nullable();               // el saldo només compta fitxatges des d'aquesta data (sense històric)
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('saldo_minuts_apunts')) {
            Schema::create('saldo_minuts_apunts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('work_log_id')->nullable()->unique();
                $table->string('tipus', 12)->default('fitxatge');
                $table->date('date')->index();
                $table->smallInteger('entrada_delta')->default(0);   // + abans de l'hora, − tard
                $table->smallInteger('sortida_delta')->default(0);   // + després de l'hora, − d'hora
                $table->smallInteger('delta_aplicat')->default(0);   // el que entra al saldo després del tope
                $table->smallInteger('excedent')->default(0);        // el que el tope deixa fora (va a revisió normal)
                $table->smallInteger('saldo_despres')->default(0);
                $table->decimal('extra_abans', 8, 4)->nullable();    // hores de més sense autoritzar abans de restar
                $table->decimal('extra_despres', 8, 4)->nullable();  // i després (per no restar dos cops)
                $table->string('nota', 500)->nullable();
                $table->unsignedBigInteger('decidit_per')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saldo_minuts_apunts');
        Schema::dropIfExists('saldo_minuts_settings');
    }
};
