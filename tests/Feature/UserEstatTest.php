<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Activar / desactivar des de la fitxa: admin i RRHH, amb els límits de sempre. */
class UserEstatTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol, bool $actiu = true): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => $actiu]);
    }

    public function test_rrhh_desactiva_i_reactiva_i_es_tanquen_les_sessions(): void
    {
        $treb = $this->usuari('worker');
        $treb->createToken('mobil');
        Sanctum::actingAs($this->usuari('hr'));

        $this->putJson("/api/v1/users/{$treb->id}/estat", ['active' => false])->assertOk()->assertJson(['active' => false]);
        $this->assertFalse((bool) $treb->fresh()->active);
        $this->assertSame(0, $treb->tokens()->count(), 'En desactivar, ha de perdre les sessions obertes.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_DEACTIVATED', 'entity_id' => $treb->id]);

        $this->putJson("/api/v1/users/{$treb->id}/estat", ['active' => true])->assertOk()->assertJson(['active' => true]);
        $this->assertTrue((bool) $treb->fresh()->active);
    }

    public function test_limits(): void
    {
        $hr = $this->usuari('hr');
        $admin = $this->usuari('admin');
        Sanctum::actingAs($hr);
        $this->putJson("/api/v1/users/{$admin->id}/estat", ['active' => false])->assertForbidden();
        $this->putJson("/api/v1/users/{$hr->id}/estat", ['active' => false])->assertStatus(422);
        $this->assertTrue((bool) $admin->fresh()->active);

        $treb = $this->usuari('worker');
        Sanctum::actingAs($treb);
        $this->putJson("/api/v1/users/{$admin->id}/estat", ['active' => false])->assertForbidden();
    }
}
