<?php

namespace Tests\Feature;

use App\Models\ComplianceAcknowledgement;
use App\Models\ComplianceDocument;
use App\Models\DailySeal;
use App\Models\IntegrityEvent;
use App\Models\User;
use App\Services\IntegrityLedger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SEGELL D'INTEGRITAT — el llibre append-only i la seva verificació (IntegrityLedger).
 *
 * El mòdul només té sentit si DETECTA. Un llibre que sempre diu "ok" és pitjor que no tenir-ne: dona
 * una confiança que no acredita res davant d'un tercer (inspecció, jutjat). Per això el nucli
 * d'aquesta suite és la MANIPULACIÓ: es toca una fila al mig de la cadena i es comprova què canta
 * verify() i què no.
 *
 * El contracte que es fixa:
 *   - Una cadena intacta verifica ok, amb el recompte d'esdeveniments del dia.
 *   - Alterar el contingut d'una fila (payload_hash) trenca la cadena i verify() diu ON.
 *   - Esborrar una fila trenca l'arrel del dia.
 *   - La incorporació és idempotent (una fila d'origen entra un sol cop) i tolera fonts absents.
 *   - Els dies s'encadenen: el segell d'avui porta l'arrel d'ahir.
 *
 * Les tres manipulacions que verify() NO detectava (recalcular el hash d'una fila del mig, editar la
 * fila d'origen després de segellar, i el segell il·localitzable fora de MySQL) ja estan TAPADES:
 * les assercions s'han invertit i ara exigeixen la detecció.
 */
class SegellIntegritatTest extends TestCase
{
    use RefreshDatabase;

    private IntegrityLedger $llibre;

    protected function setUp(): void
    {
        parent::setUp();
        // Cap crida real: ni a apps remotes ni a la TSA (els tests provaran sempre amb withTsa=false).
        Http::fake(['*' => Http::response(null, 500)]);
        config([
            // Una sola font controlada: el que es prova és la CADENA, no el catàleg de fonts real.
            'integrity.sources'        => [['table' => 'compliance_acknowledgements', 'ts' => 'acknowledged_at']],
            'integrity.remote_sources' => [],
            'integrity.tsa.url'        => '',
        ]);
        $this->llibre = app(IntegrityLedger::class);
    }

    /**
     * Tanca el dia. `seal_date` ja es desa normalitzada ('Y-m-d') a qualsevol motor de BD gràcies al
     * mutador de DailySeal, i verify() busca el segell pel DIA: aquí no cal cap correcció prèvia.
     * Vegeu test_la_cerca_del_segell_per_data_es_portable().
     */
    private function segella(Carbon $dia, bool $ambTsa = false): DailySeal
    {
        return $this->llibre->sealDay($dia, $ambTsa)->fresh();
    }

    /** Crea $n acusaments (files d'origen) dins del dia indicat, un per usuari. */
    private function fonts(Carbon $dia, int $n): void
    {
        $doc = ComplianceDocument::create([
            'tipus' => 'info_art90', 'titol' => 'Informació art. 90', 'versio' => '1.0',
            'contingut' => 'text', 'estat' => 'publicat', 'requereix_acus' => true,
            'published_at' => $dia->copy()->startOfDay(),
        ]);
        for ($i = 0; $i < $n; $i++) {
            $u = User::create([
                'name' => "Treballador {$i}", 'email' => 'i' . uniqid() . '@test.local',
                'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
            ]);
            ComplianceAcknowledgement::create([
                'compliance_document_id' => $doc->id, 'user_id' => $u->id,
                'document_versio' => '1.0', 'ip' => '10.0.0.' . ($i + 1),
                'acknowledged_at' => $dia->copy()->setTime(10, $i),
            ]);
        }
    }

    /**
     * Fa servir l'entrada REAL del catàleg (config/integrity.php) per a la taula indicada, no una
     * còpia inventada dins del test. Així, si algú fica 'amount' fora de la llista d'estables o hi
     * afegeix 'viewed_at', són aquests tests els que canten — no un catàleg paral·lel que sempre
     * quadra amb ell mateix.
     */
    private function usaFontDelCataleg(string $taula): array
    {
        $real = collect(require base_path('config/integrity.php'))
            ->get('sources');
        $font = collect($real)->firstWhere('table', $taula);
        $this->assertNotNull($font, "la taula «{$taula}» ha d'estar al catàleg real de config/integrity.php");
        config(['integrity.sources' => [$font]]);

        return $font;
    }

    /** Una nòmina entregada (import + PDF), amb el rastre d'entrega encara buit. */
    private function nomina(Carbon $dia, float $import = 1834.72): int
    {
        $u = User::create([
            'name' => 'Treballadora', 'email' => 'n' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);

        return DB::table('payrolls')->insertGetId([
            'user_id'        => $u->id,
            'title'          => 'Nòmina Maig 2026',
            'month'          => '05',
            'year'           => 2026,
            'amount'         => $import,
            'payroll_base64' => 'data:application/pdf;base64,JVBERi0xLjQK',
            'file_name'      => 'nomina_2026_05.pdf',
            'created_at'     => $dia->copy()->setTime(8, 0),
            'updated_at'     => $dia->copy()->setTime(8, 0),
        ]);
    }

    // ── Cadena intacta ────────────────────────────────────────────────────────

    public function test_una_cadena_intacta_es_verifica(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 5);

        $segell = $this->segella($dia);
        $this->assertSame(5, $segell->events_count);

        $r = $this->llibre->verify($dia);
        $this->assertTrue($r['ok']);
        $this->assertSame(5, $r['esdeveniments']);
        $this->assertNull($r['cadena_trencada_a']);
        $this->assertTrue($r['arrel_coincideix']);
    }

    public function test_cada_esdeveniment_encadena_el_hash_de_l_anterior(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 4);
        $this->segella($dia);

        $events = IntegrityEvent::orderBy('id')->get()->values();
        $this->assertCount(4, $events);
        $this->assertNull($events[0]->prev_hash, 'la primera baula no en té d\'anterior');
        for ($i = 1; $i < $events->count(); $i++) {
            $this->assertSame($events[$i - 1]->hash, $events[$i]->prev_hash,
                'cada esdeveniment ha de portar el hash del que el precedeix');
        }
    }

    public function test_verify_sense_segell_no_menteix(): void
    {
        $r = $this->llibre->verify(Carbon::parse('2026-05-20'));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('No hi ha segell', $r['motiu']);
    }

    // ── DETECCIÓ DE MANIPULACIÓ (la raó de ser del mòdul) ────────────────────

    public function test_verify_detecta_una_manipulacio_al_mig_de_la_cadena(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 5);
        $this->segella($dia);
        $this->assertTrue($this->llibre->verify($dia)['ok']);

        $delMig = IntegrityEvent::orderBy('id')->get()[2];

        // Manipulació dins d'una transacció: qui té accés a la BD reescriu el contingut segellat.
        DB::transaction(function () use ($delMig) {
            DB::table('integrity_events')->where('id', $delMig->id)
                ->update(['payload_hash' => hash('sha256', 'contingut falsificat')]);
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok'], 'una fila alterada al mig ha de fer caure la verificació');
        $this->assertSame($delMig->id, $r['cadena_trencada_a'],
            'verify() ha de dir EXACTAMENT on s\'ha trencat, no només que alguna cosa falla');
    }

    public function test_verify_detecta_la_manipulacio_de_l_ultim_esdeveniment(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 3);
        $this->segella($dia);

        $ultim = IntegrityEvent::orderByDesc('id')->first();
        DB::transaction(function () use ($ultim) {
            DB::table('integrity_events')->where('id', $ultim->id)
                ->update(['payload_hash' => hash('sha256', 'ultim falsificat')]);
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok']);
        $this->assertSame($ultim->id, $r['cadena_trencada_a']);
    }

    public function test_verify_detecta_l_esborrat_d_una_fila(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 5);
        $this->segella($dia);

        $delMig = IntegrityEvent::orderBy('id')->get()[2];
        DB::transaction(function () use ($delMig) {
            DB::table('integrity_events')->where('id', $delMig->id)->delete();
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok'], 'append-only: fer desaparèixer una fila ha de cantar');
        $this->assertFalse($r['arrel_coincideix'],
            'l\'arrel del dia incorpora el recompte: si en falta una, no lliga');
    }

    public function test_verify_detecta_la_manipulacio_de_l_arrel_del_segell(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 3);
        $segell = $this->segella($dia);

        DB::transaction(function () use ($segell) {
            DB::table('daily_seals')->where('id', $segell->id)
                ->update(['root_hash' => hash('sha256', 'arrel falsificada')]);
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok']);
        $this->assertNull($r['cadena_trencada_a'], 'la cadena està sana: el que s\'ha tocat és l\'arrel');
        $this->assertFalse($r['arrel_coincideix']);
    }

    // ── Incorporació: idempotent, ordenada i tolerant a fonts absents ─────────

    public function test_la_incorporacio_es_idempotent(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 4);

        $this->assertSame(4, $this->llibre->collect($dia));
        $this->assertSame(0, $this->llibre->collect($dia),
            'una fila d\'origen s\'incorpora un sol cop, per moltes passades que es facin');
        $this->assertSame(4, IntegrityEvent::count());
    }

    public function test_una_font_absent_no_trenca_la_resta(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 3);
        config(['integrity.sources' => [
            ['table' => 'taula_que_no_existeix',        'ts' => 'created_at'],
            ['table' => 'compliance_acknowledgements',  'ts' => 'columna_que_no_existeix'],
            ['table' => 'compliance_acknowledgements',  'ts' => 'acknowledged_at'],
        ]]);

        $this->assertSame(3, $this->llibre->collect($dia),
            'una font mal configurada s\'omet; la resta del segell s\'ha de fer igualment');
    }

    public function test_els_dies_s_encadenen_entre_ells(): void
    {
        $dia1 = Carbon::parse('2026-05-20');
        $this->fonts($dia1, 2);
        $segell1 = $this->segella($dia1);

        $dia2 = Carbon::parse('2026-05-21');
        $this->fonts($dia2, 2);
        $segell2 = $this->segella($dia2);

        $this->assertSame($segell1->root_hash, $segell2->prev_seal_hash,
            'el segell d\'un dia ha de portar l\'arrel de l\'anterior');
        $this->assertSame(2, $segell2->events_count, 'el dia 2 només segella els seus esdeveniments');
        $this->assertTrue($this->llibre->verify($dia1)['ok']);
        $this->assertTrue($this->llibre->verify($dia2)['ok']);
    }

    public function test_un_dia_sense_esdeveniments_es_tanca_igualment(): void
    {
        $dia = Carbon::parse('2026-05-20');

        $segell = $this->segella($dia);
        $this->assertSame(0, $segell->events_count);
        $this->assertNotNull($segell->root_hash, 'un dia buit també ha de deixar constància');
        $this->assertTrue($this->llibre->verify($dia)['ok']);
    }

    public function test_sense_tsa_configurada_el_dia_queda_pendent_de_segell_extern(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 2);

        $segell = $this->segella($dia, true);   // amb TSA, però tsa.url buida
        $this->assertSame('pendent', $segell->tsa_status,
            'sense proveïdor configurat no s\'inventa cap segell: queda pendent i es diu');
        $this->assertNull($segell->tsa_token);
    }

    // ── MANIPULACIONS TAPADES (abans buits: assercions invertides) ───────────

    public function test_verify_detecta_una_manipulacio_amb_el_hash_recalculat(): void
    {
        // TAPAT (defecte 3): el hash és un sha256 sense clau ni secret, així que qui pot escriure a
        // la BD pot recalcular-lo; i l'arrel del dia només depèn de l'ÚLTIM hash i del recompte. La
        // garantia no la dona el hash de la fila sinó la CADENA: verify() comprova ara, baula a
        // baula, que prev_hash[n] == hash[n-1] i que el contingut segueix quadrant amb la font.
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 5);
        $this->segella($dia);

        $events = IntegrityEvent::orderBy('id')->get();
        $delMig = $events[2];
        $seguent = $events[3];

        DB::transaction(function () use ($delMig) {
            $fals = hash('sha256', 'contingut falsificat');
            $hashCoherent = hash('sha256', ($delMig->prev_hash ?? '') . '|' . $delMig->source . '|'
                . $delMig->source_id . '|' . $delMig->occurred_at . '|' . $fals);
            DB::table('integrity_events')->where('id', $delMig->id)
                ->update(['payload_hash' => $fals, 'hash' => $hashCoherent]);
        });

        // La cadena ESTÀ trencada: el següent ja no enllaça amb aquest.
        $this->assertNotSame(IntegrityEvent::find($delMig->id)->hash, $seguent->prev_hash);

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok'],
            'una fila reescrita amb el hash recalculat ha de fer caure la verificació');
        $this->assertSame($delMig->id, $r['cadena_trencada_a'],
            'verify() ha de dir EXACTAMENT quina fila falla');
    }

    public function test_verify_detecta_el_trencament_de_l_enllac_encara_que_tot_quadri(): void
    {
        // Manipulació que passa TOTES les comprovacions d'una fila aïllada: el contingut segueix
        // quadrant amb la font i el hash quadra amb el seu propi contingut. L'única cosa que la
        // delata és l'enllaç: el següent esdeveniment ja no porta aquest hash com a prev_hash.
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 5);
        $this->segella($dia);

        $events = IntegrityEvent::orderBy('id')->get();
        $delMig = $events[2];
        $seguent = $events[3];

        DB::transaction(function () use ($delMig) {
            $falsejat = '2026-05-20 23:59:59';   // es reescriu QUAN va passar, no QUÈ
            $hashCoherent = hash('sha256', ($delMig->prev_hash ?? '') . '|' . $delMig->source . '|'
                . $delMig->source_id . '|' . $falsejat . '|' . $delMig->payload_hash);
            DB::table('integrity_events')->where('id', $delMig->id)
                ->update(['occurred_at' => $falsejat, 'hash' => $hashCoherent]);
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok']);
        $this->assertSame($seguent->id, $r['cadena_trencada_a'],
            'el trencament es veu a la baula que ja no enllaça amb l\'anterior');
        $this->assertStringContainsString('prev_hash', $r['motiu']);
    }

    public function test_la_cerca_del_segell_per_data_es_portable(): void
    {
        // TAPAT (mitjana 3): Eloquent escrivia el cast 'date' com a 'Y-m-d H:i:s'. A MySQL la columna
        // DATE truncava l'hora i la cerca per igualtat funcionava; a SQLite (els tests i qualsevol
        // còpia local) es desava '2026-05-20 00:00:00' i verify() responia "No hi ha segell" encara
        // que el segell hi fos. Ara la data es normalitza en desar-la i la cerca és per DIA.
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 2);
        $segell = $this->llibre->sealDay($dia, false);   // sense cap correcció posterior

        $this->assertSame('2026-05-20',
            DB::table('daily_seals')->where('id', $segell->id)->value('seal_date'));
        $this->assertTrue($this->llibre->verify($dia)['ok'],
            'el segell existeix i verify() l\'ha de trobar sigui quin sigui el motor de BD');

        // Un segell antic amb l'hora enganxada tampoc es pot perdre.
        DB::table('daily_seals')->where('id', $segell->id)
            ->update(['seal_date' => '2026-05-20 00:00:00']);
        $this->assertTrue($this->llibre->verify($dia)['ok'],
            'els segells escrits abans de normalitzar la data s\'han de seguir trobant');

        // I resegellar el mateix dia no duplica el segell (la cerca de sealDay també és per dia).
        $this->llibre->sealDay($dia, false);
        $this->assertSame(1, DailySeal::count());
    }

    public function test_l_endpoint_de_verificacio_del_segell_respon_amb_la_cadena(): void
    {
        // GET /integrity/seals/{seal}/verify feia Carbon::parse($seal->seal_date) i arrossegava el
        // mateix problema de portabilitat: l'admin veia "No hi ha segell" sobre un segell existent.
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 3);
        $segell = $this->segella($dia);

        \Laravel\Sanctum\Sanctum::actingAs(User::create([
            'name' => 'Admin', 'email' => 'ad' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true,
        ]));

        $r = $this->getJson("/api/v1/integrity/seals/{$segell->id}/verify")->assertOk()->json();
        $this->assertTrue($r['ok']);
        $this->assertSame(3, $r['esdeveniments']);
    }

    public function test_verify_detecta_l_alteracio_de_la_fila_d_origen(): void
    {
        // TAPAT (defecte 4): verify() torna a llegir la taula d'origen i en recomputa el payload.
        // Editar l'acusament original (data, IP, versió) DESPRÉS de segellar-lo ja no passa: el que
        // el segell acredita ha de seguir corresponent-se amb la realitat, no amb el que hi ha desat.
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 3);
        $this->segella($dia);

        $primer = IntegrityEvent::orderBy('id')->first();

        DB::transaction(function () {
            DB::table('compliance_acknowledgements')->orderBy('id')->limit(1)
                ->update(['ip' => '203.0.113.99', 'acknowledged_at' => '2026-05-20 23:59:00']);
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok'], 'editar la font d\'un esdeveniment segellat ha de cantar');
        $this->assertSame($primer->id, $r['cadena_trencada_a']);
        $this->assertStringContainsString('modificat', $r['motiu']);
    }

    // ── NÒMINES: camps estables (abans 'mutable' => true, que no verificava res) ─────────────
    //
    // Amb 'mutable' => true, verify() NO tornava a llegir la fila de payrolls: la cadena detectava
    // insercions, esborrats i reordenacions, però qui pogués escriure a la BD podia canviar l'IMPORT
    // d'una nòmina ja segellada i el llibre seguia dient "ok". Provar que els imports no s'han tocat
    // és bona part del sentit del llibre, així que ara se segellen els camps ESTABLES de la font
    // (import, període, titular, PDF) i es tornen a llegir; el rastre d'entrega queda fora.

    public function test_el_cataleg_no_segella_el_rastre_d_entrega_de_la_nomina(): void
    {
        // Contracte del catàleg: què ha d'entrar al segell i què NO. Si algú "arregla" un fals
        // vermell traient l'import de la llista, aquí es veu.
        $font = $this->usaFontDelCataleg('payrolls');

        $this->assertArrayNotHasKey('mutable', $font,
            'la marca que renunciava a rellegir la font ja no hi ha de ser');
        $this->assertIsArray($font['estables'] ?? null, 'payrolls ha de declarar camps estables');

        foreach (['id', 'user_id', 'month', 'year', 'amount', 'payroll_base64'] as $col) {
            $this->assertContains($col, $font['estables'],
                "«{$col}» no canvia mai legítimament: ha d'estar segellat");
        }
        foreach (['viewed_at', 'signed_at', 'signature_hash', 'updated_at'] as $col) {
            $this->assertNotContains($col, $font['estables'],
                "«{$col}» canvia legítimament: si se segella, el llibre canta cada dia sense manipulació");
        }
    }

    public function test_visualitzar_i_signar_una_nomina_no_trenca_el_segell(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->usaFontDelCataleg('payrolls');
        $id = $this->nomina($dia);

        $segell = $this->segella($dia);
        $this->assertSame(1, $segell->events_count, 'la nòmina ha d\'haver entrat al llibre');
        $this->assertTrue($this->llibre->verify($dia)['ok']);

        // La treballadora la visualitza i la signa DIES DESPRÉS de segellar-se: canvi legítim.
        DB::transaction(function () use ($id) {
            DB::table('payrolls')->where('id', $id)->update([
                'viewed_at'      => '2026-06-02 09:14:00',
                'signed_at'      => '2026-06-02 09:15:30',
                'signature_hash' => hash('sha256', 'firma de la treballadora'),
                'updated_at'     => '2026-06-02 09:15:30',
            ]);
        });

        $r = $this->llibre->verify($dia);
        $this->assertTrue($r['ok'],
            'visualitzar o signar una nòmina és el curs normal: el llibre no ho pot denunciar');
        $this->assertNull($r['cadena_trencada_a']);
    }

    public function test_canviar_l_import_d_una_nomina_segellada_es_detecta(): void
    {
        // LA prova que dona sentit a tota la feina: amb 'mutable' => true això passava en silenci.
        $dia = Carbon::parse('2026-05-20');
        $this->usaFontDelCataleg('payrolls');
        $id = $this->nomina($dia, 1834.72);

        $this->segella($dia);
        $this->assertTrue($this->llibre->verify($dia)['ok']);

        $ev = IntegrityEvent::where('source', 'payrolls')->where('source_id', $id)->firstOrFail();

        DB::transaction(function () use ($id) {
            DB::table('payrolls')->where('id', $id)->update(['amount' => 999.00]);
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok'], 'canviar l\'IMPORT d\'una nòmina segellada ha de posar el llibre en vermell');
        $this->assertSame($ev->id, $r['cadena_trencada_a'], 'verify() ha de dir QUINA fila');
        $this->assertStringContainsString("payrolls#{$id}", $r['motiu']);
        $this->assertStringContainsString('modificat', $r['motiu']);
    }

    public function test_canviar_el_pdf_o_el_titular_d_una_nomina_segellada_es_detecta(): void
    {
        // L'import de veritat viu al PDF (amount n'és el resum): substituir el document ha de cantar
        // igual que tocar la xifra. I reassignar la nòmina a un altre treballador, també.
        $dia = Carbon::parse('2026-05-20');
        $this->usaFontDelCataleg('payrolls');

        $altre = User::create([
            'name' => 'Un altre', 'email' => 'a' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);

        foreach ([
            'PDF substituït'  => ['payroll_base64' => 'data:application/pdf;base64,RkFMU0lGSUNBVA=='],
            'titular canviat' => ['user_id' => $altre->id],
            'període canviat' => ['month' => '06'],
        ] as $etiqueta => $canvi) {
            IntegrityEvent::query()->delete();
            DailySeal::query()->delete();
            DB::table('payrolls')->delete();

            $id = $this->nomina($dia);
            $this->segella($dia);
            $this->assertTrue($this->llibre->verify($dia)['ok'], "{$etiqueta}: partida en verd");

            DB::transaction(function () use ($id, $canvi) {
                DB::table('payrolls')->where('id', $id)->update($canvi);
            });

            $this->assertFalse($this->llibre->verify($dia)['ok'],
                "{$etiqueta}: hauria de posar el llibre en vermell");
        }
    }

    public function test_esborrar_una_nomina_segellada_es_detecta(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->usaFontDelCataleg('payrolls');
        $id = $this->nomina($dia);
        $this->segella($dia);

        $ev = IntegrityEvent::where('source', 'payrolls')->where('source_id', $id)->firstOrFail();

        DB::transaction(function () use ($id) {
            DB::table('payrolls')->where('id', $id)->delete();
        });

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok'], 'el segell acreditaria una nòmina que ja no existeix');
        $this->assertSame($ev->id, $r['cadena_trencada_a']);
        $this->assertStringContainsString('ja no existeix', $r['motiu']);
    }

    public function test_una_font_sense_camps_estables_segueix_segellant_la_fila_sencera(): void
    {
        // No hi ha regressió a les append-only: continuen hashejant i rellegint la fila SENCERA,
        // sense haver de mantenir cap llista de columnes.
        $dia = Carbon::parse('2026-05-20');
        $font = $this->usaFontDelCataleg('compliance_acknowledgements');
        $this->assertArrayNotHasKey('estables', $font);

        $this->fonts($dia, 2);
        $this->segella($dia);
        $this->assertTrue($this->llibre->verify($dia)['ok']);

        // Es toca una columna qualsevol (ni tan sols la data): ha de cantar igualment.
        DB::table('compliance_acknowledgements')->orderBy('id')->limit(1)
            ->update(['document_versio' => '9.9']);

        $this->assertFalse($this->llibre->verify($dia)['ok']);
    }

    public function test_verify_detecta_l_esborrat_de_la_fila_d_origen(): void
    {
        // Fer desaparèixer la prova d'origen és tan greu com editar-la: el segell acreditaria una
        // cosa que ja no existeix.
        $dia = Carbon::parse('2026-05-20');
        $this->fonts($dia, 3);
        $this->segella($dia);

        $primer = IntegrityEvent::orderBy('id')->first();
        DB::table('compliance_acknowledgements')->where('id', $primer->source_id)->delete();

        $r = $this->llibre->verify($dia);
        $this->assertFalse($r['ok']);
        $this->assertSame($primer->id, $r['cadena_trencada_a']);
        $this->assertStringContainsString('ja no existeix', $r['motiu']);
    }
}
