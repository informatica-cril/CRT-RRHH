<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresenciaMissatgeriaTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $role = 'worker'): User
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'chat_status' => null]);
    }

    public function test_qui_treballa_a_l_app_sense_obrir_el_xat_surt_connectat(): void
    {
        $treballadora = $this->usuari();
        $companya     = $this->usuari('hr');

        Sanctum::actingAs($treballadora);
        $this->getJson('/api/v1/absence-types')->assertOk();

        Sanctum::actingAs($companya);
        $this->getJson('/api/v1/chat/presence')
             ->assertOk()
             ->assertJsonPath((string) $treballadora->id, true);
    }

    public function test_qui_no_ha_estat_a_l_app_surt_desconnectat(): void
    {
        $absent  = $this->usuari();
        $companya = $this->usuari('hr');

        Sanctum::actingAs($companya);
        $this->getJson('/api/v1/chat/presence')
             ->assertOk()
             ->assertJsonPath((string) $absent->id, false);
    }

    public function test_qui_es_marca_no_disponible_no_surt_connectat_encara_que_hi_sigui(): void
    {
        $discreta = $this->usuari();
        $companya = $this->usuari('hr');

        Sanctum::actingAs($discreta);
        $this->postJson('/api/v1/chat/presence', ['status' => 'offline'])->assertOk();
        Cache::flush();
        $this->getJson('/api/v1/absence-types')->assertOk();

        Sanctum::actingAs($companya);
        $this->getJson('/api/v1/chat/presence')
             ->assertOk()
             ->assertJsonPath((string) $discreta->id, false);
    }

    public function test_direccio_no_publica_presencia_encara_que_estigui_treballant(): void
    {
        $direccio = $this->usuari('admin');
        $companya = $this->usuari('hr');

        Sanctum::actingAs($direccio);
        $this->getJson('/api/v1/absence-types');

        Sanctum::actingAs($companya);
        $this->getJson('/api/v1/chat/presence')
             ->assertOk()
             ->assertJsonPath((string) $direccio->id, false);
    }

    public function test_la_presencia_caduca_sola_quan_algu_tanca_i_marxa(): void
    {
        $marxada = $this->usuari();
        $companya = $this->usuari('hr');

        Sanctum::actingAs($marxada);
        $this->getJson('/api/v1/absence-types')->assertOk();

        $marxada->forceFill(['last_chat_heartbeat' => now()->subMinutes(30)])->saveQuietly();

        Sanctum::actingAs($companya);
        $this->getJson('/api/v1/chat/presence')
             ->assertOk()
             ->assertJsonPath((string) $marxada->id, false);
    }

    public function test_nomes_es_desa_una_marca_per_persona_sense_historic(): void
    {
        $treballadora = $this->usuari();

        Sanctum::actingAs($treballadora);
        $this->getJson('/api/v1/absence-types')->assertOk();
        $primera = $treballadora->fresh()->last_chat_heartbeat;

        Cache::flush();
        $this->travel(2)->minutes();
        $this->getJson('/api/v1/absence-types')->assertOk();
        $segona = $treballadora->fresh()->last_chat_heartbeat;

        $this->assertNotEquals((string) $primera, (string) $segona);
        $this->assertSame(1, \DB::table('users')->where('id', $treballadora->id)->count());
    }
}
