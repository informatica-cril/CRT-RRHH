<?php

namespace Tests\Feature;

use App\Models\ComplianceAcknowledgement;
use App\Models\ComplianceDocument;
use App\Models\DisciplinaryDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * COMPLIMENT — la CONSTÀNCIA que habilita la resta (LOPDGDD art. 90, conveni XII art. 55.K.6).
 *
 * L'acusament de recepció és una PROVA, no un tràmit d'interfície: acredita que la informació va ser
 * PRÈVIA (data), de qui (usuari), de quina versió (snapshot) i des d'on (IP). Si un tercer pot posar
 * l'acusament, la prova no val res i contamina tot el que hi penja (el motor disciplinari inclòs).
 *
 * El contracte que es fixa:
 *   - Només un document PUBLICAT i marcat 'requereix_acus' es pot acusar; un esborrany, no.
 *   - L'acusament desa data, versió i IP, i és únic per (document, usuari).
 *   - L'acusament és de qui el fa: la identitat surt del token, mai del cos de la petició.
 *   - Un escrit disciplinari només l'acusa el seu DESTINATARI (DisciplinaryDocumentPolicy),
 *     després d'haver estat notificat i una sola vegada. Sense titular: fail-closed per a tothom.
 *
 * Dos forats TAPATS (assercions invertides conscientment): la republicació que movia `published_at`
 * per darrere d'acusaments ja donats, i el panell de seguiment que només mirava els 'worker' —
 * amb coordinadors, `hr` i admin fora de la llista, Direcció no podia acreditar el compliment de
 * mitja plantilla. No queda cap test amb prefix `test_buit_` en aquest fitxer.
 */
class ComplimentAcusamentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les migracions de contingut publiquen textos legals reals (p. ex. la informació de l'art. 90
     * amb el rendiment assistencial). Aquests casos mesuren el MECANISME de pendents i acusaments,
     * no el catàleg: es parteix de taula buida perquè cada assercio parli només del que crea.
     */
    protected function setUp(): void
    {
        parent::setUp();
        ComplianceDocument::query()->delete();
    }

    private function usuari(string $role = 'worker'): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . uniqid(), 'email' => $role . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);
    }

    private function document(array $extra = []): ComplianceDocument
    {
        return ComplianceDocument::create(array_merge([
            'tipus'          => 'info_art90',
            'titol'          => 'Informació prèvia sobre el registre horari i la geolocalització',
            'versio'         => '1.0',
            'contingut'      => '# Informació art. 90 LOPDGDD',
            'base_legal'     => 'LOPDGDD art. 90',
            'estat'          => 'publicat',
            'requereix_acus' => true,
            'published_at'   => now(),
        ], $extra));
    }

    // ── Publicació i exigència d'acusament ────────────────────────────────────

    public function test_un_document_publicat_amb_acus_apareix_com_a_pendent(): void
    {
        $doc = $this->document();
        Sanctum::actingAs($this->usuari());

        $pendents = $this->getJson('/api/v1/compliance/pending')->assertOk()->json();
        $this->assertSame([$doc->id], array_column($pendents, 'id'));
    }

    public function test_ni_l_esborrany_ni_l_arxivat_ni_el_que_no_exigeix_acus_son_pendents(): void
    {
        $this->document(['estat' => 'esborrany', 'published_at' => null]);
        $this->document(['estat' => 'arxivat']);
        $this->document(['requereix_acus' => false]);

        Sanctum::actingAs($this->usuari());
        $this->assertSame([], $this->getJson('/api/v1/compliance/pending')->assertOk()->json());
    }

    public function test_un_esborrany_no_es_pot_acusar(): void
    {
        $doc = $this->document(['estat' => 'esborrany', 'published_at' => null]);
        Sanctum::actingAs($this->usuari());

        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(422);
        $this->assertSame(0, ComplianceAcknowledgement::count(),
            'no pot existir constància d\'un document que encara no s\'ha publicat');
    }

    public function test_un_document_que_no_exigeix_acus_no_es_pot_acusar(): void
    {
        $doc = $this->document(['requereix_acus' => false]);
        Sanctum::actingAs($this->usuari());

        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(422);
        $this->assertSame(0, ComplianceAcknowledgement::count());
    }

    // ── L'acusament com a PROVA: data, versió i IP ────────────────────────────

    public function test_l_acusament_desa_data_versio_i_ip(): void
    {
        $doc = $this->document(['versio' => '2.3']);
        $u = $this->usuari();
        Sanctum::actingAs($u);

        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(201);

        $ack = ComplianceAcknowledgement::where('user_id', $u->id)->firstOrFail();
        $this->assertSame($doc->id, $ack->compliance_document_id);
        $this->assertNotNull($ack->acknowledged_at, 'sense data, l\'acusament no prova res');
        $this->assertNotNull($ack->ip, 'la IP forma part de la prova de recepció');
        $this->assertSame('127.0.0.1', $ack->ip);
        $this->assertSame('2.3', $ack->document_versio,
            'la versió acusada és un snapshot: ha de quedar congelada a l\'acusament');
    }

    public function test_un_cop_acusat_desapareix_de_pendents_i_no_es_duplica(): void
    {
        $doc = $this->document();
        $u = $this->usuari();
        Sanctum::actingAs($u);

        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(201);
        $this->assertSame([], $this->getJson('/api/v1/compliance/pending')->assertOk()->json());

        // Segon intent: idempotent, mai una segona constància de la mateixa persona.
        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge");
        $this->assertSame(1, ComplianceAcknowledgement::where('compliance_document_id', $doc->id)->count());
    }

    public function test_l_acusament_es_sempre_de_qui_el_fa_mai_del_cos_de_la_peticio(): void
    {
        $doc = $this->document();
        $victima = $this->usuari();
        $qui_acusa = $this->usuari();
        Sanctum::actingAs($qui_acusa);

        // Intent d'acusar EN NOM d'un altre passant-ne l'id pel cos.
        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge", ['user_id' => $victima->id])
            ->assertStatus(201);

        $this->assertDatabaseMissing('compliance_acknowledgements', ['user_id' => $victima->id]);
        $this->assertDatabaseHas('compliance_acknowledgements', ['user_id' => $qui_acusa->id]);
    }

    public function test_l_estat_dacusaments_distingeix_qui_ha_acusat_i_qui_no(): void
    {
        $doc = $this->document();
        $ha_acusat = $this->usuari();
        $no_ha_acusat = $this->usuari();

        Sanctum::actingAs($ha_acusat);
        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(201);

        Sanctum::actingAs($this->usuari('hr'));
        $estat = collect($this->getJson("/api/v1/compliance/{$doc->id}/status")->assertOk()->json())
            ->keyBy('user_id');

        $this->assertNotNull($estat[$ha_acusat->id]['acknowledged_at']);
        $this->assertNull($estat[$no_ha_acusat->id]['acknowledged_at'],
            'qui no ha acusat ha de sortir explícitament com a pendent, no desaparèixer');
    }

    // ── Immutabilitat del document publicat ───────────────────────────────────

    public function test_un_document_publicat_no_s_edita(): void
    {
        $doc = $this->document(['contingut' => 'original']);
        Sanctum::actingAs($this->usuari('hr'));

        $this->putJson("/api/v1/compliance/{$doc->id}", ['contingut' => 'reescrit'])
            ->assertStatus(422);
        $this->assertSame('original', $doc->fresh()->contingut,
            'si el text acusat pogués canviar, l\'acusament no acreditaria res');
    }

    public function test_un_document_arxivat_no_es_pot_tornar_a_publicar(): void
    {
        $doc = $this->document(['estat' => 'arxivat']);
        Sanctum::actingAs($this->usuari('hr'));

        $this->postJson("/api/v1/compliance/{$doc->id}/publish")->assertStatus(422);
        $this->assertSame('arxivat', $doc->fresh()->estat);
    }

    // ── ESCRITS DISCIPLINARIS: acusar és un acte personal del destinatari ─────

    private function escrit(?User $destinatari, array $extra = []): DisciplinaryDocument
    {
        return DisciplinaryDocument::create(array_merge([
            'professional' => $destinatari?->name ?? 'fisio.prova',
            'user_id'      => $destinatari?->id,
            'tipus'        => 'amonestacio',
            'contingut'    => 'text de l\'escrit',
            'estat'        => 'notificat',
            'notificat_ts' => now(),
        ], $extra));
    }

    public function test_nomes_el_destinatari_pot_acusar_el_seu_escrit(): void
    {
        $destinatari = $this->usuari();
        $company = $this->usuari();
        $doc = $this->escrit($destinatari);

        Sanctum::actingAs($company);
        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertStatus(403);
        $this->assertNull($doc->fresh()->acus_ts, 'un company no acredita la notificació de ningú');

        Sanctum::actingAs($destinatari);
        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertOk();
        $this->assertNotNull($doc->fresh()->acus_ts);
    }

    public function test_la_policy_es_fail_closed_amb_un_escrit_sense_destinatari(): void
    {
        $doc = $this->escrit(null);

        foreach (['admin', 'hr', 'coordinator', 'worker'] as $role) {
            Sanctum::actingAs($this->usuari($role));
            $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")
                ->assertStatus(403);
        }
        $this->assertNull($doc->fresh()->acus_ts,
            'sense titular vinculat ningú pot acusar: fail-closed');
    }

    public function test_no_es_pot_acusar_un_escrit_que_no_s_ha_notificat(): void
    {
        $destinatari = $this->usuari();
        $doc = $this->escrit($destinatari, ['estat' => 'signat', 'notificat_ts' => null]);

        Sanctum::actingAs($destinatari);
        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertStatus(422);
        $this->assertNull($doc->fresh()->acus_ts);
    }

    public function test_no_es_pot_acusar_dues_vegades_el_mateix_escrit(): void
    {
        $destinatari = $this->usuari();
        $doc = $this->escrit($destinatari);

        Sanctum::actingAs($destinatari);
        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertOk();
        $primer = $doc->fresh()->acus_ts;

        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertStatus(422);
        $this->assertEquals($primer, $doc->fresh()->acus_ts,
            'la data del primer acusament no es pot moure amb un segon clic');
    }

    public function test_l_acusament_de_l_escrit_desa_data_i_ip_i_queda_a_l_historial(): void
    {
        $destinatari = $this->usuari();
        $cas = \App\Models\DisciplinaryCase::create([
            'professional' => 'fisio.prova', 'user_id' => $destinatari->id, 'vincle' => 'laboral',
            'tipus_falta' => 'puntualitat', 'gravetat' => 'lleu', 'estat' => 'comunicat',
        ]);
        $doc = $this->escrit($destinatari, ['case_id' => $cas->id]);

        Sanctum::actingAs($destinatari);
        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertOk();

        $doc = $doc->fresh();
        $this->assertNotNull($doc->acus_ts);
        $this->assertSame('127.0.0.1', $doc->acus_ip);

        // La cadena de custòdia del cas ha de recollir l'acte (és el que després entra al segell).
        $this->assertDatabaseHas('disciplinary_case_events', ['case_id' => $cas->id, 'document_id' => $doc->id]);
    }

    public function test_el_treballador_nomes_veu_els_escrits_notificats_a_ell(): void
    {
        $meu = $this->usuari();
        $altre = $this->usuari();
        $doc_meu = $this->escrit($meu);
        $this->escrit($altre);
        $this->escrit($meu, ['estat' => 'signat', 'notificat_ts' => null]); // encara no notificat

        Sanctum::actingAs($meu);
        $llista = $this->getJson('/api/v1/disciplinary/my-notifications')->assertOk()->json();

        $this->assertSame([$doc_meu->id], array_column($llista, 'id'));
    }

    // ── Cobertura del panell: TOTA la plantilla, no només els worker ──────────

    public function test_el_panell_dacusaments_segueix_totes_les_persones_no_nomes_els_worker(): void
    {
        // TAPAT: acknowledgementsStatus() llistava User::where('role','worker'). Un coordinador, un
        // hr o un admin reben el document a /compliance/pending i el poden acusar, però no sortien
        // mai al panell: el seu compliment era invisible i Direcció no podia acreditar-lo.
        $doc = $this->document();
        $persones = [
            'worker'      => $this->usuari('worker'),
            'coordinator' => $this->usuari('coordinator'),
            'hr'          => $this->usuari('hr'),
            'admin'       => $this->usuari('admin'),
        ];

        foreach ($persones as $rol => $u) {
            Sanctum::actingAs($u);
            $this->assertSame([$doc->id],
                array_column($this->getJson('/api/v1/compliance/pending')->assertOk()->json(), 'id'),
                "un {$rol} SÍ té el document com a pendent");
        }

        Sanctum::actingAs($this->usuari('hr'));
        $files = collect($this->getJson("/api/v1/compliance/{$doc->id}/status")->assertOk()->json())
            ->keyBy('user_id');

        foreach ($persones as $rol => $u) {
            $this->assertArrayHasKey($u->id, $files, "el {$rol} ha de sortir al panell de seguiment");
            $this->assertSame($rol, $files[$u->id]['role'], 'el panell ha de dir de quin rol és cadascú');
            $this->assertNull($files[$u->id]['acknowledged_at']);
        }
    }

    public function test_el_panell_distingeix_qui_ha_acusat_sigui_quin_sigui_el_seu_rol(): void
    {
        $doc = $this->document();
        $admin_que_acusa = $this->usuari('admin');
        $hr_que_no_acusa = $this->usuari('hr');

        Sanctum::actingAs($admin_que_acusa);
        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(201);

        Sanctum::actingAs($this->usuari('admin'));
        $files = collect($this->getJson("/api/v1/compliance/{$doc->id}/status")->assertOk()->json())
            ->keyBy('user_id');

        $this->assertNotNull($files[$admin_que_acusa->id]['acknowledged_at']);
        $this->assertNull($files[$hr_que_no_acusa->id]['acknowledged_at']);
    }

    public function test_el_compte_de_servei_no_surt_al_panell(): void
    {
        // 'service' és el compte amb què domi consumeix l'API: no és una persona i no acusa res.
        // Si sortís, el panell diria per sempre que falta un acusament que ningú pot donar.
        $doc = $this->document();
        $servei = $this->usuari('service');

        Sanctum::actingAs($this->usuari('hr'));
        $ids = array_column($this->getJson("/api/v1/compliance/{$doc->id}/status")->assertOk()->json(), 'user_id');

        $this->assertNotContains($servei->id, $ids);
    }

    public function test_el_panell_marca_els_comptes_desactivats_en_comptes_d_amagar_los(): void
    {
        $doc = $this->document();
        $baixa = $this->usuari('worker');
        $baixa->update(['active' => false]);

        Sanctum::actingAs($this->usuari('hr'));
        $files = collect($this->getJson("/api/v1/compliance/{$doc->id}/status")->assertOk()->json())
            ->keyBy('user_id');

        $this->assertArrayHasKey($baixa->id, $files,
            'una baixa que no va acusar no desapareix del panell: es marca');
        $this->assertFalse($files[$baixa->id]['active']);
    }

    public function test_republicar_un_document_no_mou_la_data_de_publicacio(): void
    {
        // TAPAT (mitjana 4): publish() bloquejava només l'arxivat i un document JA publicat es podia
        // tornar a publicar reescrivint published_at. La data de publicació és la que acredita que la
        // informació va ser PRÈVIA: si es pogués moure endavant, acabaria sent posterior a acusaments
        // ja donats i els invalidaria.
        Carbon::setTestNow('2026-03-01 09:00:00');
        $doc = $this->document(['published_at' => '2026-01-10 08:00:00']);
        $u = $this->usuari();
        Sanctum::actingAs($u);
        $this->postJson("/api/v1/compliance/{$doc->id}/acknowledge")->assertStatus(201);
        $ack = ComplianceAcknowledgement::where('user_id', $u->id)->firstOrFail();

        Carbon::setTestNow('2026-04-01 09:00:00');
        Sanctum::actingAs($this->usuari('hr'));
        $this->postJson("/api/v1/compliance/{$doc->id}/publish")->assertStatus(422);

        $this->assertSame('2026-01-10 08:00:00', $doc->fresh()->published_at->toDateTimeString(),
            'la data de publicació original no es toca');
        $this->assertTrue($doc->fresh()->published_at->lt($ack->acknowledged_at),
            'la informació ha de seguir sent PRÈVIA a l\'acusament');
    }
}
