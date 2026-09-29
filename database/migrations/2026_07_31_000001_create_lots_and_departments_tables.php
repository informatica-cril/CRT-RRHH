<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LOT territorial i DEPARTAMENT — les dues dimensions que faltaven al quadre de
 * disponibilitat (Direcció, 31-07-2026).
 *
 * ── LOT ─────────────────────────────────────────────────────────────────────────────
 * És el lot de clàusula del contracte amb CatSalut: territori ample. Els valors no me'ls
 * invento, són els que ja fa servir la facturació de domi (`crt_lot`, i van al fitxer FSE
 * per les posicions 17-21):
 *      B1 → Barcelona   (zona DOMI-BCN)
 *      B9 → Vallès      (zona DOMI-VALLES)
 *
 * Es crea una taula PRÒPIA a RRHH i no es llegeix la de domi a posta: són dues aplicacions
 * amb repositoris separats que només s'integren per API. Si algun dia CRT guanya un lot
 * nou, s'ha d'afegir als dos costats —és una fila— i val més això que una dependència
 * creuada de bases de dades.
 *
 * ── DEPARTAMENT ─────────────────────────────────────────────────────────────────────
 * Servei × modalitat. Els cinc que hi ha:
 *      LOGO_AMBU · Logopèdia ambulatòria
 *      LOGO_DOMI · Logopèdia domiciliària
 *      RHB_AMBU  · Rehabilitació ambulatòria
 *      RHB_DOMI  · Rehabilitació domiciliària
 *      TO_DOMI   · Teràpia ocupacional
 *
 * ⚠️ SUPÒSIT SOBRE LA TERÀPIA OCUPACIONAL: es modela com a domiciliària, perquè a domi el
 * perfil `tpo` treballa en paral·lel al de rehabilitació a domicili. Si també fa
 * ambulatòria, això és UNA FILA de seed més (TO_AMBU) i cap canvi d'esquema.
 *
 * Es diu «Rehabilitació» i no «fisioteràpia» perquè és el terme que fa servir CRT.
 *
 * Les dues relacions són N:M a posta: una persona pot cobrir Barcelona i Vallès, i pot fer
 * ambulatòria i domiciliària alhora. Modelar-ho com a columna única obligaria a duplicar
 * treballadors, que és pitjor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $t) {
            $t->id();
            $t->string('code', 10)->unique();      // B1, B9
            $t->string('name');                     // Barcelona, Vallès
            $t->string('domi_zone', 30)->nullable(); // DOMI-BCN / DOMI-VALLES, per conciliar amb domi
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();       // RHB_DOMI…
            $t->string('name');
            $t->string('service', 10);              // LOGO | RHB | TO
            $t->string('modality', 10);             // AMBU | DOMI
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        Schema::create('lot_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'lot_id']);
        });

        Schema::create('department_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('department_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'department_id']);
        });

        $ara = now();
        DB::table('lots')->insert([
            ['code' => 'B1', 'name' => 'Barcelona', 'domi_zone' => 'DOMI-BCN',    'sort' => 1, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
            ['code' => 'B9', 'name' => 'Vallès',    'domi_zone' => 'DOMI-VALLES', 'sort' => 2, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
        ]);

        DB::table('departments')->insert([
            ['code' => 'RHB_DOMI',  'name' => 'Rehabilitació domiciliària',   'service' => 'RHB',  'modality' => 'DOMI', 'sort' => 1, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
            ['code' => 'RHB_AMBU',  'name' => 'Rehabilitació ambulatòria',    'service' => 'RHB',  'modality' => 'AMBU', 'sort' => 2, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
            ['code' => 'LOGO_DOMI', 'name' => 'Logopèdia domiciliària',       'service' => 'LOGO', 'modality' => 'DOMI', 'sort' => 3, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
            ['code' => 'LOGO_AMBU', 'name' => 'Logopèdia ambulatòria',        'service' => 'LOGO', 'modality' => 'AMBU', 'sort' => 4, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
            ['code' => 'TO_DOMI',   'name' => 'Teràpia ocupacional',          'service' => 'TO',   'modality' => 'DOMI', 'sort' => 5, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('department_user');
        Schema::dropIfExists('lot_user');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('lots');
    }
};
