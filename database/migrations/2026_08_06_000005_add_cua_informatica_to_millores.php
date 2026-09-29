<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('millores', function (Blueprint $table) {
            $table->text('detall_tecnic')->nullable()->after('proposta');
            $table->decimal('hores_ia', 5, 1)->nullable()->after('esforc');
            $table->decimal('hores_admin', 5, 1)->nullable()->after('hores_ia');
            $table->unsignedTinyInteger('prioritat')->nullable()->after('hores_admin');
            $table->timestamp('encuada_ts')->nullable()->after('decidit_ts');
            $table->timestamp('iniciada_ts')->nullable()->after('encuada_ts');
            $table->timestamp('feta_ts')->nullable()->after('iniciada_ts');
        });

        // MODIFY només existeix a MySQL; a sqlite (tests) la columna es refà com a text.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE millores MODIFY estat
                ENUM('nova','en_cua','en_curs','feta','descartada','acceptada','preparada','preparacio_fallida')
                NOT NULL DEFAULT 'nova'");
        } else {
            Schema::table('millores', function (Blueprint $table) {
                $table->string('estat', 20)->default('nova')->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('millores', function (Blueprint $table) {
            $table->dropColumn(['detall_tecnic', 'hores_ia', 'hores_admin', 'prioritat',
                                'encuada_ts', 'iniciada_ts', 'feta_ts']);
        });
    }
};
