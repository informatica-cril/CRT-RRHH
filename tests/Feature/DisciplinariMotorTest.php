<?php

namespace Tests\Feature;

use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryDocument;
use App\Models\DisciplinaryElement;
use App\Models\DisciplinaryFaultType;
use App\Models\User;
use App\Services\DisciplinaryEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * MOTOR DISCIPLINARI — les GARANTIES com a porta (DisciplinaryEngine).
 *
 * Un error aquí té conseqüències laborals reals: una sanció imposada sobre una falta prescrita, sense
 * evidència lícita o sense audiència és nul·la, i el cost no el paga el codi. Per això aquí no es
 * prova el "camí feliç": es proven els LÍMITS (el dia abans i el dia després) i cada porta que el
 * motor tanca.
 *
 * El contracte que es fixa:
 *   - Prescripció ET 60.2: lleu i menys greu 10 dies, greu 20, molt greu 60, comptats des del
 *     CONEIXEMENT, amb topall dur de 6 mesos des de la COMISSIÓ del fet.
 *   - El dia de la prescripció encara es pot actuar; l'endemà, no.
 *   - Reincidència: només sumen els elements IMPUTABLES ('si') i no descartats.
 *   - Cap transició a 'comunicat' sense evidència lícita, sense treballador vinculat, sense un escrit
 *     SIGNAT i amb la falta viva. Cap 'resolt' sense persona que signi, sense audiència a la RLT si
 *     és molt greu, ni sense expedient contradictori si és representant.
 *   - Vincle mercantil (autònom): via disciplinària laboral vetada SEMPRE.
 *   - Els suggeriments d'obertura són PROPOSTES i només neixen en creuar el llindar del catàleg o
 *     quan un element està a punt de prescriure.
 *
 * Els quatre buits que aquesta suite documentava (resoldre sense comunicar, retrocedir des d'un cas
 * tancat, perdre el topall de 6 mesos en requalificar i obrir expedient laboral a un autònom) ja
 * estan TAPATS: les assercions s'han invertit i ara exigeixen la garantia, no la descriuen.
 */
class DisciplinariMotorTest extends TestCase
{
    use RefreshDatabase;

    private DisciplinaryEngine $motor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->motor = app(DisciplinaryEngine::class);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function falta(string $clau, string $grau, ?int $llindar = null): DisciplinaryFaultType
    {
        return DisciplinaryFaultType::create([
            'clau' => $clau, 'descripcio' => "Falta {$clau}", 'grau_base' => $grau,
            'base_conveni' => 'art.64', 'prescripcio_dies' => 10,
            'reincidencia_puja' => $llindar !== null, 'llindar_reincidencia' => $llindar,
        ]);
    }

    private function treballador(): User
    {
        return User::create([
            'name' => 'Treballador Disciplinari', 'email' => 'd' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);
    }

    private function element(string $professional, array $extra = []): DisciplinaryElement
    {
        return DisciplinaryElement::create(array_merge([
            'professional'     => $professional,
            'tipus_falta'      => 'puntualitat',
            // Coneixement d'avui: lluny de prescriure, perquè els tests de reincidència no arrosseguin
            // els suggeriments de prescripció imminent.
            'data_fet'         => Carbon::today()->toDateString(),
            'data_coneixement' => Carbon::today()->toDateString(),
            'imputable'        => 'si',
            'estat'            => 'valorat',
        ], $extra));
    }

    private function cas(array $extra = []): DisciplinaryCase
    {
        return DisciplinaryCase::create(array_merge([
            'professional' => 'fisio.prova',
            'vincle'       => 'laboral',
            'tipus_falta'  => 'puntualitat',
            'gravetat'     => 'lleu',
            'estat'        => 'instruccio',
        ], $extra));
    }

    /** Cas amb TOTES les portes de 'comunicat' obertes (per tancar-ne una i comprovar què passa). */
    private function casLlestPerComunicar(array $extra = []): DisciplinaryCase
    {
        $u = $this->treballador();
        $cas = $this->cas(array_merge([
            'user_id'             => $u->id,
            'te_evidencia_licita' => true,
            'data_prescripcio'    => Carbon::today()->addDays(5)->toDateString(),
        ], $extra));
        DisciplinaryDocument::create([
            'case_id' => $cas->id, 'professional' => $cas->professional, 'user_id' => $u->id,
            'tipus' => 'amonestacio', 'contingut' => 'text', 'estat' => 'signat',
            'signat_per' => 'Direcció', 'signat_ts' => now(),
        ]);

        return $cas->fresh();
    }

    // ── PRESCRIPCIÓ (ET art. 60.2) ────────────────────────────────────────────

    public function test_prescripcio_termini_legal_per_grau(): void
    {
        $coneixement = '2026-03-01';

        // ET 60.2: lleus 10 dies, greus 20, molt greus 60. El conveni tracta la menys greu com lleu.
        $this->assertSame('2026-03-11', $this->motor->prescripcio('lleu', $coneixement));
        $this->assertSame('2026-03-11', $this->motor->prescripcio('menys_greu', $coneixement));
        $this->assertSame('2026-03-21', $this->motor->prescripcio('greu', $coneixement));
        $this->assertSame('2026-04-30', $this->motor->prescripcio('molt_greu', $coneixement));
    }

    public function test_prescripcio_topall_dur_de_6_mesos_des_del_fet(): void
    {
        // Fet antic descobert tard: el termini per coneixement (60 dies → 19/08) va MÉS ENLLÀ del
        // topall dels 6 mesos des de la comissió (10/07). Mana el topall: abans, mai després.
        $this->assertSame(
            '2026-07-10',
            $this->motor->prescripcio('molt_greu', '2026-06-20', '2026-01-10'),
            'el topall de 6 mesos des del fet no es pot allargar pel coneixement tardà'
        );

        // Fet recent: el topall dels 6 mesos queda lluny i mana el termini per coneixement.
        $this->assertSame(
            '2026-06-30',
            $this->motor->prescripcio('lleu', '2026-06-20', '2026-06-19'),
            'amb el fet recent mana el termini curt des del coneixement'
        );
    }

    public function test_el_cataleg_no_pot_contradir_el_termini_legal(): void
    {
        // El termini el fixa l'ET 60.2 pel GRAU, no el formulari: prescripcio_dies del catàleg és un
        // mirall derivat i es recalcula en desar. Aquí s'intenta escurçar el d'una falta molt greu.
        $ft = DisciplinaryFaultType::create([
            'clau' => 'assetjament', 'descripcio' => 'Assetjament', 'grau_base' => 'molt_greu',
            'base_conveni' => 'art.67', 'prescripcio_dies' => 3,
            'reincidencia_puja' => false, 'llindar_reincidencia' => null,
        ]);

        $this->assertSame(60, (int) $ft->fresh()->prescripcio_dies,
            'un termini de l\'ET no es pot escurçar des del catàleg');
        $this->assertSame(60, $this->motor->diesPrescripcio('molt_greu'));
        $this->assertNull($this->motor->diesPrescripcio('gravissim'));
    }

    public function test_prescripcio_es_nula_sense_coneixement_o_amb_grau_desconegut(): void
    {
        $this->assertNull($this->motor->prescripcio('lleu', null));
        $this->assertNull($this->motor->prescripcio('gravissim', '2026-03-01'),
            'un grau fora del catàleg legal no pot generar cap termini inventat');
    }

    public function test_dies_restants_els_limits_no_el_mig(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');

        $this->assertSame(1, $this->motor->diesRestants('2026-05-21'), 'el dia abans: encara viva');
        $this->assertSame(0, $this->motor->diesRestants('2026-05-20'), 'el mateix dia: encara viva');
        $this->assertSame(-1, $this->motor->diesRestants('2026-05-19'), 'el dia després: prescrita');
        $this->assertNull($this->motor->diesRestants(null));
    }

    public function test_el_dia_de_la_prescripcio_encara_es_comunica_i_l_endema_ja_no(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');

        $viva = $this->casLlestPerComunicar(['data_prescripcio' => '2026-05-20']);
        [$ok] = $this->motor->potTransicio($viva, 'comunicat');
        $this->assertTrue($ok, 'el mateix dia de la prescripció el termini encara no ha vençut');

        $morta = $this->casLlestPerComunicar(['data_prescripcio' => '2026-05-19']);
        [$ok, $motiu] = $this->motor->potTransicio($morta, 'comunicat');
        $this->assertFalse($ok);
        $this->assertStringContainsString('PRESCRITA', $motiu);
    }

    // ── REINCIDÈNCIA ──────────────────────────────────────────────────────────

    public function test_reincidencia_al_llindar_i_un_abans(): void
    {
        $this->falta('puntualitat', 'lleu', 3);

        $this->element('fisio.prova');
        $this->element('fisio.prova');
        $r = $this->motor->reincidencia('fisio.prova', 'puntualitat');
        $this->assertFalse($r['acreditada'], 'amb llindar-1 fets no hi ha reincidència acreditada');
        $this->assertSame(2, $r['n']);

        $this->element('fisio.prova');
        $r = $this->motor->reincidencia('fisio.prova', 'puntualitat');
        $this->assertTrue($r['acreditada'], 'exactament al llindar ja s\'acredita');
        $this->assertSame(3, $r['n']);
        $this->assertSame(3, $r['llindar']);
    }

    public function test_reincidencia_nomes_compta_els_fets_imputables(): void
    {
        $this->falta('puntualitat', 'lleu', 2);

        $this->element('fisio.prova', ['imputable' => 'si']);
        $this->element('fisio.prova', ['imputable' => 'no']);
        $this->element('fisio.prova', ['imputable' => 'condicional']);
        $this->element('fisio.prova', ['imputable' => null]);

        $r = $this->motor->reincidencia('fisio.prova', 'puntualitat');
        $this->assertSame(1, $r['n'],
            'no, condicional i sense valorar NO poden sumar per a la reincidència');
        $this->assertFalse($r['acreditada']);
    }

    public function test_reincidencia_no_compta_els_elements_descartats(): void
    {
        $this->falta('puntualitat', 'lleu', 2);

        $this->element('fisio.prova');
        $this->element('fisio.prova', ['estat' => 'descartat']);

        $r = $this->motor->reincidencia('fisio.prova', 'puntualitat');
        $this->assertSame(1, $r['n'], 'un element descartat per una persona no pot ressuscitar');
        $this->assertFalse($r['acreditada']);
    }

    public function test_sense_llindar_al_cataleg_la_reincidencia_mai_s_acredita(): void
    {
        $this->falta('faltar_dia', 'greu', null);
        for ($i = 0; $i < 5; $i++) {
            $this->element('fisio.prova', ['tipus_falta' => 'faltar_dia']);
        }

        $r = $this->motor->reincidencia('fisio.prova', 'faltar_dia');
        $this->assertFalse($r['acreditada']);
        $this->assertSame(0, $r['llindar'], 'sense llindar parametritzat no s\'inventa cap regla');
    }

    public function test_reincidencia_no_barreja_ni_professionals_ni_tipus_de_falta(): void
    {
        $this->falta('puntualitat', 'lleu', 2);
        $this->falta('faltar_dia', 'greu', 2);

        $this->element('fisio.prova', ['tipus_falta' => 'puntualitat']);
        $this->element('fisio.prova', ['tipus_falta' => 'faltar_dia']);
        $this->element('fisio.altre', ['tipus_falta' => 'puntualitat']);

        $this->assertSame(1, $this->motor->reincidencia('fisio.prova', 'puntualitat')['n']);
        $this->assertSame(1, $this->motor->reincidencia('fisio.prova', 'faltar_dia')['n']);
    }

    // ── TRANSICIONS BLOQUEJADES ───────────────────────────────────────────────

    public function test_l_autonom_esta_bloquejat_en_totes_les_transicions(): void
    {
        $cas = $this->casLlestPerComunicar(['vincle' => 'autonom']);

        foreach (['instruccio', 'comunicat', 'alegacions', 'resolt', 'executat', 'arxivat'] as $desti) {
            [$ok, $motiu] = $this->motor->potTransicio($cas, $desti);
            $this->assertFalse($ok, "un vincle mercantil no pot arribar a '{$desti}'");
            $this->assertStringContainsString('mercantil', $motiu);
        }
    }

    public function test_comunicat_exigeix_evidencia_licita(): void
    {
        $cas = $this->casLlestPerComunicar(['te_evidencia_licita' => false]);

        [$ok, $motiu] = $this->motor->potTransicio($cas, 'comunicat');
        $this->assertFalse($ok);
        $this->assertStringContainsString('licitud', $motiu);
    }

    public function test_comunicat_exigeix_treballador_vinculat(): void
    {
        $cas = $this->casLlestPerComunicar(['user_id' => null]);

        [$ok, $motiu] = $this->motor->potTransicio($cas, 'comunicat');
        $this->assertFalse($ok, 'sense destinatari a l\'app no hi ha notificació possible');
        $this->assertStringContainsString('treballador', $motiu);
    }

    public function test_comunicat_exigeix_un_escrit_signat_i_l_esborrany_no_compta(): void
    {
        $u = $this->treballador();
        $cas = $this->cas([
            'user_id' => $u->id, 'te_evidencia_licita' => true,
            'data_prescripcio' => Carbon::today()->addDays(5)->toDateString(),
        ]);

        [$ok, $motiu] = $this->motor->potTransicio($cas, 'comunicat');
        $this->assertFalse($ok, 'sense cap escrit no es pot comunicar res');
        $this->assertStringContainsString('SIGNAT', $motiu);

        // Un ESBORRANY no habilita: la signatura és l'acte humà que dona valor a l'escrit.
        DisciplinaryDocument::create([
            'case_id' => $cas->id, 'professional' => $cas->professional, 'user_id' => $u->id,
            'tipus' => 'amonestacio', 'contingut' => 'text', 'estat' => 'esborrany',
        ]);
        [$ok, $motiu] = $this->motor->potTransicio($cas->fresh(), 'comunicat');
        $this->assertFalse($ok, 'un esborrany sense signar no habilita la comunicació');
        $this->assertStringContainsString('SIGNAT', $motiu);
    }

    public function test_no_es_poden_obrir_alegacions_sense_comunicacio_previa(): void
    {
        $cas = $this->cas(['comunicat_ts' => null]);
        [$ok, $motiu] = $this->motor->potTransicio($cas, 'alegacions');
        $this->assertFalse($ok);
        $this->assertStringContainsString('comunicació', $motiu);

        $cas->comunicat_ts = now();
        [$ok] = $this->motor->potTransicio($cas, 'alegacions');
        $this->assertTrue($ok);
    }

    public function test_cap_resolucio_sense_persona_que_la_signi(): void
    {
        $cas = $this->cas(['estat' => 'alegacions', 'resolt_per' => null, 'alegacions_ts' => now()]);

        [$ok, $motiu] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok, 'RGPD 22: cap sanció automàtica');
        $this->assertStringContainsString('PERSONA', $motiu);

        $cas->resolt_per = 'Direcció RRHH';
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok);
    }

    public function test_molt_greu_exigeix_audiencia_a_la_rlt(): void
    {
        $cas = $this->cas([
            'estat' => 'alegacions', 'gravetat' => 'molt_greu', 'resolt_per' => 'Direcció RRHH',
            'alegacions_ts' => now(),
        ]);

        [$ok, $motiu] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok);
        $this->assertStringContainsString('RLT', $motiu);

        $cas->audiencia_rlt_ts = now();
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok);

        // La porta és exclusiva de les molt greus: una greu no la pot arrossegar.
        $greu = $this->cas(['estat' => 'alegacions', 'gravetat' => 'greu', 'resolt_per' => 'Direcció RRHH',
            'alegacions_ts' => now()]);
        [$ok] = $this->motor->potTransicio($greu, 'resolt');
        $this->assertTrue($ok, 'una falta greu no requereix audiència a la RLT');
    }

    public function test_representant_exigeix_expedient_contradictori(): void
    {
        $cas = $this->cas([
            'estat' => 'alegacions', 'resolt_per' => 'Direcció RRHH', 'alegacions_ts' => now(),
            'es_representant' => true, 'expedient_contradictori' => false,
        ]);

        [$ok, $motiu] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok);
        $this->assertStringContainsString('contradictori', $motiu);

        $cas->expedient_contradictori = true;
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok);
    }

    public function test_nomes_s_executa_una_resolucio_signada(): void
    {
        // Encara en al·legacions: no hi ha res a executar.
        $cas = $this->cas(['estat' => 'alegacions', 'resolucio_tipus' => 'amonestacio']);
        [$ok, $motiu] = $this->motor->potTransicio($cas, 'executat');
        $this->assertFalse($ok);
        $this->assertStringContainsString('resolució signada', $motiu);

        // Resolt però sense tipus de sanció: tampoc.
        $cas = $this->cas(['estat' => 'resolt', 'resolucio_tipus' => null]);
        [$ok] = $this->motor->potTransicio($cas, 'executat');
        $this->assertFalse($ok, 'una resolució sense part dispositiva no s\'executa');

        $cas->resolucio_tipus = 'amonestacio';
        [$ok] = $this->motor->potTransicio($cas, 'executat');
        $this->assertTrue($ok);
    }

    public function test_una_transicio_desconeguda_es_fail_closed(): void
    {
        [$ok, $motiu] = $this->motor->potTransicio($this->cas(), 'sancionat_directament');
        $this->assertFalse($ok, 'davant d\'un estat que no existeix, tancat');
        $this->assertStringContainsString('desconeguda', $motiu);
    }

    public function test_les_garanties_reflecteixen_l_estat_de_cada_porta(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');

        $cas = $this->cas([
            'gravetat' => 'molt_greu', 'vincle' => 'autonom', 'te_evidencia_licita' => false,
            'es_representant' => true, 'data_prescripcio' => '2026-05-19',
        ]);
        $g = collect($this->motor->garanties($cas))->keyBy('clau');

        $this->assertFalse($g['prescripcio']['ok']);
        $this->assertFalse($g['evidencia_licita']['ok']);
        $this->assertFalse($g['vincle']['ok']);
        $this->assertFalse($g['audiencia_rlt']['ok']);
        $this->assertFalse($g['contradictori']['ok']);

        $ok = $this->cas([
            'gravetat' => 'greu', 'te_evidencia_licita' => true,
            'data_prescripcio' => '2026-05-25', 'alegacions_ts' => Carbon::now(),
        ]);
        foreach ($this->motor->garanties($ok) as $porta) {
            $this->assertTrue($porta['ok'], "la porta '{$porta['clau']}' hauria d'estar oberta");
        }
    }

    // ── SUGGERIMENTS D'OBERTURA (proposen; la persona decideix) ───────────────

    public function test_el_suggeriment_de_reincidencia_neix_en_creuar_el_llindar(): void
    {
        $this->falta('puntualitat', 'lleu', 3);

        $this->element('fisio.prova');
        $this->element('fisio.prova');
        $this->assertSame([], $this->motor->suggeriments(),
            'per sota del llindar el motor no proposa res');

        $this->element('fisio.prova');
        $sug = collect($this->motor->suggeriments())->firstWhere('tipus', 'reincidencia');
        $this->assertNotNull($sug, 'en creuar el llindar s\'ha de proposar l\'obertura');
        $this->assertSame('fisio.prova', $sug['professional']);
        $this->assertSame(3, $sug['n']);
        $this->assertSame(3, $sug['llindar']);
        $this->assertCount(3, $sug['element_ids'], 'el suggeriment porta els fets que el fonamenten');
    }

    public function test_el_suggeriment_de_reincidencia_nomes_compta_imputables(): void
    {
        $this->falta('puntualitat', 'lleu', 3);

        $this->element('fisio.prova', ['imputable' => 'si']);
        $this->element('fisio.prova', ['imputable' => 'no']);
        $this->element('fisio.prova', ['imputable' => 'condicional']);

        $this->assertNull(collect($this->motor->suggeriments())->firstWhere('tipus', 'reincidencia'),
            'tres fets, però només un imputable: no hi ha res a proposar');
    }

    public function test_els_elements_ja_inclosos_en_un_cas_no_tornen_a_suggerir(): void
    {
        $this->falta('puntualitat', 'lleu', 2);

        $this->element('fisio.prova', ['estat' => 'inclos_en_cas']);
        $this->element('fisio.prova', ['estat' => 'inclos_en_cas']);

        $this->assertNull(collect($this->motor->suggeriments())->firstWhere('tipus', 'reincidencia'),
            'el que ja està dins d\'un expedient no es proposa una segona vegada');
    }

    public function test_el_suggeriment_de_prescripcio_avisa_a_7_dies_o_menys(): void
    {
        Carbon::setTestNow('2026-05-20 09:00:00');
        $this->falta('puntualitat', 'lleu');   // lleu → 10 dies des del coneixement

        // Coneixement 2026-05-17 → prescriu 2026-05-27 → queden 7 dies: AVISA.
        $al_limit = $this->element('fisio.prova', [
            'data_fet' => '2026-05-17', 'data_coneixement' => '2026-05-17',
        ]);
        $sug = collect($this->motor->suggeriments())->firstWhere('tipus', 'prescripcio');
        $this->assertNotNull($sug);
        $this->assertSame([$al_limit->id], $sug['element_ids']);
        $this->assertSame(7, $sug['dies']);

        // Coneixement 2026-05-18 → prescriu 2026-05-28 → queden 8 dies: encara no avisa.
        DisciplinaryElement::query()->delete();
        $this->element('fisio.prova', ['data_fet' => '2026-05-18', 'data_coneixement' => '2026-05-18']);
        $this->assertNull(collect($this->motor->suggeriments())->firstWhere('tipus', 'prescripcio'),
            'a 8 dies encara no toca avisar');

        // Ja prescrita: tampoc s'avisa (no es pot actuar; l'avís seria fals).
        DisciplinaryElement::query()->delete();
        $this->element('fisio.prova', ['data_fet' => '2026-05-01', 'data_coneixement' => '2026-05-01']);
        $this->assertNull(collect($this->motor->suggeriments())->firstWhere('tipus', 'prescripcio'),
            'una falta ja prescrita no genera un avís d\'actuar');
    }

    // ── GARANTIES TAPADES (abans buits: assercions invertides) ────────────────

    public function test_un_cas_mai_comunicat_no_es_pot_resoldre(): void
    {
        // TAPAT (defecte 1): potTransicio('resolt') exigeix l'estat d'origen 'comunicat' o
        // 'alegacions'. Un expedient en 'esborrany' o en 'instruccio' —sense escrit signat, sense
        // comunicació i sense audiència— ja no arriba a 'resolt' pel sol fet de posar qui signa.
        // Sancionar sense haver escoltat el treballador és indefensió (ET 55.1 i conveni 55.K).
        foreach (['esborrany', 'instruccio'] as $origen) {
            $cas = $this->cas(['estat' => $origen, 'comunicat_ts' => null, 'resolt_per' => 'Direcció']);

            [$ok, $motiu] = $this->motor->potTransicio($cas, 'resolt');
            $this->assertFalse($ok, "des de '{$origen}' no hi ha hagut tràmit d'audiència");
            $this->assertStringContainsString('COMUNICACIÓ', $motiu);
        }

        // Des de 'comunicat' (fets ja traslladats al treballador) i amb l'audiència exhaurida sí
        // que es pot resoldre.
        $cas = $this->cas(['estat' => 'comunicat', 'comunicat_ts' => now(), 'resolt_per' => 'Direcció',
            'alegacions_ts' => now()]);
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok);
    }

    /**
     * TRÀMIT D'AUDIÈNCIA (ET 55.1, conveni 55.K.6): comunicar els fets NO és escoltar el treballador.
     * Mentre el termini que el mateix plec li concedeix estigui corrent, l'expedient no es resol.
     * Es prova el dia abans i el dia després, i les tres sortides legítimes.
     */
    public function test_no_es_resol_amb_el_termini_d_alegacions_encara_obert(): void
    {
        Carbon::setTestNow('2026-08-14 09:00:00');
        $base = ['estat' => 'comunicat', 'comunicat_ts' => now(), 'resolt_per' => 'Direcció'];

        // (a) Termini viu i cap al·legació: porta tancada, i el motiu diu la data.
        $cas = $this->cas($base + ['termini_alegacions' => '2026-08-20']);
        [$ok, $motiu] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok, 'amb el termini obert no es pot imposar cap sanció');
        $this->assertStringContainsString('20/08/2026', $motiu);

        // (b) L'ÚLTIM dia del termini encara és termini: la defensa el té sencer.
        $cas = $this->cas($base + ['termini_alegacions' => '2026-08-14']);
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok, 'el dia del venciment encara es pot al·legar');

        // (c) L'endemà del venciment, sense al·legacions: es pot resoldre documentant-ho.
        $cas = $this->cas($base + ['termini_alegacions' => '2026-08-13']);
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok, 'vençut el termini sense resposta, l\'expedient segueix el seu curs');

        // (d) Al·legacions presentades: no cal esperar el venciment.
        $cas = $this->cas($base + ['termini_alegacions' => '2026-08-20', 'alegacions_ts' => now()]);
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok);

        // (e) Renúncia: només val si consta QUI hi renuncia. Una data sense autor no és acte propi.
        $cas = $this->cas($base + ['termini_alegacions' => '2026-08-20', 'renuncia_termini_ts' => now()]);
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok, 'una renúncia sense autor no acredita cap acte del treballador');

        $u = $this->treballador();
        $cas = $this->cas($base + ['termini_alegacions' => '2026-08-20',
            'renuncia_termini_ts' => now(), 'renuncia_termini_per' => $u->id]);
        [$ok] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertTrue($ok, 'la renúncia expressa del treballador obre la porta');

        // (f) Sense termini fixat no hi ha hagut tràmit: fail-closed.
        $cas = $this->cas($base + ['termini_alegacions' => null]);
        [$ok, $motiu] = $this->motor->potTransicio($cas, 'resolt');
        $this->assertFalse($ok);
        $this->assertStringContainsString('audiència', $motiu);

        Carbon::setTestNow();
    }

    /** Un fet PRESCRIT (ET 60.2) no pot fonamentar cap càrrec, encara que el cas segueixi viu. */
    public function test_un_fet_prescrit_no_entra_al_plec(): void
    {
        Carbon::setTestNow('2026-08-14 09:00:00');
        $this->falta('puntualitat', 'lleu');

        $viu = $this->element('fisio.prova', ['data_fet' => '2026-08-10', 'data_coneixement' => '2026-08-12']);
        $mort = $this->element('fisio.prova', ['data_fet' => '2026-07-01', 'data_coneixement' => '2026-07-02']);

        $this->assertFalse($this->motor->elementPrescrit($viu));
        $this->assertTrue($this->motor->elementPrescrit($mort), 'lleu: 10 dies des del coneixement');

        $cas = $this->cas(['professional' => 'fisio.prova']);
        DisciplinaryElement::whereIn('id', [$viu->id, $mort->id])->update(['case_id' => $cas->id]);

        $escrit = $this->motor->generaEscrit($cas->fresh(), 'plec_carrecs');
        $this->assertStringContainsString('10 de agost', $escrit);
        $this->assertStringNotContainsString('1 de juliol', $escrit, 'el fet prescrit no pot sortir al plec');

        Carbon::setTestNow();
    }

    /** La prova d'un altre treballador no imputa el titular de l'expedient. */
    public function test_el_plec_no_recull_fets_d_un_altre_treballador(): void
    {
        $this->falta('puntualitat', 'lleu');
        $titular = $this->treballador();
        $altri   = $this->treballador();

        $seu   = $this->element('fisio.prova', ['user_id' => $titular->id, 'descripcio' => 'fet del titular']);
        $alie  = $this->element('altre.fisio', ['user_id' => $altri->id, 'descripcio' => 'fet de tercer']);

        $cas = $this->cas(['professional' => 'fisio.prova', 'user_id' => $titular->id]);
        DisciplinaryElement::whereIn('id', [$seu->id, $alie->id])->update(['case_id' => $cas->id]);

        $escrit = $this->motor->generaEscrit($cas->fresh(), 'plec_carrecs');
        $this->assertStringContainsString('fet del titular', $escrit);
        $this->assertStringNotContainsString('fet de tercer', $escrit);
    }

    /** El destinatari surt de la FITXA, i l'origen de cada prova s'escriu tal com consta. */
    public function test_el_plec_identifica_el_destinatari_i_l_origen_de_la_prova(): void
    {
        $this->falta('puntualitat', 'lleu');
        $u = $this->treballador();
        $u->update(['dni' => '12345678Z']);

        $cas = $this->cas(['professional' => 'fisio.prova', 'user_id' => $u->id]);
        $manual = $this->element('fisio.prova', ['user_id' => $u->id, 'font' => 'informe intern', 'origen' => 'manual']);
        DisciplinaryElement::where('id', $manual->id)->update(['case_id' => $cas->id]);

        $escrit = $this->motor->generaEscrit($cas->fresh(), 'plec_carrecs');
        $this->assertStringContainsString($u->name, $escrit);
        $this->assertStringContainsString('12345678Z', $escrit);
        $this->assertStringContainsString('incorporada manualment', $escrit);

        // Sense titular vinculat no s'inventa cap nom: queda el claudàtor a la vista de qui signa.
        $senseFitxa = $this->cas(['professional' => 'fisio.prova', 'user_id' => null]);
        $this->assertStringContainsString('[vincular el treballador',
            $this->motor->generaEscrit($senseFitxa, 'plec_carrecs'));
    }

    public function test_un_cas_resolt_o_executat_no_pot_retrocedir(): void
    {
        // TAPAT (mitjana 2): l'historial és append-only i la màquina d'estats ara ho respecta. Un cas
        // resolt o executat no torna enrere; reobrir exigeix un expedient nou. L'arxiu, en canvi, és
        // un moviment cap endavant i segueix obert.
        $executat = $this->cas(['estat' => 'executat', 'resolucio_tipus' => 'acomiadament', 'resolt_per' => 'Direcció']);
        $resolt   = $this->cas(['estat' => 'resolt', 'resolucio_tipus' => 'amonestacio', 'resolt_per' => 'Direcció']);

        foreach (['instruccio', 'comunicat', 'alegacions'] as $enrere) {
            foreach (['executat' => $executat, 'resolt' => $resolt] as $origen => $cas) {
                [$ok, $motiu] = $this->motor->potTransicio($cas, $enrere);
                $this->assertFalse($ok, "un cas en «{$origen}» no pot tornar a «{$enrere}»");
                $this->assertStringContainsString('TANCAT', $motiu);
            }
        }

        [$ok] = $this->motor->potTransicio($executat, 'arxivat');
        $this->assertTrue($ok, 'arxivar un cas ja executat és tancar-lo, no retrocedir');
    }

    public function test_requalificar_la_gravetat_manté_el_topall_de_6_mesos(): void
    {
        Carbon::setTestNow('2026-06-20 09:00:00');
        $this->falta('transgressio_bona_fe', 'molt_greu');
        $this->falta('puntualitat', 'lleu');

        $admin = User::create(['name' => 'Admin', 'email' => 'a' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        // Obertura correcta: fet del 10/01, conegut el 20/06 → el topall dur mana (10/07).
        $r = $this->postJson('/api/v1/disciplinary/cases', [
            'professional' => 'fisio.prova', 'vincle' => 'laboral',
            'tipus_falta' => 'puntualitat', 'gravetat' => 'lleu',
            'data_coneixement' => '2026-06-20', 'data_fet' => '2026-01-10',
        ])->assertStatus(201);
        $casId = $r->json('id');
        $this->assertSame('2026-06-30', DisciplinaryCase::find($casId)->data_prescripcio->toDateString());

        // TAPAT (defecte 2): disciplinary_cases DESA data_fet i el recàlcul per requalificació la
        // torna a passar al motor. A molt greu el termini per coneixement seria el 19/08, però mana
        // el topall dur dels 6 mesos des de la comissió: 10/01 + 6 mesos = 10/07.
        $this->assertSame('2026-01-10', DisciplinaryCase::find($casId)->data_fet->toDateString(),
            'la data del fet s\'ha de desar al cas: sense ella el topall és irrecuperable');

        $this->putJson("/api/v1/disciplinary/cases/{$casId}", ['gravetat' => 'molt_greu'])->assertOk();

        $this->assertSame('2026-07-10',
            DisciplinaryCase::find($casId)->data_prescripcio->toDateString(),
            'requalificar no pot allargar la falta més enllà dels 6 mesos des del fet (ET 60.2)');
    }

    public function test_el_vincle_del_cas_es_contrasta_amb_la_fitxa_del_treballador(): void
    {
        // TAPAT (mitjana 1): el veto ja no es basa només en el camp 'vincle' del formulari sinó en
        // User::esAutonom(). Un col·laborador classificat com a autònom a RRHH no pot acabar amb un
        // expedient disciplinari laboral obert marcant "laboral" a l'alta del cas.
        $this->falta('puntualitat', 'lleu');
        $autonom = User::create([
            'name' => 'Col·laborador Autònom', 'email' => 'au' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
            'relacio' => 'autonom', 'domi_username' => 'fisio.autonom',
        ]);
        $this->assertTrue($autonom->esAutonom());

        $admin = User::create(['name' => 'Admin', 'email' => 'a' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);

        $r = $this->postJson('/api/v1/disciplinary/cases', [
            'professional' => 'fisio.autonom', 'vincle' => 'laboral',
            'tipus_falta' => 'puntualitat', 'data_coneixement' => Carbon::today()->toDateString(),
        ]);

        $r->assertStatus(422);
        $this->assertStringContainsString('mercantil', $r->json('message'));
        $this->assertSame(0, DisciplinaryCase::count(),
            'no pot quedar cap expedient laboral obert a qui té vincle mercantil');

        // I si un cas antic ja el porta marcat com a 'laboral', el motor també el tanca a la porta.
        $cas = $this->cas(['user_id' => $autonom->id, 'vincle' => 'laboral']);
        [$ok, $motiu] = $this->motor->potTransicio($cas, 'comunicat');
        $this->assertFalse($ok);
        $this->assertStringContainsString('mercantil', $motiu);
    }
}
