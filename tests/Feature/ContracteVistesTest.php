<?php

namespace Tests\Feature;

use App\Models\ComplianceDocument;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryDocument;
use App\Models\DisciplinaryElement;
use App\Models\DisciplinaryFaultType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * CONTRACTE ENTRE LES VISTES VUE I L'API.
 *
 * Les pantalles de Compliment (ComplianceView / WorkerComplianceView) i de Procediment disciplinari
 * (DisciplinaryView) es van tocar sense poder-les obrir: estan darrere del login i no es van
 * verificar visualment. El que SÍ es pot blindar sense navegador és el contracte: cada camp que el
 * `<template>` llegeix ha d'existir a la resposta de l'endpoint que la vista crida.
 *
 * Un camp que desapareix del JSON no peta: es pinta buit. Per això aquests tests no comproven
 * "que respongui 200", sinó que hi siguin ELS CAMPS, un a un, amb el nom exacte que fa servir la
 * vista. Si algú reanomena una columna o retalla un `select`, la pantalla es buidaria en silenci
 * i aquí es veurà abans.
 *
 * ⚠️ Això NO substitueix mirar les pantalles: no cobreix maquetació, ni CSS, ni que un botó
 *    estigui on toca. Cobreix que les dades hi arribin. Vegeu l'informe de la sessió.
 */
class ContracteVistesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les migracions de contingut publiquen textos legals reals. Aquest fitxer fixa els CAMPS que
     * cada vista necessita, no el catàleg de documents: es parteix de taula buida.
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

    /** Comprova que TOTS els camps hi són, i diu quin falta (no "l'estructura no quadra"). */
    private function assertCamps(array $camps, array $fila, string $on): void
    {
        foreach ($camps as $camp) {
            $this->assertArrayHasKey($camp, $fila, "{$on}: la vista llegeix «{$camp}» i l'API no el retorna");
        }
    }

    // ══ ComplianceView.vue (admin/hr) ════════════════════════════════════════

    public function test_el_llistat_de_compliment_porta_el_que_pinta_la_taula(): void
    {
        ComplianceDocument::create([
            'tipus' => 'info_art90', 'titol' => 'Informació art. 90', 'versio' => '1.0',
            'contingut' => 'text', 'estat' => 'publicat', 'requereix_acus' => true, 'published_at' => now(),
        ]);

        Sanctum::actingAs($this->usuari('hr'));
        $fila = $this->getJson('/api/v1/compliance')->assertOk()->json('0');

        // ComplianceView.vue: d.id, d.titol, d.tipus, d.versio, d.estat, d.published_at,
        //                     d.requereix_acus, d.acknowledgements_count
        $this->assertCamps(
            ['id', 'titol', 'tipus', 'versio', 'estat', 'published_at', 'requereix_acus', 'acknowledgements_count'],
            $fila, 'GET /compliance'
        );
    }

    public function test_el_panell_dacusaments_porta_el_que_pinta_la_taula(): void
    {
        $doc = ComplianceDocument::create([
            'tipus' => 'info_art90', 'titol' => 'Informació art. 90', 'versio' => '1.0',
            'contingut' => 'text', 'estat' => 'publicat', 'requereix_acus' => true, 'published_at' => now(),
        ]);
        $this->usuari('worker');

        Sanctum::actingAs($this->usuari('hr'));
        $fila = $this->getJson("/api/v1/compliance/{$doc->id}/status")->assertOk()->json('0');

        // ComplianceView.vue: r.user_id, r.name, r.role, r.active, r.acknowledged_at
        $this->assertCamps(['user_id', 'name', 'role', 'active', 'acknowledged_at'], $fila, 'GET /compliance/{id}/status');
    }

    public function test_el_llistat_de_compliment_es_de_gestio(): void
    {
        Sanctum::actingAs($this->usuari('worker'));
        $this->getJson('/api/v1/compliance')->assertStatus(403);
    }

    // ══ WorkerComplianceView.vue (treballador) ═══════════════════════════════

    public function test_els_pendents_del_treballador_porten_el_text_a_llegir(): void
    {
        ComplianceDocument::create([
            'tipus' => 'info_art90', 'titol' => 'Informació art. 90', 'versio' => '1.0',
            'contingut' => '# text que ha de llegir', 'base_legal' => 'LOPDGDD art. 90',
            'estat' => 'publicat', 'requereix_acus' => true, 'published_at' => now(),
        ]);

        Sanctum::actingAs($this->usuari('worker'));
        $fila = $this->getJson('/api/v1/compliance/pending')->assertOk()->json('0');

        // WorkerComplianceView.vue: d.id, d.titol, d.versio, d.contingut, d.base_legal
        $this->assertCamps(['id', 'titol', 'versio', 'contingut', 'base_legal'], $fila, 'GET /compliance/pending');
        $this->assertSame('# text que ha de llegir', $fila['contingut'],
            'sense contingut, la pantalla demana acusar recepció d\'un document en blanc');
    }

    public function test_les_notificacions_disciplinaries_del_treballador_porten_el_seu_estat(): void
    {
        $u = $this->usuari('worker');
        DisciplinaryDocument::create([
            'professional' => 'fisio.prova', 'user_id' => $u->id, 'tipus' => 'amonestacio',
            'contingut' => 'escrit', 'estat' => 'notificat', 'signat_per' => 'Direcció',
            'signat_ts' => now(), 'notificat_ts' => now(),
        ]);

        Sanctum::actingAs($u);
        $fila = $this->getJson('/api/v1/disciplinary/my-notifications')->assertOk()->json('0');

        // WorkerComplianceView.vue: n.id, n.tipus, n.contingut, n.acus_ts
        $this->assertCamps(['id', 'tipus', 'contingut', 'acus_ts'], $fila, 'GET /disciplinary/my-notifications');
        $this->assertNull($fila['acus_ts'], 'sense acus_ts la vista no sabria si ha d\'oferir el botó d\'acusar');
    }

    // ══ DisciplinaryView.vue (admin/hr) ══════════════════════════════════════

    private function faltaICas(): DisciplinaryCase
    {
        DisciplinaryFaultType::create([
            'clau' => 'puntualitat', 'descripcio' => 'Puntualitat', 'grau_base' => 'lleu',
            'base_conveni' => 'art.64', 'prescripcio_dies' => 10,
            'reincidencia_puja' => false, 'llindar_reincidencia' => null,
        ]);
        $u = $this->usuari('worker');

        return DisciplinaryCase::create([
            'professional' => 'fisio.prova', 'user_id' => $u->id, 'vincle' => 'laboral',
            'tipus_falta' => 'puntualitat', 'gravetat' => 'lleu', 'estat' => 'instruccio',
            'data_coneixement' => Carbon::today()->toDateString(),
            'data_prescripcio' => Carbon::today()->addDays(5)->toDateString(),
        ]);
    }

    public function test_el_llistat_de_casos_porta_el_que_pinta_la_taula(): void
    {
        $this->faltaICas();

        Sanctum::actingAs($this->usuari('hr'));
        $fila = $this->getJson('/api/v1/disciplinary/cases')->assertOk()->json('0');

        // DisciplinaryView.vue: c.id, c.professional, c.tipus_falta, c.gravetat, c.estat,
        //                       c.dies_prescripcio (calculat, no és columna de la taula)
        $this->assertCamps(['id', 'professional', 'tipus_falta', 'gravetat', 'estat', 'dies_prescripcio'],
            $fila, 'GET /disciplinary/cases');
        $this->assertIsInt($fila['dies_prescripcio']);
    }

    public function test_la_fitxa_del_cas_porta_les_garanties_i_les_transicions(): void
    {
        $cas = $this->faltaICas();

        Sanctum::actingAs($this->usuari('hr'));
        $c = $this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->assertOk()->json();

        // DisciplinaryView.vue llegeix tots aquests camps de la fitxa.
        $this->assertCamps([
            'id', 'professional', 'tipus_falta', 'gravetat', 'estat', 'data_coneixement',
            'data_prescripcio', 'dies_prescripcio', 'es_representant', 'te_evidencia_licita',
            'apercebiment_previ', 'afectacio_servei_acreditada', 'expedient_contradictori',
            'audiencia_rlt_ts', 'garanties', 'reincidencia', 'transicions',
            'documents', 'elements', 'events',
        ], $c, 'GET /disciplinary/cases/{id}');

        // Les transicions són la barra de botons: cada porta amb el seu ok i el seu motiu.
        $this->assertNotEmpty($c['transicions']);
        foreach ($c['transicions'] as $t) {
            $this->assertCamps(['estat', 'ok', 'motiu'], $t, 'transicions[]');
        }
        $estats = array_column($c['transicions'], 'estat');
        $this->assertSame(['instruccio', 'comunicat', 'alegacions', 'resolt', 'executat', 'arxivat'], $estats,
            'la vista pinta un botó per estat: si en falta un, la porta desapareix de la pantalla');
    }

    public function test_el_boto_de_resoldre_s_ofereix_quan_toca_i_no_abans(): void
    {
        // Regressió de la troballa anterior: 'resolt' sortia SEMPRE bloquejat perquè la guarda
        // s'avaluava sense signant, i el botó final del mòdul era inabastable des de la interfície.
        $cas = $this->faltaICas();
        Sanctum::actingAs($this->usuari('hr'));

        $porta = fn (array $c) => collect($c['transicions'])->firstWhere('estat', 'resolt');

        // Encara en instrucció: no s'ha comunicat res → la porta ha d'estar tancada, AMB motiu.
        $abans = $porta($this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->assertOk()->json());
        $this->assertFalse($abans['ok']);
        $this->assertNotEmpty($abans['motiu'], 'una porta tancada sense motiu deixa la pantalla muda');

        // Un cop comunicat I exhaurit el tràmit d'audiència (aquí, amb al·legacions presentades),
        // la previsualització ha d'oferir-la encara que ningú hagi signat encara. Amb el termini
        // corrent la porta segueix tancada: ho prova DisciplinariMotorTest.
        $cas->update(['estat' => 'comunicat', 'comunicat_ts' => now(), 'alegacions_ts' => now()]);
        $despres = $porta($this->getJson("/api/v1/disciplinary/cases/{$cas->id}")->assertOk()->json());
        $this->assertTrue($despres['ok'],
            'des de «comunicat» el botó de resoldre s\'ha d\'oferir: si no, el mòdul no es pot acabar');

        // I la previsualització NO desa res: el cas segueix sense signant.
        $this->assertNull($cas->fresh()->resolt_per);
    }

    public function test_els_suggeriments_i_el_cataleg_de_faltes_porten_el_que_pinta_la_vista(): void
    {
        DisciplinaryFaultType::create([
            'clau' => 'puntualitat', 'descripcio' => 'Puntualitat', 'grau_base' => 'lleu',
            'base_conveni' => 'conveni XII art. 64', 'prescripcio_dies' => 10,
            'reincidencia_puja' => true, 'llindar_reincidencia' => 2,
        ]);
        $u = $this->usuari('worker');
        foreach ([0, 1] as $i) {
            DisciplinaryElement::create([
                'professional' => 'fisio.prova', 'user_id' => $u->id, 'tipus_falta' => 'puntualitat',
                'data_fet' => Carbon::today()->toDateString(),
                'data_coneixement' => Carbon::today()->toDateString(),
                'imputable' => 'si', 'estat' => 'valorat',
            ]);
        }

        Sanctum::actingAs($this->usuari('hr'));

        $falta = $this->getJson('/api/v1/disciplinary/fault-types')->assertOk()->json('0');
        $this->assertCamps(['clau', 'descripcio', 'grau_base'], $falta, 'GET /disciplinary/fault-types');

        $sug = $this->getJson('/api/v1/disciplinary/suggestions')->assertOk()->json('0');
        $this->assertNotNull($sug, 'amb el llindar de reincidència creuat hi ha d\'haver suggeriment');
        // DisciplinaryView.vue: s.professional, s.tipus_falta, s.motiu, s.base_legal, s.element_ids, s.user_id
        $this->assertCamps(['professional', 'tipus_falta', 'motiu', 'base_legal', 'element_ids', 'user_id'],
            $sug, 'GET /disciplinary/suggestions');
        $this->assertIsArray($sug['element_ids'],
            'la vista els envia a elements/bulk: ha de ser una llista, no un text');
    }

    public function test_el_procediment_disciplinari_es_de_gestio(): void
    {
        Sanctum::actingAs($this->usuari('worker'));
        foreach (['cases', 'fault-types', 'suggestions'] as $ruta) {
            $this->getJson("/api/v1/disciplinary/{$ruta}")->assertStatus(403);
        }
        // Però les seves PRÒPIES notificacions, sí.
        $this->getJson('/api/v1/disciplinary/my-notifications')->assertOk();
    }
}
