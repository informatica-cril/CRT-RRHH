<?php

namespace Tests\Feature;

use App\Models\RendimentAllegacio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * RENDIMENT — la persona mesurada ha de poder VEURE la seva mesura i REBATRE-LA.
 *
 * El panell de Direcció ja afirmava que «la persona afectada té dret a conèixer el detall del seu
 * càlcul i a rebatre'l», però el treballador rebia 403 a /rendiment i no hi havia cap lloc on
 * escriure res. Controlar el rendiment és lícit (art. 20.3 ET); fer-ho sense que l'afectat el pugui
 * conèixer ni contradir, no (art. 13-15 i 21 RGPD).
 *
 * El contracte que es fixa:
 *   - El titular veu les SEVES xifres, i cap companya: ni mitjanes, ni llistes, ni rànquings.
 *   - Un treballador no veu el rendiment d'un altre. Un coordinador tampoc: el panell de Direcció
 *     és d'admin i hr, i la via per persona no pot ser una porta del darrere més ampla.
 *   - Cada xifra va acompanyada de què mesura i d'on surt; sense això, rebatre-la és impossible.
 *   - L'escrit de disconformitat és del TITULAR i de ningú més: ni Direcció ni RRHH poden posar-lo
 *     en nom seu (mateix criteri que l'acusament de recepció disciplinari).
 *   - L'escrit queda amb data, arriba a qui llegeix les xifres i es respon una sola vegada.
 */
class RendimentPropiTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $role = 'worker'): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . uniqid(), 'email' => $role . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);
    }

    private function tancament(User $u, string $desde = '2026-06-01', string $fins = '2026-06-30'): void
    {
        DB::table('domi_rendiment')->insert([
            'user_id' => $u->id, 'periode_desde' => $desde, 'periode_fins' => $fins,
            'sessions_firmades' => 120, 'compliment_pct' => 91, 'documental_pct' => 88,
            'altes_sense_informe' => 1, 'processos_tancats' => 14, 'adherencia_pct' => 83,
            'processos_sota_70pct' => 2, 'puntualitat_pct' => 90, 'retard_mitja_min' => 7,
            'aportacions' => 7, 'prou_mostra' => 1, 'agraiments' => 6, 'queixes' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_el_treballador_no_entra_al_panell_de_direccio(): void
    {
        Sanctum::actingAs($this->usuari());
        $this->getJson('/api/v1/rendiment')->assertStatus(403);
    }

    public function test_el_titular_veu_les_seves_xifres_amb_la_seva_explicacio(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($u);

        $r = $this->getJson("/api/v1/rendiment/persona/{$u->id}")->assertOk();

        $this->assertSame(91, $r->json('fila.compliment_pct'));
        $this->assertSame(7, $r->json('fila.retard_mitja_min'));
        // Cada indicador porta què mesura i d'on surt: una xifra sola no es pot rebatre.
        foreach ($r->json('indicadors') as $clau => $i) {
            $this->assertNotEmpty($i['mesura'], "L'indicador {$clau} no diu què mesura.");
            $this->assertNotEmpty($i['origen'], "L'indicador {$clau} no diu d'on surt la dada.");
        }
    }

    public function test_la_pantalla_propia_no_porta_companys_ni_mitjana(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($u);

        $r = $this->getJson("/api/v1/rendiment/persona/{$u->id}")->assertOk();

        $this->assertArrayNotHasKey('files', $r->json());
        $this->assertArrayNotHasKey('mitjana', $r->json());
    }

    public function test_un_treballador_no_veu_el_rendiment_d_un_company(): void
    {
        $a = $this->usuari();
        $b = $this->usuari();
        $this->tancament($b);
        Sanctum::actingAs($a);

        $this->getJson("/api/v1/rendiment/persona/{$b->id}")->assertStatus(403);
    }

    public function test_coordinacio_no_entra_per_la_via_per_persona(): void
    {
        // El panell de Direcció és 'role:admin,hr'. Si 'owner' (que deixa passar tot l'staff) fos
        // l'única porta, Coordinació veuria per aquí el que no veu pel panell.
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($this->usuari('coordinator'));

        $this->getJson("/api/v1/rendiment/persona/{$u->id}")->assertStatus(403);
    }

    public function test_direccio_si_pot_veure_el_rendiment_d_una_persona(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($this->usuari('admin'));

        $this->getJson("/api/v1/rendiment/persona/{$u->id}")->assertOk()->assertJsonPath('fila.compliment_pct', 91);
    }

    public function test_l_escrit_de_disconformitat_es_desa_amb_data(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($u);

        $this->postJson("/api/v1/rendiment/persona/{$u->id}/allegacio", [
            'periode_desde' => '2026-06-01', 'periode_fins' => '2026-06-30',
            'indicador' => 'retard_mitja_min',
            'text' => 'El retard de dos visites va ser per un ascensor avariat; consta la incidencia a Coordinacio.',
        ])->assertCreated();

        $a = RendimentAllegacio::first();
        $this->assertSame($u->id, $a->user_id);
        $this->assertNotNull($a->presentada_ts);
        $this->assertNull($a->resposta_ts);
    }

    public function test_ningu_pot_rebatre_en_nom_d_un_altre(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        $cos = ['periode_desde' => '2026-06-01', 'periode_fins' => '2026-06-30',
            'indicador' => 'puntualitat_pct', 'text' => 'el descarrec no el pot escriure un tercer mai'];

        Sanctum::actingAs($this->usuari());
        $this->postJson("/api/v1/rendiment/persona/{$u->id}/allegacio", $cos)->assertStatus(403);

        Sanctum::actingAs($this->usuari('admin'));
        $this->postJson("/api/v1/rendiment/persona/{$u->id}/allegacio", $cos)->assertStatus(403);

        $this->assertSame(0, RendimentAllegacio::count());
    }

    public function test_no_es_rebat_un_periode_sense_tancament(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($u);

        $this->postJson("/api/v1/rendiment/persona/{$u->id}/allegacio", [
            'periode_desde' => '2021-01-01', 'periode_fins' => '2021-01-31',
            'indicador' => 'puntualitat_pct', 'text' => 'periode que no consta en cap tancament rebut',
        ])->assertStatus(422);
    }

    public function test_l_escrit_arriba_a_qui_llegeix_les_xifres_i_es_respon_un_sol_cop(): void
    {
        $u = $this->usuari();
        $this->tancament($u);
        Sanctum::actingAs($u);
        $this->postJson("/api/v1/rendiment/persona/{$u->id}/allegacio", [
            'periode_desde' => '2026-06-01', 'periode_fins' => '2026-06-30',
            'indicador' => 'retard_mitja_min', 'text' => 'incidencia acreditada al carrer Girona els dies 12 i 19',
        ])->assertCreated();
        $id = RendimentAllegacio::first()->id;

        // Un company no pot contestar la disconformitat d'un altre.
        Sanctum::actingAs($this->usuari());
        $this->postJson("/api/v1/rendiment/allegacions/{$id}/resposta",
            ['resposta' => 'un treballador no pot resoldre la disconformitat aliena'])->assertStatus(403);

        $direccio = $this->usuari('admin');
        Sanctum::actingAs($direccio);
        // Apareix al panell que mostra les xifres discutides.
        $this->getJson('/api/v1/rendiment')->assertOk()->assertJsonPath('allegacions_pendents.0.id', $id);

        $this->postJson("/api/v1/rendiment/allegacions/{$id}/resposta",
            ['resposta' => 'Comprovada la incidencia amb Coordinacio: el retard queda justificat.'])->assertOk();
        $this->postJson("/api/v1/rendiment/allegacions/{$id}/resposta",
            ['resposta' => 'intent de sobreescriure la posicio ja donada per l empresa'])->assertStatus(422);

        $a = RendimentAllegacio::find($id);
        $this->assertSame($direccio->id, $a->respost_per);
        $this->assertNotNull($a->resposta_ts);

        // I el titular la veu.
        Sanctum::actingAs($u);
        $this->getJson("/api/v1/rendiment/persona/{$u->id}")
            ->assertOk()->assertJsonPath('allegacions.0.resposta_ts', $a->resposta_ts->toJSON());
    }

    public function test_cap_resposta_sense_data_ni_data_sense_resposta(): void
    {
        $u = $this->usuari();
        $a = RendimentAllegacio::create([
            'user_id' => $u->id, 'periode_desde' => '2026-06-01', 'periode_fins' => '2026-06-30',
            'indicador' => 'puntualitat_pct', 'text' => 'text de prova prou llarg per passar', 'presentada_ts' => now(),
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $a->update(['resposta' => 'resposta sense data de resposta']);
    }
}
