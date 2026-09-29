<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Afegeix 'hr' (Responsable RRHH) a l'enum de role (additiu, no elimina valors existents).
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('role'));
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->enum('role', ['admin', 'coordinator', 'worker', 'service', 'hr'])->default('worker'));
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','coordinator','worker','service','hr') NOT NULL DEFAULT 'worker'");
    }

    public function down(): void
    {
        // Torna qualsevol 'hr' a 'coordinator' abans de treure el valor de l'enum (evita fallada de dades).
        DB::table('users')->where('role', 'hr')->update(['role' => 'coordinator']);
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('role'));
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->enum('role', ['admin', 'coordinator', 'worker', 'service'])->default('worker'));
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','coordinator','worker','service') NOT NULL DEFAULT 'worker'");
    }
};
