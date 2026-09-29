<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Afegir 'service' al enum de role (compte de servei read-only per a domi). Additiu.
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('role'));
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->enum('role', ['admin', 'coordinator', 'worker', 'service'])->default('worker'));
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','coordinator','worker','service') NOT NULL DEFAULT 'worker'");
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('role'));
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->enum('role', ['admin', 'coordinator', 'worker'])->default('worker'));
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','coordinator','worker') NOT NULL DEFAULT 'worker'");
    }
};
