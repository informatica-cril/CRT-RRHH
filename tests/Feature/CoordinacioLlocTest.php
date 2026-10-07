<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Coordinació és un lloc de treball, no un rol de gestió (Direcció, 07-10-2026). */
class CoordinacioLlocTest extends TestCase
{
    use RefreshDatabase;

    private function coordinadora(): User
    {
        return User::create([
            'name' => 'Coordinadora', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'coordinator', 'job_profile' => 'Coordinación BCN', 'active' => true,
        ]);
    }

    public function test_el_rol_coordinacio_es_desa_com_a_treballador(): void
    {
        $u = $this->coordinadora();

        $this->assertSame('worker', $u->fresh()->role);
        $this->assertSame('Coordinación BCN', $u->fresh()->job_profile);
    }

    public function test_una_coordinadora_no_te_acces_a_la_gestio(): void
    {
        Sanctum::actingAs($this->coordinadora()->fresh());

        $this->getJson('/api/v1/work-logs')->assertForbidden();
        $this->getJson('/api/v1/auth-codes')->assertForbidden();
        $this->getJson('/api/v1/safata/resum')->assertForbidden();
    }

    public function test_la_migracio_passa_les_coordinadores_a_treballadores(): void
    {
        // Simula una coordinadora antiga escrita directament a la BD (sense passar pel model).
        \Illuminate\Support\Facades\DB::table('users')->insert([
            'name' => 'Antiga', 'email' => 'antiga@test.local', 'password' => 'x', 'role' => 'coordinator',
            'job_profile' => 'Fisioterapeuta', 'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $migracio = require database_path('migrations/2026_10_07_000001_coordinacio_com_a_lloc_de_treball.php');
        $migracio->up();

        $u = User::where('email', 'antiga@test.local')->first();
        $this->assertSame('worker', $u->role);
        $this->assertSame('Coordinación', $u->job_profile);

        $migracio->down();
        $this->assertSame('coordinator', $u->fresh()->role);
    }
}
