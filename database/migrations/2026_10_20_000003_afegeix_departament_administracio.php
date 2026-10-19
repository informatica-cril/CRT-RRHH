<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Departament d'Administració (RRHH, 19-10-2026): el personal d'administració també fa quadrant i
 * s'ha de poder filtrar com els altres departaments. RRHH l'assigna a la fitxa de cada persona.
 * Additiu: només afegeix la fila si no hi és.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('departments')->where('code', 'ADMIN')->exists()) return;

        DB::table('departments')->insert([
            'code' => 'ADMIN', 'name' => 'Administració', 'service' => 'ADM', 'modality' => 'AMBU',
            'sort' => (int) DB::table('departments')->max('sort') + 1, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('departments')->where('code', 'ADMIN')->delete();
    }
};
