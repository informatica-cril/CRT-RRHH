<?php

namespace Tests\Feature;

use App\Models\AuthorizationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CARACTERITZACIÓ de la creació de codis d'autorització abans del refactor
 * a FormRequests. Fixa que les DUES capes de validació segueixen actives:
 * les regles bàsiques (422 de validate) i la de negoci (franja nocturna).
 */
class AuthCodeCaracteritzacioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true,
        ]);
    }

    private function payload(User $admin, array $overrides = []): array
    {
        return array_merge([
            'code' => 'HC-' . strtoupper(uniqid()),
            'concept' => 'Hores complementàries de prova',
            'authorized_hours' => 2,
            'time_slot_start' => '09:00',
            'time_slot_end' => '13:00',
            'generated_by' => $admin->id,
            'valid_from' => '2026-07-01',
            'valid_to' => '2026-07-31',
        ], $overrides);
    }

    public function test_store_crea_codi_valid(): void
    {
        $a = $this->admin();
        Sanctum::actingAs($a);

        $r = $this->postJson('/api/v1/auth-codes', $this->payload($a));
        $r->assertStatus(201);
        $this->assertSame(1, AuthorizationCode::count());
    }

    public function test_store_sense_camps_obligatoris_respon_422(): void
    {
        $a = $this->admin();
        Sanctum::actingAs($a);

        $this->postJson('/api/v1/auth-codes', ['concept' => 'incomplet'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'authorized_hours', 'generated_by']);
    }

    public function test_store_franja_nocturna_es_rebutja_422(): void
    {
        $a = $this->admin();
        Sanctum::actingAs($a);

        // La franja nocturna (22:00–06:00) està prohibida: validació de NEGOCI,
        // posterior a validate(). Ha de sobreviure el refactor a FormRequest.
        $r = $this->postJson('/api/v1/auth-codes', $this->payload($a, [
            'time_slot_start' => '21:00', 'time_slot_end' => '23:00',
        ]));
        $r->assertStatus(422)->assertJsonValidationErrors(['time_slot_start']);
        $this->assertSame(0, AuthorizationCode::count());
    }
}
