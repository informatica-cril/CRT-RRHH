<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Derivació d'incidències a RRHH o Informàtica i notificacions dins de l'app. */
class IncidenciesDerivacioTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol, bool $informatica = false): User
    {
        return User::create(['name' => 'P ' . $rol . ($informatica ? ' IT' : ''), 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true, 'es_informatica' => $informatica]);
    }

    private function envia(User $t, string $dest): int
    {
        Sanctum::actingAs($t);

        return $this->postJson('/api/v1/incidencies', ['tipus' => 'app', 'destinatari' => $dest, 'assumpte' => "Per a {$dest}",
            'descripcio' => "Descripció prou llarga per a {$dest}."])->assertCreated()->json('id');
    }

    public function test_sense_destinatari_va_a_rrhh_com_fins_ara(): void
    {
        $t = $this->usuari('worker');
        Sanctum::actingAs($t);
        $id = $this->postJson('/api/v1/incidencies', ['tipus' => 'app', 'assumpte' => 'Hola', 'descripcio' => 'Una descripció llarga.'])->assertCreated()->json('id');

        $this->assertSame('rrhh', \App\Models\Incidencia::find($id)->destinatari);
    }

    public function test_cada_equip_nomes_veu_i_gestiona_les_seves(): void
    {
        $t = $this->usuari('worker');
        $it = $this->usuari('worker', true);
        $hr = $this->usuari('hr');
        $admin = $this->usuari('admin');
        $idHr = $this->envia($t, 'rrhh');
        $idIt = $this->envia($t, 'informatica');

        Sanctum::actingAs($it);
        $this->assertSame([$idIt], collect($this->getJson('/api/v1/incidencies')->assertOk()->json())->pluck('id')->all());
        $this->postJson("/api/v1/incidencies/{$idIt}/missatges", ['text' => 'Prova de reiniciar la tauleta.'])->assertOk();
        $this->postJson("/api/v1/incidencies/{$idIt}/resol")->assertOk();
        $this->postJson("/api/v1/incidencies/{$idHr}/resol")->assertForbidden();
        $this->postJson("/api/v1/incidencies/{$idHr}/missatges", ['text' => 'Intent de resposta aliena.'])->assertForbidden();

        Sanctum::actingAs($hr);
        $this->assertSame([$idHr], collect($this->getJson('/api/v1/incidencies')->json())->pluck('id')->all());
        $this->postJson("/api/v1/incidencies/{$idIt}/resol")->assertForbidden();

        Sanctum::actingAs($admin);
        $this->assertCount(2, $this->getJson('/api/v1/incidencies')->json());
    }

    public function test_un_treballador_normal_no_gestiona(): void
    {
        $t = $this->usuari('worker');
        $this->envia($t, 'informatica');
        Sanctum::actingAs($t);
        $this->getJson('/api/v1/incidencies')->assertForbidden();
    }

    public function test_notificacions_resposta_a_l_emissor_i_incidencia_nova_a_informatica(): void
    {
        $t = $this->usuari('worker');
        $it = $this->usuari('worker', true);
        $hr = $this->usuari('hr');
        $id = $this->envia($t, 'informatica');

        Sanctum::actingAs($it);
        $this->assertSame(['Tens una incidència per atendre'], collect($this->getJson('/api/v1/notificacions')->json('items'))->pluck('titol')->all());
        Sanctum::actingAs($hr);
        $this->assertSame(0, $this->getJson('/api/v1/notificacions')->json('total'));   // no és el seu àmbit

        Sanctum::actingAs($it);
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Ja ho estem mirant.'])->assertOk();
        $this->assertSame(0, $this->getJson('/api/v1/notificacions')->json('total'));   // ja no és «nova»

        Sanctum::actingAs($t);
        $items = $this->getJson('/api/v1/notificacions')->json('items');
        $this->assertSame('Resposta a la teva incidència', $items[0]['titol']);
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/incidencies/meves/vistes')->assertOk();
        $this->assertSame(0, $this->getJson('/api/v1/notificacions')->json('total'));   // vista: el comptador baixa
    }

    public function test_nomes_avisa_a_gestio_si_hi_ha_una_resposta_del_treballador(): void
    {
        $t = $this->usuari('worker');
        $hr = $this->usuari('hr');
        $id = $this->envia($t, 'rrhh');
        $avisats = fn () => collect($this->getJson('/api/v1/safata/resum')->json('cues'))->firstWhere('clau', 'incidencies')['n'] ?? 0;

        Sanctum::actingAs($hr);
        $this->assertSame(1, $avisats());                                    // nova, sense resposta: s'ha d'atendre

        $this->travel(1)->minutes();
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Indica la teva zona, si us plau.'])->assertOk();
        $this->assertSame(0, $avisats());                                    // la pilota és al treballador: no s'avisa

        Sanctum::actingAs($t);
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Treballo al Raval i a Gràcia.'])->assertOk();
        Sanctum::actingAs($hr);
        $this->assertSame(1, $avisats());                                    // ha contestat: torna a avisar
    }

    public function test_notificacions_porta_les_alertes_pendents_i_desapareixen_en_descartar(): void
    {
        $t = $this->usuari('worker');
        $a = \App\Models\WorkLogAlert::create(['user_id' => $t->id, 'type' => 'no_clock_out', 'message' => 'Tens un fitxatge sense tancar.', 'scheduled_at' => now(), 'sent_at' => now()]);
        Sanctum::actingAs($t);

        $items = $this->getJson('/api/v1/notificacions')->json('items');
        $this->assertSame('urgent', $items[0]['nivell']);
        $this->assertSame($a->id, $items[0]['alerta_id']);

        $this->postJson("/api/v1/work-log-alerts/{$a->id}/dismiss")->assertOk();
        $this->assertSame(0, $this->getJson('/api/v1/notificacions')->json('total'));
    }
}
