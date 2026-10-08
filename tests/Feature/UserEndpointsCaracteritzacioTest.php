<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CARACTERITZACIÓ dels endpoints d'usuaris abans del refactor a FormRequests.
 *
 * El contracte que es fixa:
 *   - La contrasenya MAI apareix en cap resposta (index, store, show, update).
 *   - La contrasenya es guarda hashejada.
 *   - Els camps privilegiats (role, active, ...) s'IGNOREN en silenci si qui
 *     edita no és admin (complementa RelacioAutonomTest, que fixa relacio).
 *   - Les validacions bàsiques responen 422.
 */
class UserEndpointsCaracteritzacioTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin', 'email' => 'admin' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true,
        ]);
    }

    private function worker(): User
    {
        return User::create([
            'name' => 'Treballador', 'email' => 'w' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);
    }

    public function test_index_mai_exposa_password(): void
    {
        Sanctum::actingAs($this->admin());
        $this->worker();

        $r = $this->getJson('/api/v1/users');
        $r->assertStatus(200);
        foreach ($r->json() as $u) {
            $this->assertArrayNotHasKey('password', $u);
        }
    }

    public function test_store_crea_usuari_amb_password_hashejat_i_ocult(): void
    {
        Sanctum::actingAs($this->admin());

        $r = $this->postJson('/api/v1/users', [
            'name' => 'Nova Fisio', 'email' => 'nova@test.local',
            'password' => 'secret123', 'role' => 'worker',
        ]);
        $r->assertStatus(201);
        $this->assertArrayNotHasKey('password', $r->json());

        $u = User::where('email', 'nova@test.local')->firstOrFail();
        $this->assertTrue(Hash::check('secret123', $u->password),
            'la contrasenya s\'ha de guardar hashejada');
    }

    public function test_l_alta_obliga_a_canviar_la_contrasenya_en_el_primer_acces(): void
    {
        Sanctum::actingAs($this->admin());

        // Alta individual (pantalla de Treballadors): no envia cap marca de canvi.
        $this->postJson('/api/v1/users', [
            'name' => 'Nova Alta', 'email' => 'alta@test.local', 'password' => 'Generada-123', 'role' => 'worker',
        ])->assertCreated();
        // Alta massiva: encara que la pantalla enviï que no cal canviar-la.
        $this->postJson('/api/v1/users/bulk', ['users' => [[
            'name' => 'Alta Massiva', 'email' => 'massiva@test.local', 'password' => 'DeLExcel-123',
            'role' => 'worker', 'must_change_password' => false,
        ]]])->assertCreated();

        foreach (['alta@test.local', 'massiva@test.local'] as $email) {
            $nou = User::where('email', $email)->first();
            $this->assertTrue((bool) $nou->must_change_password, $email);

            // En entrar, qualsevol pantalla queda bloquejada fins que la canviï.
            Sanctum::actingAs($nou);
            $this->getJson('/api/v1/work-logs/user/' . $nou->id)->assertStatus(423);
            $this->getJson('/api/v1/users/me')->assertOk()->assertJsonPath('must_change_password', true);
        }
    }

    public function test_nomes_admin_crea_administradors(): void
    {
        $hr = User::create(['name' => 'RRHH', 'email' => 'hr' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);
        Sanctum::actingAs($hr);

        $this->postJson('/api/v1/users', ['name' => 'Gerent', 'email' => 'gerent@test.local', 'password' => 'Generada-123', 'role' => 'admin'])
            ->assertForbidden();
        $this->postJson('/api/v1/users/bulk', ['users' => [['name' => 'G2', 'email' => 'g2@test.local', 'password' => 'x', 'role' => 'admin']]])
            ->assertForbidden();
        $this->assertSame(0, User::whereIn('email', ['gerent@test.local', 'g2@test.local'])->count());

        // RRHH sí que dona d'alta treballadors.
        $this->postJson('/api/v1/users', ['name' => 'Fisio', 'email' => 'fisio@test.local', 'password' => 'Generada-123', 'role' => 'worker'])
            ->assertCreated();

        // Un administrador sí que crea administradors.
        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/users', ['name' => 'Gerent', 'email' => 'gerent@test.local', 'password' => 'Generada-123', 'role' => 'admin'])
            ->assertCreated();
    }

    public function test_store_sense_email_o_duplicat_respon_422(): void
    {
        Sanctum::actingAs($this->admin());
        $existent = $this->worker();

        $this->postJson('/api/v1/users', ['name' => 'X', 'password' => 'secret123'])
            ->assertStatus(422)->assertJsonValidationErrors(['email']);

        $this->postJson('/api/v1/users', [
            'name' => 'X', 'email' => $existent->email, 'password' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_update_no_admin_ignora_camps_privilegiats(): void
    {
        $w = $this->worker();
        Sanctum::actingAs($w);

        $r = $this->putJson("/api/v1/users/{$w->id}", [
            'name' => 'Nom Canviat',
            'role' => 'admin',   // privilegiat: s'ha d'ignorar
            'active' => false,   // privilegiat: s'ha d'ignorar
        ]);
        $r->assertStatus(200);

        $w->refresh();
        $this->assertSame('Nom Canviat', $w->name);
        $this->assertSame('worker', $w->role, 'un worker no es pot autoascendir');
        $this->assertTrue((bool) $w->active);
    }

    public function test_update_admin_si_pot_canviar_role(): void
    {
        Sanctum::actingAs($this->admin());
        $w = $this->worker();

        $this->putJson("/api/v1/users/{$w->id}", ['role' => 'hr'])
            ->assertStatus(200);
        $this->assertSame('hr', $w->fresh()->role);

        // 'coordinator' ja no és un rol de gestió: es desa com a treballador.
        $this->putJson("/api/v1/users/{$w->id}", ['role' => 'coordinator'])->assertStatus(200);
        $this->assertSame('worker', $w->fresh()->role);
    }

    public function test_show_no_exposa_password(): void
    {
        Sanctum::actingAs($this->admin());
        $w = $this->worker();

        $r = $this->getJson("/api/v1/users/{$w->id}");
        $r->assertStatus(200);
        $this->assertArrayNotHasKey('password', $r->json());
    }
}
