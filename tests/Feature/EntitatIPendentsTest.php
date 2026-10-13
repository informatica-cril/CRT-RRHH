<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Treballadors: entitat i hospital a la llista. Registres: pendents per mes. */
class EntitatIPendentsTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $rol = 'worker', array $extra = []): User
    {
        return User::create($extra + ['name' => 'P ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    public function test_rrhh_canvia_l_entitat_i_la_llista_porta_els_departaments(): void
    {
        $t = $this->persona();
        Sanctum::actingAs($this->persona('hr'));

        $this->putJson("/api/v1/users/{$t->id}", ['entitat' => 'CRIL_CRT'])->assertOk();
        $this->assertSame('CRIL_CRT', $t->fresh()->entitat);
        $this->putJson("/api/v1/users/{$t->id}", ['entitat' => 'HOSPITAL'])->assertStatus(422)
            ->assertJsonFragment(['L\'entitat no és vàlida (CRIL, CRT o totes dues).']);

        $fila = collect($this->getJson('/api/v1/users/bulk-index')->assertOk()->json('users'))->firstWhere('id', $t->id);
        $this->assertArrayHasKey('departments', $fila);   // la pantalla hi mira si és de l'hospital
    }

    public function test_pendents_per_mes(): void
    {
        $t = $this->persona();
        foreach ([['2026-10-02', 'pending'], ['2026-09-15', 'pending'], ['2026-09-20', 'pending'], ['2026-08-03', 'approved']] as [$d, $e]) {
            DB::table('work_logs')->insert(['user_id' => $t->id, 'date' => $d, 'start_time' => "$d 08:00:00",
                'status' => $e, 'created_at' => now(), 'updated_at' => now()]);
        }
        Sanctum::actingAs($this->persona('hr'));

        $this->getJson('/api/v1/work-logs/pendents-per-mes')->assertOk()
            ->assertExactJson([['mes' => '2026-10', 'n' => 1], ['mes' => '2026-09', 'n' => 2]]);

        Sanctum::actingAs($t);
        $this->getJson('/api/v1/work-logs/pendents-per-mes')->assertForbidden();
    }
}
