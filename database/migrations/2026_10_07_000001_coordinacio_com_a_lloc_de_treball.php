<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Coordinació deixa de ser un rol amb permisos de gestió (Direcció, 07-10-2026): és només un
 * lloc de treball, com Informàtica o Neteja. Les persones amb rol 'coordinator' passen a
 * 'worker' (així també poden fitxar) i conserven el lloc «Coordinación» al perfil; RRHH en
 * tria després la zona (Coordinación Vallés / Coordinación BCN).
 *
 * Només dades, cap columna. El down() torna el rol a qui té un lloc de coordinació.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'coordinator')
            ->where(fn ($q) => $q->whereNull('job_profile')->orWhere('job_profile', 'not like', 'Coordinaci%'))
            ->update(['job_profile' => 'Coordinación']);
        DB::table('users')->where('role', 'coordinator')->update(['role' => 'worker']);
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'worker')->where('job_profile', 'like', 'Coordinaci%')
            ->update(['role' => 'coordinator']);
    }
};
