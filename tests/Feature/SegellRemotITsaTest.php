<?php

namespace Tests\Feature;

use App\Models\ComplianceAcknowledgement;
use App\Models\ComplianceDocument;
use App\Models\IntegrityEvent;
use App\Models\User;
use App\Services\IntegrityLedger;
use App\Services\TsaClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LES DUES PECES DEL SEGELL QUE NO ES PROVAVEN: les firmes que arriben d'ALTRES APPS
 * (IntegrityLedger::collectRemote) i el SEGELLAT DE TEMPS REAL (TsaClient).
 *
 * Per què importen:
 *   · collectRemote és el que fa que un sol crèdit FNMT/dia empari també les firmes de domi i
 *     logopedia. Si s'empassa events repetits, o si una app caiguda tomba el tancament, el segell
 *     del dia surt malament o no surt — i el que s'acredita davant d'un tercer és el que hi ha
 *     dins d'aquell segell.
 *   · TsaClient és l'única part del mòdul que parla amb el món exterior i que gasta diners (un
 *     crèdit per crida). Ha de fallar en silenci i no tombar mai el tancament diari, i no ha de
 *     gastar un segon crèdit per un dia ja segellat.
 *
 * Cap prova surt a la xarxa: Http::fake a tot arreu.
 */
class SegellRemotITsaTest extends TestCase
{
    use RefreshDatabase;

    private IntegrityLedger $llibre;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'integrity.sources'        => [['table' => 'compliance_acknowledgements', 'ts' => 'acknowledged_at']],
            'integrity.remote_sources' => [],
            'integrity.tsa.url'        => '',
        ]);
        $this->llibre = app(IntegrityLedger::class);
    }

    /** Una font local (acusament) dins del dia, per tenir cadena prèvia amb què encadenar. */
    private function fontLocal(Carbon $dia): void
    {
        $doc = ComplianceDocument::create([
            'tipus' => 'info_art90', 'titol' => 'Informació art. 90', 'versio' => '1.0',
            'contingut' => 'text', 'estat' => 'publicat', 'requereix_acus' => true,
            'published_at' => $dia->copy()->startOfDay(),
        ]);
        $u = User::create([
            'name' => 'Treballador', 'email' => 'r' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true,
        ]);
        ComplianceAcknowledgement::create([
            'compliance_document_id' => $doc->id, 'user_id' => $u->id,
            'document_versio' => '1.0', 'ip' => '10.0.0.1',
            'acknowledged_at' => $dia->copy()->setTime(9, 0),
        ]);
    }

    private function fontRemota(string $name = 'domi_firmes', string $url = 'https://domi.test/api/firmes_dia.php', string $token = 'secret-token'): array
    {
        return ['name' => $name, 'url' => $url, 'token' => $token];
    }

    // ══ collectRemote: firmes d'altres apps ══════════════════════════════════

    public function test_les_firmes_remotes_entren_al_llibre(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => [
            ['id' => 101, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('a', 64)],
            ['id' => 102, 'occurred_at' => '2026-05-20 12:00:00', 'hash' => str_repeat('b', 64)],
        ]])]);

        $this->assertSame(2, $this->llibre->collectRemote($dia));

        $events = IntegrityEvent::where('source', 'domi_firmes')->orderBy('id')->get();
        $this->assertSame([101, 102], $events->pluck('source_id')->map(fn ($i) => (int) $i)->all());
        // El payload segellat és el DIGEST de la firma remota, mai la firma en cru.
        $this->assertSame(hash('sha256', 'domi_firmes|101|' . str_repeat('a', 64)), $events[0]->payload_hash);
    }

    public function test_la_peticio_porta_el_token_i_el_rang_del_dia(): void
    {
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => []])]);

        $this->llibre->collectRemote(Carbon::parse('2026-05-20'));

        Http::assertSent(function ($request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);

            return $request->hasHeader('Authorization', 'Bearer secret-token')
                && ($q['desde'] ?? null) === '2026-05-20'
                && ($q['fins'] ?? null) === '2026-05-20';
        });
    }

    public function test_les_firmes_remotes_encadenen_amb_les_locals(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fontLocal($dia);
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => [
            ['id' => 101, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('a', 64)],
        ]])]);

        $this->llibre->collect($dia);
        $ultimLocal = IntegrityEvent::orderByDesc('id')->first();
        $this->llibre->collectRemote($dia);

        $remot = IntegrityEvent::where('source', 'domi_firmes')->firstOrFail();
        $this->assertSame($ultimLocal->hash, $remot->prev_hash,
            'la firma remota ha de penjar de l\'última baula local: la cadena és una de sola');
        $this->assertSame(
            hash('sha256', $remot->prev_hash . '|domi_firmes|101|2026-05-20 11:00:00|' . $remot->payload_hash),
            $remot->hash
        );
    }

    public function test_la_incorporacio_remota_es_idempotent(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => [
            ['id' => 101, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('a', 64)],
        ]])]);

        $this->assertSame(1, $this->llibre->collectRemote($dia));
        $this->assertSame(0, $this->llibre->collectRemote($dia), 'la mateixa firma no pot entrar dues vegades');
        $this->assertSame(1, IntegrityEvent::where('source', 'domi_firmes')->count());
    }

    public function test_una_app_remota_caiguda_no_tomba_la_resta(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [
            $this->fontRemota('domi_firmes', 'https://domi.test/api/firmes_dia.php'),
            $this->fontRemota('logopedia_firmes', 'https://logo.test/api/firmes_dia.php'),
        ]]);
        Http::fake([
            'domi.test/*' => Http::response('boom', 500),
            'logo.test/*' => Http::response(['events' => [
                ['id' => 7, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('c', 64)],
            ]]),
        ]);

        $this->assertSame(1, $this->llibre->collectRemote($dia));
        $this->assertSame(0, IntegrityEvent::where('source', 'domi_firmes')->count());
        $this->assertSame(1, IntegrityEvent::where('source', 'logopedia_firmes')->count());
    }

    public function test_una_app_remota_inaccessible_no_llenca(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [
            $this->fontRemota('domi_firmes', 'https://domi.test/api/firmes_dia.php'),
            $this->fontRemota('logopedia_firmes', 'https://logo.test/api/firmes_dia.php'),
        ]]);
        Http::fake([
            'domi.test/*' => fn () => throw new ConnectionException('cURL error 28: timeout'),
            'logo.test/*' => Http::response(['events' => [
                ['id' => 7, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('c', 64)],
            ]]),
        ]);

        $this->assertSame(1, $this->llibre->collectRemote($dia),
            'un timeout d\'una app no pot deixar el dia sense segellar');
    }

    public function test_les_firmes_malformades_s_ignoren(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => [
            ['occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('a', 64)],   // sense id
            ['id' => 102, 'occurred_at' => '2026-05-20 11:00:00'],                     // sense hash
            ['id' => 103, 'hash' => str_repeat('d', 64)],                              // sense data → la del dia
        ]])]);

        $this->assertSame(1, $this->llibre->collectRemote($dia));
        $this->assertSame('103', (string) IntegrityEvent::where('source', 'domi_firmes')->value('source_id'));
    }

    public function test_una_resposta_sense_events_no_afegeix_res(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['ok' => true])]);

        $this->assertSame(0, $this->llibre->collectRemote($dia));
    }

    public function test_una_font_remota_mal_configurada_s_omet(): void
    {
        config(['integrity.remote_sources' => [
            ['name' => 'sense_url'],
            ['url' => 'https://domi.test/api/firmes_dia.php'],   // sense name
        ]]);
        // Http::preventStrayRequests() garanteix que ni tan sols s'intenta la crida.
        $this->assertSame(0, $this->llibre->collectRemote(Carbon::parse('2026-05-20')));
    }

    public function test_el_segell_del_dia_engloba_les_firmes_remotes_i_segueix_verificant(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fontLocal($dia);
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => [
            ['id' => 101, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('a', 64)],
            ['id' => 102, 'occurred_at' => '2026-05-20 12:00:00', 'hash' => str_repeat('b', 64)],
        ]])]);

        $segell = $this->llibre->sealDay($dia, false);
        $this->assertSame(3, $segell->events_count, '1 local + 2 remotes dins del MATEIX segell (1 crèdit)');

        // Una firma remota no es pot rellegir de cap taula local: verify() no pot exigir-ne la font,
        // però tampoc pot donar-la per bona sense comprovar-ne la baula. La cadena ha de quadrar.
        $this->assertTrue($this->llibre->verify($dia)['ok']);
    }

    public function test_manipular_una_firma_remota_ja_segellada_es_detecta(): void
    {
        $dia = Carbon::parse('2026-05-20');
        config(['integrity.remote_sources' => [$this->fontRemota()]]);
        Http::fake(['domi.test/*' => Http::response(['events' => [
            ['id' => 101, 'occurred_at' => '2026-05-20 11:00:00', 'hash' => str_repeat('a', 64)],
            ['id' => 102, 'occurred_at' => '2026-05-20 12:00:00', 'hash' => str_repeat('b', 64)],
        ]])]);
        $this->llibre->sealDay($dia, false);
        $this->assertTrue($this->llibre->verify($dia)['ok']);

        // Algú canvia el digest de la firma remota #101 a la base de dades.
        $ev = IntegrityEvent::where('source', 'domi_firmes')->where('source_id', 101)->firstOrFail();
        $ev->update(['payload_hash' => hash('sha256', 'una altra cosa')]);

        $informe = $this->llibre->verify($dia);
        $this->assertFalse($informe['ok'], 'una firma remota alterada després de segellar ha de cantar');
        $this->assertSame($ev->id, $informe['cadena_trencada_a']);
    }

    // ══ TsaClient: el segellat de temps real ═════════════════════════════════

    private function arrel(): string
    {
        return hash('sha256', 'arrel del dia');
    }

    public function test_sense_url_no_es_crida_la_tsa(): void
    {
        config(['integrity.tsa.url' => '']);
        $r = app(TsaClient::class)->stamp($this->arrel());

        $this->assertSame('pendent', $r['status']);
        $this->assertStringContainsString('TSA no configurada', $r['error']);
        Http::assertNothingSent();
    }

    public function test_una_arrel_que_no_es_un_sha256_no_gasta_credit(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        foreach (['', 'no-hex', str_repeat('a', 63), str_repeat('z', 64)] as $dolenta) {
            $r = app(TsaClient::class)->stamp($dolenta);
            $this->assertSame('error', $r['status'], "arrel «{$dolenta}»");
            $this->assertSame('arrel no vàlida', $r['error']);
        }
        Http::assertNothingSent();
    }

    public function test_una_resposta_de_la_tsa_es_desa_com_a_token(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa', 'integrity.tsa.provider' => 'fnmt']);
        $der = "\x30\x82\x01\x00" . random_bytes(64);   // resposta binària qualsevol
        Http::fake(['tsa.test/*' => Http::response($der, 200)]);

        $r = app(TsaClient::class)->stamp($this->arrel());

        $this->assertSame('segellat', $r['status']);
        $this->assertSame('fnmt', $r['provider']);
        $this->assertSame(base64_encode($der), $r['token'], 'el token es desa en base64, tal com arriba');

        // La petició ha de ser una TimeStampReq RFC 3161 de debò, no un JSON inventat.
        Http::assertSent(function ($request) {
            return $request->hasHeader('Content-Type', 'application/timestamp-query')
                && str_starts_with($request->body(), "\x30");   // SEQUENCE DER
        });
    }

    public function test_un_error_http_de_la_tsa_no_llenca(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response('service unavailable', 503)]);

        $r = app(TsaClient::class)->stamp($this->arrel());
        $this->assertSame('error', $r['status']);
        $this->assertStringContainsString('503', $r['error']);
    }

    public function test_una_resposta_buida_de_la_tsa_no_es_dona_per_bona(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response('', 200)]);

        $this->assertSame('error', app(TsaClient::class)->stamp($this->arrel())['status']);
    }

    public function test_una_tsa_inaccessible_no_llenca(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => fn () => throw new ConnectionException('cURL error 28: timeout')]);

        $r = app(TsaClient::class)->stamp($this->arrel());
        $this->assertSame('error', $r['status']);
        $this->assertNotEmpty($r['error']);
    }

    public function test_sense_openssl_al_sistema_no_llenca(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa',
                'integrity.tsa.openssl' => '/no/hi/ha/openssl']);

        $r = app(TsaClient::class)->stamp($this->arrel());
        $this->assertSame('error', $r['status']);
        Http::assertNothingSent();
    }

    public function test_la_basic_auth_s_envia_si_esta_configurada(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa',
                'integrity.tsa.user' => 'crt', 'integrity.tsa.password' => 'clau']);
        Http::fake(['tsa.test/*' => Http::response("\x30\x0a" . random_bytes(8), 200)]);

        app(TsaClient::class)->stamp($this->arrel());

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization',
            'Basic ' . base64_encode('crt:clau')));
    }

    public function test_no_deixa_fitxers_temporals_enrere(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response("\x30\x0a" . random_bytes(8), 200)]);

        $abans = count(glob(sys_get_temp_dir() . '/tsa_*') ?: []);
        app(TsaClient::class)->stamp($this->arrel());
        $this->assertSame($abans, count(glob(sys_get_temp_dir() . '/tsa_*') ?: []),
            'la petició i la resposta contenen l\'arrel del dia: no poden quedar-se a /tmp');
    }

    // ══ TSA dins del tancament diari ═════════════════════════════════════════

    public function test_el_tancament_diari_segella_amb_la_tsa(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fontLocal($dia);
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response("\x30\x0a" . random_bytes(8), 200)]);

        $segell = $this->llibre->sealDay($dia, true)->fresh();

        $this->assertSame('segellat', $segell->tsa_status);
        $this->assertNotEmpty($segell->tsa_token);
        $this->assertNull($segell->tsa_error);
        Http::assertSentCount(1);
    }

    public function test_un_dia_ja_segellat_no_gasta_un_segon_credit(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fontLocal($dia);
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response("\x30\x0a" . random_bytes(8), 200)]);

        $this->llibre->sealDay($dia, true);
        $this->llibre->sealDay($dia, true);
        $this->llibre->sealDay($dia, true);

        Http::assertSentCount(1);
    }

    public function test_un_dia_sense_esdeveniments_no_gasta_credit(): void
    {
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response("\x30\x0a" . random_bytes(8), 200)]);

        $segell = $this->llibre->sealDay(Carbon::parse('2026-05-20'), true)->fresh();

        $this->assertSame(0, $segell->events_count);
        $this->assertSame('pendent', $segell->tsa_status);
        Http::assertNothingSent();
    }

    public function test_una_tsa_que_falla_deixa_el_dia_segellat_internament(): void
    {
        $dia = Carbon::parse('2026-05-20');
        $this->fontLocal($dia);
        config(['integrity.tsa.url' => 'https://tsa.test/tsa']);
        Http::fake(['tsa.test/*' => Http::response('down', 500)]);

        $segell = $this->llibre->sealDay($dia, true)->fresh();

        $this->assertSame('error', $segell->tsa_status);
        $this->assertNotEmpty($segell->tsa_error);
        $this->assertNotEmpty($segell->root_hash, 'la cadena interna s\'ha de tancar igualment');
        $this->assertTrue($this->llibre->verify($dia)['ok'],
            'que la FNMT estigui caiguda no invalida la integritat interna del dia');
    }
}
