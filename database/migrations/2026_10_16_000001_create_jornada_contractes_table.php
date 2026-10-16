<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percentatge de jornada del CONTRACTE amb historial (RRHH, 16-10-2026).
 *
 * Fins ara el % es deduïa de l'horari (hores setmanals ÷ 37,5). RRHH treballa al revés: mana
 * la jornada màxima anual del conveni (1726 h × %), i cada ampliació o reducció es guarda amb
 * la seva data d'efecte perquè l'any es calculi a trams. Sense cap registre, la persona no té
 * % de contracte i tot continua com abans.
 *
 * A part, el % de RETRIBUCIÓ: només informatiu, no es barreja mai amb la jornada (hi ha qui
 * treballa el 75% i cobra el 81,25%).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jornada_contractes')) {
            Schema::create('jornada_contractes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('pct', 5, 2);
                $table->date('valid_from');
                $table->string('motiu', 255)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'valid_from']);
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'retribucio_pct')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('retribucio_pct', 5, 2)->nullable()->after('work_schedule_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'retribucio_pct')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('retribucio_pct'));
        }
        Schema::dropIfExists('jornada_contractes');
    }
};
