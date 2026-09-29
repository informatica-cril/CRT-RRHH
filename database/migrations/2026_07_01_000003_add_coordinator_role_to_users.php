<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Añadir 'coordinator' al enum de role (aditivo, no elimina valores existentes)
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('role'));
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->enum('role', ['admin', 'coordinator', 'worker'])->default('worker'));
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','coordinator','worker') NOT NULL DEFAULT 'worker'");
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            // sqlite (nomes tests, taula buida aqui): refem la columna per renovar el CHECK.
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->dropColumn('role'));
            \Illuminate\Support\Facades\Schema::table('users', fn ($t) => $t->enum('role', ['admin', 'worker'])->default('worker'));
            return;
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','worker') NOT NULL DEFAULT 'worker'");
    }
};
