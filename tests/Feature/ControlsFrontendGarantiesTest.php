<?php

namespace Tests\Feature;

use App\Models\ComplianceDocument;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryDocument;
use App\Models\DisciplinaryFaultType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CONTROLS DEL FRONTEND ↔ GARANTIES DEL BACKEND.
 *
 * Quatre accions que el procediment prohibeix (resoldre sense comunicar, reobrir un cas tancat,
 * republicar un document publicat i obrir expedient laboral a un autònom) responen 422. La vista
 * ha d'AMAGAR O DESACTIVAR el control corresponent i dir-ne el motiu en llenguatge natural: un
 * botó que sempre falla és pitjor que no tenir-lo, perquè qui el prem no sap si ha fet res mal fet.
 *
 * Aquí es prova el CONTRACTE que la vista consumeix per decidir-ho (no hi ha runner de JS al
 * projecte): que l'API digui, per a cada porta, si està oberta i per què no ho està; i que el
 * motiu que arriba sigui llegible per una persona, no un codi. I, en paral·lel, que res d'això
 * hagi relaxat la guarda: la petició prohibida segueix responent 422.
 *
 * Les regles vénen de l'ET i del procediment; el frontend s'hi adapta, no al revés.
 */
class ControlsFrontendGarantiesTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        $u = User::create([
            'name' => 'Responsable RRHH', 'email' => 'g' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true,
        ]);
        Sanctum::actingAs($u);

        return $u;
    }

    private function treballador(array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Treballadora', 'email' => 't' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ], $extra));
    }

    /** Cas amb totes les portes de 'comunicat' obertes i un escrit ja signat. */
    private function casLlestPerComunicar(array $extra = []): DisciplinaryCase
    {
        DisciplinaryFaultType::firstOrCreate(['clau' => 'puntualitat'], [
            'descripcio' => 'Puntualitat', 'grau_base' => 'lleu', 'base_conveni' => 'art.64',
            'prescripcio_dies' => 10, 'reincidencia_puja' => false,
        ]);
        $u = $this->treballador();
        $cas = DisciplinaryCase::create(array_merge([
            'professional' => 'fisio.prova', 'vincle' => 'laboral', 'tipus_falta' => 'puntualitat',
            'gravetat' => 'lleu', 'estat' => 'instruccio', 'user_id' => $u->id,
            'te_evidencia_licita' => true,
            'data_prescripcio' => Carbon::today()->addDays(5)->toDateString(),
        ], $extra));
        DisciplinaryDocument::create([
            'case_id' => $cas->id, 'professional' => $cas->professional, 'user_id' => $u->id,
            'tipus' => 'amonestacio', 'contingut' => 'text', 'estat' => 'signat',
            'signat_per' => 'Direcció', 'signat_ts' => now(),
        ]);

        return $cas->fresh();
    }

    /** La porta que la vista llegeix per decidir si ofereix el botó d'una transició. */
    private function porta(array $transicions, string $estat): array
    {
        $t = collect($transicions)->firstWhere('estat', $estat);
        $this->assertNotNull($t, "l'API ha d'informar de la porta «{$estat}»");

        return $t;
    }

    // ── 1. Resoldre un expedient que no s'ha comunicat ────────────────────────

    public function test_resoldre_esta_tancat_i_explicat_mentre_el_cas_no_s_ha_comunicat(): void
    {
        $this->gestor();
        $cas = $this->casLlestPerComunicar();   // estat: instruccio

        $r = $this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->assertOk()->json();
        $porta = $this->porta($r['transicions'], 'resolt');

        $this->assertFalse($porta['ok'], 'sense comunicació prèvia, el botó de resoldre no s\'ofereix');
        $this->assertNotSame('OK', $porta['motiu']);
        $this->assertStringContainsString('COMUNICACIÓ', $porta['motiu'],
            'el motiu ha de ser llegible per una persona, no un codi d\'error');
        $this->assertStringNotContainsString('422', $porta['motiu']);
    }

    public function test_la_petició_de_resoldre_sense_comunicar_segueix_bloquejada_al_backend(): void
    {
        // El frontend amaga el control, però la garantia NO viu al frontend.
        $this->gestor();
        $cas = $this->casLlestPerComunicar();

        $this->postJson("/api/v1/disciplinary/cases/{$cas->id}/transition", [
            'estat' => 'resolt', 'resolucio_tipus' => 'amonestacio', 'resolucio_motivacio' => 'Motivat.',
        ])->assertStatus(422);

        $this->assertSame('instruccio', $cas->fresh()->estat);
    }

    public function test_resoldre_s_ofereix_quan_el_cas_ja_esta_comunicat(): void
    {
        // REGRESSIÓ: la porta 'resolt' exigeix signant i motivació, i totes dues les aporta la
        // pròpia petició. Avaluada en fred sortia sempre bloquejada i el botó no s'oferia MAI,
        // ni tan sols des de 'comunicat': la resolució era inabastable des de la vista.
        $this->gestor();
        // Amb el tràmit d'audiència ja exhaurit (el treballador ha al·legat): la porta de resoldre
        // s'ha d'oferir. Mentre el termini corre, la porta segueix tancada — ho prova
        // DisciplinariMotorTest.
        $cas = $this->casLlestPerComunicar([
            'estat' => 'comunicat', 'comunicat_ts' => now(), 'alegacions_ts' => now(),
        ]);

        $r = $this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->assertOk()->json();

        $this->assertTrue($this->porta($r['transicions'], 'resolt')['ok'],
            'des de «comunicat» la resolució s\'ha de poder oferir; qui la signa és qui prem');
    }

    public function test_previsualitzar_la_resolucio_no_desa_res_ni_relaxa_la_motivacio(): void
    {
        $this->gestor();
        $cas = $this->casLlestPerComunicar(['estat' => 'comunicat', 'comunicat_ts' => now()]);

        $this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->assertOk();
        $this->assertNull($cas->fresh()->resolt_per,
            'la previsualització de la porta no pot deixar cap signant escrit a la BD');

        // Sense motivació la resolució segueix rebutjada, encara que la porta s'ofereixi.
        $this->postJson("/api/v1/disciplinary/cases/{$cas->id}/transition", [
            'estat' => 'resolt', 'resolucio_tipus' => 'amonestacio', 'resolucio_motivacio' => '   ',
        ])->assertStatus(422);
        $this->assertSame('comunicat', $cas->fresh()->estat);
    }

    public function test_una_porta_que_exigeix_audiencia_a_la_rlt_ho_diu(): void
    {
        // La previsualització no pot obrir portes que no toquen: 'molt_greu' sense audiència RLT
        // segueix bloquejat, i amb el motiu explicat.
        $this->gestor();
        $cas = $this->casLlestPerComunicar([
            'estat' => 'comunicat', 'comunicat_ts' => now(), 'gravetat' => 'molt_greu',
            'alegacions_ts' => now(),   // audiència ja exhaurida: la porta que ha de cantar és la de la RLT
        ]);

        $porta = $this->porta(
            $this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->json('transicions'), 'resolt'
        );
        $this->assertFalse($porta['ok']);
        $this->assertStringContainsString('RLT', $porta['motiu']);
    }

    // ── 2. Reobrir un cas ja resolt, executat o arxivat ───────────────────────

    /** @dataProvider estatsTancats */
    public function test_un_cas_tancat_no_ofereix_cap_porta_de_retorn_i_diu_per_que(string $tancat): void
    {
        $this->gestor();
        $cas = $this->casLlestPerComunicar(['estat' => $tancat]);

        $transicions = $this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->json('transicions');

        foreach (['instruccio', 'comunicat', 'alegacions'] as $enrere) {
            $porta = $this->porta($transicions, $enrere);
            $this->assertFalse($porta['ok'], "des de «{$tancat}» no es pot tornar a «{$enrere}»");
            $this->assertStringContainsString('TANCAT', $porta['motiu']);
            $this->assertStringContainsString('immutable', $porta['motiu']);
        }

        // I la petició directa segueix rebutjada: el control amagat no és la garantia.
        $this->postJson("/api/v1/disciplinary/cases/{$cas->id}/transition", ['estat' => 'instruccio'])
            ->assertStatus(422);
        $this->assertSame($tancat, $cas->fresh()->estat);
    }

    public static function estatsTancats(): array
    {
        return [['resolt'], ['executat'], ['arxivat']];
    }

    // ── 3. Republicar un document de compliment ja publicat ───────────────────

    public function test_el_llistat_de_compliment_diu_si_un_document_ja_esta_publicat(): void
    {
        // La vista amaga el botó quan estat=='publicat' i hi ha published_at: si l'API deixés de
        // servir aquests dos camps, el botó tornaria a aparèixer i tornaria a fallar sempre.
        $this->gestor();
        ComplianceDocument::create([
            'tipus' => 'info_art90', 'titol' => 'Informació art. 90', 'versio' => '1.0',
            'contingut' => 'text', 'estat' => 'publicat', 'requereix_acus' => true,
            'published_at' => now()->subDay(),
        ]);

        $fila = $this->getJson('/api/v1/compliance')->assertOk()->json()[0];

        $this->assertSame('publicat', $fila['estat']);
        $this->assertNotNull($fila['published_at'] ?? null,
            'sense published_at la vista no pot saber que ja s\'ha publicat');
    }

    public function test_republicar_un_document_publicat_segueix_bloquejat_amb_motiu_llegible(): void
    {
        $this->gestor();
        $doc = ComplianceDocument::create([
            'tipus' => 'ropa', 'titol' => 'RoPA', 'versio' => '1.0',
            'contingut' => 'text', 'estat' => 'esborrany', 'requereix_acus' => false,
        ]);

        $this->postJson("/api/v1/compliance/{$doc->id}/publish")->assertOk();

        $r = $this->postJson("/api/v1/compliance/{$doc->id}/publish")->assertStatus(422);
        $this->assertStringContainsString('ja publicat', mb_strtolower((string) $r->json('message')),
            'el 422 ha de portar un motiu que la vista pugui ensenyar tal qual');
    }

    // ── 4. Obrir expedient laboral a qui consta com a autònom ─────────────────

    public function test_el_llistat_d_usuaris_diu_qui_es_autonom(): void
    {
        // És l'únic senyal que té la vista per desactivar «Obrir cas» abans de trucar al servidor.
        $this->gestor();
        $autonom = $this->treballador(['relacio' => 'autonom']);

        $files = collect($this->getJson('/api/v1/users')->assertOk()->json());
        $fila  = $files->firstWhere('id', $autonom->id);

        $this->assertNotNull($fila);
        $this->assertSame('autonom', $fila['relacio'] ?? null,
            'sense «relacio» al llistat, la vista no pot saber que la via laboral està vetada');
    }

    public function test_obrir_expedient_laboral_a_un_autonom_segueix_bloquejat_amb_motiu_llegible(): void
    {
        $this->gestor();
        DisciplinaryFaultType::firstOrCreate(['clau' => 'puntualitat'], [
            'descripcio' => 'Puntualitat', 'grau_base' => 'lleu', 'base_conveni' => 'art.64',
            'prescripcio_dies' => 10, 'reincidencia_puja' => false,
        ]);
        $autonom = $this->treballador(['relacio' => 'autonom']);

        // Encara marcant 'laboral' al formulari: mana la fitxa de RRHH.
        $r = $this->postJson('/api/v1/disciplinary/cases', [
            'professional' => 'fisio.autonom', 'user_id' => $autonom->id, 'vincle' => 'laboral',
            'tipus_falta' => 'puntualitat', 'data_coneixement' => Carbon::today()->toDateString(),
        ])->assertStatus(422);

        $motiu = (string) $r->json('message');
        $this->assertStringContainsString('mercantil', mb_strtolower($motiu));
        $this->assertSame(0, DisciplinaryCase::count());
    }
}
