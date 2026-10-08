<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** «Accés a l'app» i estat per a una selecció de persones (Treballadors). */
class AccesAppTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $rol = 'worker', array $extra = []): User
    {
        return User::create($extra + ['name' => 'P ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => 'Contrasenya-123', 'role' => $rol, 'active' => true]);
    }

    public function test_sense_acces_no_entra_pero_segueix_activa(): void
    {
        $t = $this->persona();
        $t->createToken('sessio-oberta');
        Sanctum::actingAs($this->persona('hr'));

        $this->postJson('/api/v1/users/acces-app', ['ids' => [$t->id], 'acces' => false])
            ->assertOk()->assertJson(['canviats' => 1]);

        $t->refresh();
        $this->assertFalse($t->acces_app);
        $this->assertTrue($t->active);                       // segueix a la plantilla
        $this->assertSame(0, $t->tokens()->count());         // sessions obertes tancades

        $this->postJson('/api/v1/auth/login', ['email' => $t->email, 'password' => 'Contrasenya-123'])
            ->assertStatus(422)->assertJsonFragment(['Aquest compte no té accés a l\'app. Si creus que és un error, parla amb RRHH.']);

        // Tornar l'accés: torna a entrar.
        $this->postJson('/api/v1/users/acces-app', ['ids' => [$t->id], 'acces' => true])->assertOk();
        $this->postJson('/api/v1/auth/login', ['email' => $t->email, 'password' => 'Contrasenya-123'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_rrhh_no_treu_l_acces_a_admins_ni_a_si_mateixa(): void
    {
        $hr = $this->persona('hr');
        $admin = $this->persona('admin');
        Sanctum::actingAs($hr);

        $r = $this->postJson('/api/v1/users/acces-app', ['ids' => [$hr->id, $admin->id], 'acces' => false])->assertOk();
        $this->assertSame(0, $r->json('canviats'));
        $this->assertCount(2, $r->json('omesos'));
        $this->assertNotFalse($admin->fresh()->acces_app);
    }

    public function test_activar_i_desactivar_una_seleccio(): void
    {
        $a = $this->persona('worker', ['work_type' => 'AMBULATORIA']);
        $b = $this->persona('worker', ['work_type' => 'AMBULATORIA']);
        $admin = $this->persona('admin');
        Sanctum::actingAs($this->persona('hr'));

        $r = $this->postJson('/api/v1/users/estat-seleccio', ['ids' => [$a->id, $b->id, $admin->id], 'active' => false])->assertOk();
        $this->assertSame(2, $r->json('canviats'));
        $this->assertCount(1, $r->json('omesos'));           // l'administrador no el toca RRHH
        $this->assertFalse($a->fresh()->active);
        $this->assertFalse($b->fresh()->active);
        $this->assertTrue($admin->fresh()->active);

        $this->postJson('/api/v1/users/estat-seleccio', ['ids' => [$a->id], 'active' => true])->assertOk()->assertJson(['canviats' => 1]);
        $this->assertTrue($a->fresh()->active);
    }

    public function test_un_treballador_no_pot(): void
    {
        $t = $this->persona();
        Sanctum::actingAs($t);
        $this->postJson('/api/v1/users/acces-app', ['ids' => [$t->id], 'acces' => false])->assertForbidden();
        $this->postJson('/api/v1/users/estat-seleccio', ['ids' => [$t->id], 'active' => false])->assertForbidden();
    }
}
