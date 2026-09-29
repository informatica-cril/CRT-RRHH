<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Afegeix 'group' a l'enum de chat_conversations.type.
     *
     * REESCRITA (27/07/2026) sense canviar-ne l'efecte: doctrine/dbal no sap fer
     * ->change() sobre columnes enum i la migració petava en qualsevol instal·lació
     * de zero (inclosa la BD sqlite dels tests). A MySQL es fa amb MODIFY (preserva
     * dades, idèntic al que la versió original va fer a producció); a sqlite (només
     * tests, BD buida en aquest punt) es refà la columna, que renova el CHECK.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('chat_conversations', fn (Blueprint $t) => $t->dropColumn('type'));
            Schema::table('chat_conversations', fn (Blueprint $t) => $t->enum('type', ['dm', 'group', 'channel'])->default('dm'));
        } else {
            DB::statement("ALTER TABLE chat_conversations MODIFY COLUMN type ENUM('dm','group','channel') NOT NULL DEFAULT 'dm'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('chat_conversations', fn (Blueprint $t) => $t->dropColumn('type'));
            Schema::table('chat_conversations', fn (Blueprint $t) => $t->enum('type', ['dm', 'channel'])->default('dm'));
        } else {
            DB::statement("ALTER TABLE chat_conversations MODIFY COLUMN type ENUM('dm','channel') NOT NULL DEFAULT 'dm'");
        }
    }
};
