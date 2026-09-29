<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Reforç ENS de credencials a RRHH: política, generador, throttle i traça de login fallit.
 * Vegeu domi_crt.gt/docs/PROPOSTA-CREDENCIALS-ENS.md.
 */
class CredencialsEnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_politica_compleix(): void
    {
        $this->assertFalse(PasswordPolicy::compleix('curta1!'));
        $this->assertFalse(PasswordPolicy::compleix('sensesimbol1234'));
        $this->assertFalse(PasswordPolicy::compleix('SenseXifraaaa!'));
        $this->assertTrue(PasswordPolicy::compleix('Rehab2026!segur'));
    }

    public function test_generador_sempre_compleix_politica(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $p = PasswordPolicy::genera();
            $this->assertTrue(PasswordPolicy::compleix($p), "generada no compleix: $p");
            $this->assertGreaterThanOrEqual(PasswordPolicy::MIN, mb_strlen($p));
        }
    }

    public function test_reset_password_genera_temporal_que_compleix(): void
    {
        $u = User::create(['name' => 'W', 'email' => 'w@test.local',
            'password' => Hash::make('Antiga2026!xx'), 'role' => 'worker', 'active' => true]);

        $this->postJson('/api/v1/auth/reset-password', ['email' => 'w@test.local'])
            ->assertOk()->assertJson(['success' => true]);

        $u->refresh();
        $this->assertTrue((bool) $u->must_change_password, 'el reset ha de forçar canvi');
        // La contrasenya nova (desconeguda) ha de ser bcrypt i diferent de l'antiga.
        $this->assertFalse(Hash::check('Antiga2026!xx', $u->password), 'la contrasenya ha canviat');
    }

    public function test_reset_password_anti_enumeracio(): void
    {
        // Email inexistent: mateixa resposta d'èxit (no revela que no existeix).
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'no.existeix@test.local'])
            ->assertOk()->assertJson(['success' => true]);
    }

    public function test_login_fallit_deixa_traça(): void
    {
        User::create(['name' => 'W2', 'email' => 'w2@test.local',
            'password' => Hash::make('Bona2026!segura'), 'role' => 'worker', 'active' => true]);

        $this->postJson('/api/v1/auth/login', ['email' => 'w2@test.local', 'password' => 'incorrecta'])
            ->assertStatus(422);

        $this->assertDatabaseHas('audit_logs', ['action' => 'LOGIN_FAILED', 'entity_type' => 'auth']);
    }

    public function test_login_throttle_frena_fuerza_bruta(): void
    {
        User::create(['name' => 'W3', 'email' => 'w3@test.local',
            'password' => Hash::make('Bona2026!segura'), 'role' => 'worker', 'active' => true]);

        $bloquejat = false;
        for ($i = 0; $i < 12; $i++) {
            $r = $this->postJson('/api/v1/auth/login', ['email' => 'w3@test.local', 'password' => 'x']);
            if ($r->getStatusCode() === 429) { $bloquejat = true; break; }
        }
        $this->assertTrue($bloquejat, 'després de 6/min el throttle ha de retornar 429');
    }

    /** must_change_password=true bloqueja tota operació excepte el canvi propi (423). */
    public function test_force_password_change_bloqueja_fins_al_canvi(): void
    {
        $u = User::create(['name' => 'W4', 'email' => 'w4@test.local',
            'password' => Hash::make('Temporal2026!x'), 'role' => 'admin', 'active' => true,
            'must_change_password' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($u);

        // Una ruta protegida qualsevol -> 423 Locked mentre el flag hi és.
        $this->getJson('/api/v1/users')->assertStatus(423)->assertJson(['error' => 'password_change_required']);

        // El canvi propi SÍ és accessible amb el flag actiu.
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Temporal2026!x',
            'password' => 'DefinitivaNova2026!',
            'password_confirmation' => 'DefinitivaNova2026!',
        ])->assertOk()->assertJson(['success' => true]);

        // Flag tret i, ara sí, la ruta protegida respon normal.
        $this->assertFalse((bool) $u->fresh()->must_change_password);
        $this->getJson('/api/v1/users')->assertOk();
    }

    public function test_change_password_exigeix_actual_i_politica(): void
    {
        $u = User::create(['name' => 'W5', 'email' => 'w5@test.local',
            'password' => Hash::make('Actual2026!seg'), 'role' => 'worker', 'active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($u);

        // Actual incorrecta -> 422
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'malament', 'password' => 'NovaValida2026!',
            'password_confirmation' => 'NovaValida2026!'])->assertStatus(422);

        // Nova feble -> 422 (política)
        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'Actual2026!seg', 'password' => 'feble',
            'password_confirmation' => 'feble'])->assertStatus(422);
    }
}
