<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Incidències: el treballador n'envia; RRHH i Superior responen dins de l'app i les resolen (sense correu). */
class IncidenciesTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    public function test_conversa_dins_de_l_app_sense_correu(): void
    {
        Mail::fake();
        $t = $this->usuari('worker');
        $hr = $this->usuari('hr');

        Sanctum::actingAs($t);
        $id = $this->postJson('/api/v1/incidencies', ['tipus' => 'fitxatge', 'assumpte' => 'No em deixa fitxar',
            'descripcio' => "Avui al matí l'app no carregava el botó."])->assertCreated()->json('id');
        $this->getJson('/api/v1/incidencies')->assertForbidden();   // la llista de gestió no és seva
        $this->assertSame(0, $this->getJson('/api/v1/incidencies/meves/no-llegides')->json('n'));

        Sanctum::actingAs($hr);
        $this->assertSame(1, collect($this->getJson('/api/v1/safata/resum')->json('cues'))->firstWhere('clau', 'incidencies')['n']);
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Prova de tancar i obrir l\'app.'])->assertOk();

        // El treballador veu la resposta com a nova fins que obre les seves incidències.
        Sanctum::actingAs($t);
        $this->assertSame(1, $this->getJson('/api/v1/incidencies/meves/no-llegides')->json('n'));
        $meves = $this->getJson('/api/v1/incidencies/meves')->assertOk()->json();
        $this->assertTrue($meves[0]['resposta_nova']);
        $this->assertSame('Prova de tancar i obrir l\'app.', $meves[0]['missatges'][0]['text']);
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/incidencies/meves/vistes')->assertOk();
        $this->assertSame(0, $this->getJson('/api/v1/incidencies/meves/no-llegides')->json('n'));

        // RRHH la resol; si el treballador hi torna a escriure, es torna a obrir.
        Sanctum::actingAs($hr);
        $this->postJson("/api/v1/incidencies/{$id}/resol")->assertOk()->assertJson(['estat' => 'resolta']);
        Sanctum::actingAs($t);
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Torna a passar.'])->assertOk()->assertJson(['estat' => 'oberta']);

        Mail::assertNothingSent();
    }

    public function test_un_altre_treballador_no_pot_escriure_a_una_incidencia_aliena(): void
    {
        Sanctum::actingAs($this->usuari('worker'));
        $id = $this->postJson('/api/v1/incidencies', ['tipus' => 'altres', 'assumpte' => 'Prova', 'descripcio' => 'Una prova qualsevol.'])->json('id');

        Sanctum::actingAs($this->usuari('worker'));
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Hola'])->assertForbidden();
    }

    public function test_estats_de_gestio_nomes_per_a_rrhh_i_el_treballador_veu_oberta_o_resolta(): void
    {
        $t = $this->usuari('worker');
        $hr = $this->usuari('hr');
        Sanctum::actingAs($t);
        $id = $this->postJson('/api/v1/incidencies', ['tipus' => 'app', 'assumpte' => 'Prova', 'descripcio' => 'Una prova qualsevol.'])->json('id');
        $this->postJson("/api/v1/incidencies/{$id}/estat", ['estat' => 'resolta'])->assertForbidden();

        Sanctum::actingAs($hr);
        $this->assertSame('nova', $this->getJson('/api/v1/incidencies')->json('0.estat_gestio'));
        $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Ho mirem.'])->assertJson(['estat_gestio' => 'en_curs']);
        $this->postJson("/api/v1/incidencies/{$id}/estat", ['estat' => 'esperant'])->assertOk()->assertJson(['estat' => 'oberta']);

        // El treballador contesta: torna a «en curs». No veu l'estat intern.
        Sanctum::actingAs($t);
        $r = $this->postJson("/api/v1/incidencies/{$id}/missatges", ['text' => 'Ja ho he provat.'])->assertOk();
        $this->assertArrayNotHasKey('estat_gestio', $r->json());
        $this->assertArrayNotHasKey('estat_gestio', $this->getJson('/api/v1/incidencies/meves')->json('0'));

        Sanctum::actingAs($hr);
        $this->assertSame('en_curs', $this->getJson('/api/v1/incidencies')->json('0.estat_gestio'));
        $this->postJson("/api/v1/incidencies/{$id}/estat", ['estat' => 'descartada'])->assertJson(['estat' => 'resolta']);
        $this->postJson("/api/v1/incidencies/{$id}/estat", ['estat' => 'inventat'])->assertStatus(422);

        Sanctum::actingAs($t);
        $this->assertSame('resolta', $this->getJson('/api/v1/incidencies/meves')->json('0.estat'));
    }

    public function test_dades_obligatories(): void
    {
        Sanctum::actingAs($this->usuari('worker'));
        $this->postJson('/api/v1/incidencies', ['tipus' => 'inventat', 'assumpte' => '', 'descripcio' => 'curt'])
            ->assertStatus(422);
    }
}
