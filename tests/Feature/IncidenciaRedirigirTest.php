<?php

namespace Tests\Feature;

use App\Models\Incidencia;
use App\Models\IncidenciaMissatge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Redirigir una incidència a l'altre àmbit (RRHH <-> Informàtica) si s'ha enviat a qui no tocava. */
class IncidenciaRedirigirTest extends TestCase
{
    use RefreshDatabase;

    private function persona(string $rol, array $extra = []): User
    {
        return User::create($extra + ['name' => 'P ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    private function inc(User $t, string $dest = 'informatica'): Incidencia
    {
        return Incidencia::create(['user_id' => $t->id, 'tipus' => 'app', 'destinatari' => $dest, 'assumpte' => 'Prova',
            'descripcio' => 'Descripció de la prova.', 'estat' => 'oberta', 'estat_gestio' => 'en_curs']);
    }

    public function test_informatica_la_passa_a_rrhh_i_rrhh_la_veu_com_a_nova(): void
    {
        $t = $this->persona('worker');
        $info = $this->persona('worker', ['es_informatica' => true]);
        $hr = $this->persona('hr');
        $i = $this->inc($t);
        IncidenciaMissatge::create(['incidencia_id' => $i->id, 'user_id' => $info->id, 'text' => 'Mirem què passa']);

        Sanctum::actingAs($info);
        $this->postJson("/api/v1/incidencies/{$i->id}/redirigeix", ['destinatari' => 'rrhh'])->assertOk()->assertJsonPath('destinatari', 'rrhh');
        $this->assertSame([], $this->getJson('/api/v1/incidencies')->json());   // qui l'ha passada ja no la veu

        Sanctum::actingAs($hr);
        $this->assertCount(1, $this->getJson('/api/v1/incidencies')->json());
        $this->assertSame('nova', $i->fresh()->estat_gestio);
        $avisos = collect($this->getJson('/api/v1/notificacions')->json('items'))->pluck('tipus');
        $this->assertContains('incidencia', $avisos->all());   // surt als avisos de RRHH tot i que l'últim missatge era de gestió
    }

    public function test_el_superior_la_pot_passar_a_qualsevol_banda(): void
    {
        $i = $this->inc($this->persona('worker'), 'rrhh');
        Sanctum::actingAs($this->persona('admin'));
        $this->postJson("/api/v1/incidencies/{$i->id}/redirigeix", ['destinatari' => 'informatica'])->assertOk();
        $this->assertSame('informatica', $i->fresh()->destinatari);
    }

    public function test_no_es_pot_passar_a_on_ja_es_ni_la_pot_tocar_qui_no_la_gestiona(): void
    {
        $t = $this->persona('worker');
        $i = $this->inc($t, 'informatica');

        Sanctum::actingAs($this->persona('hr'));   // RRHH no gestiona les d'Informàtica
        $this->postJson("/api/v1/incidencies/{$i->id}/redirigeix", ['destinatari' => 'rrhh'])->assertForbidden();

        Sanctum::actingAs($this->persona('admin'));
        $this->postJson("/api/v1/incidencies/{$i->id}/redirigeix", ['destinatari' => 'informatica'])->assertStatus(422);

        Sanctum::actingAs($t);
        $this->postJson("/api/v1/incidencies/{$i->id}/redirigeix", ['destinatari' => 'rrhh'])->assertForbidden();
    }
}
