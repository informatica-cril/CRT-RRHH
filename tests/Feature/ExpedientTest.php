<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Expedient del treballador — bloc F del pla de maduresa.
 *
 * PER QUÈ AQUESTES PROVES I NO ALTRES
 *   `ExpedientController` no tenia CAP prova (mesurat el 01-08-2026), i és el mòdul amb
 *   conseqüències laborals: alimenta un procediment disciplinari, que pot acabar en sanció
 *   o acomiadament. Un error aquí no és una pantalla lletja.
 *
 *   El que fa el controlador és poc —proxy autenticat cap a l'API F1 de domi— i precisament
 *   per això el que s'ha de fixar és el PERÍMETRE, que és tot el que aporta:
 *
 *     1. QUI hi entra. Només admin i RRHH. Que un coordinador pugui llegir l'historial de
 *        faltes de qui té a càrrec canvia la relació laboral, i el motor disciplinari viu a
 *        domi justament per mantenir-ho separat.
 *     2. QUÈ passa si domi no respon. Un expedient a mitges és pitjor que cap: qui el mira
 *        ha de saber que li falten dades, no veure una pantalla buida i concloure que aquella
 *        persona no té cap incidència.
 *     3. QUE NO S'INVENTA RES. El controlador retorna el que diu domi, sense afegir-hi ni
 *        qualificar. La qualificació i la sanció són humanes (RGPD art. 22) i es fan a domi.
 *
 * No es crida domi de veritat: Http::fake. El que es prova és el perímetre, no la xarxa.
 */
class ExpedientTest extends TestCase
{
    use RefreshDatabase;

    private const PROF = 'A.Martinez';

    protected function setUp(): void
    {
        parent::setUp();
        /* env() de Laravel NO llegeix putenv(): l'adaptador està desactivat a posta perquè
           l'entorn sigui immutable. Les proves han de tocar $_ENV i $_SERVER, que són els
           que sí que consulta. Amb putenv sol, aquí manaria el .env real de la màquina i la
           prova diria el que digui aquella màquina, no el que diu el codi. */
        $this->env('DOMI_EXPEDIENT_TOKEN', 'token-de-prova');
        $this->env('DOMI_EXPEDIENT_URL', 'https://domi.test/api/rrhh_expedient.php');
        $this->env('DOMI_EXPEDIENT_DETALL_URL', 'https://domi.test/api/rrhh_expedient_detall.php');
    }

    private function env(string $clau, string $valor): void
    {
        $_ENV[$clau] = $_SERVER[$clau] = $valor;
        putenv("$clau=$valor");
        // Els controladors llegeixen config(), no env() (amb config:cache env() torna null).
        $mapa = ['DOMI_EXPEDIENT_TOKEN' => 'services.domi.token', 'DOMI_EXPEDIENT_URL' => 'services.domi.expedient_url',
                 'DOMI_EXPEDIENT_DETALL_URL' => 'services.domi.expedient_detall_url'];
        if (isset($mapa[$clau])) {
            config([$mapa[$clau] => $valor]);
        }
    }

    private function comA(string $rol): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => $rol]));
    }

    /* ── 1. El perímetre d'accés ────────────────────────────────────────────────────── */

    public function test_nomes_admin_i_rrhh_veuen_un_expedient(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'blocs' => []], 200)]);

        foreach (['admin', 'hr'] as $rol) {
            $this->comA($rol);
            $this->getJson('/api/v1/expedient/' . self::PROF)->assertOk();
        }

        /* Coordinació dirigeix la feina; l'historial de faltes de qui té a càrrec, no.
           Si algun dia això s'obre, ha de ser una decisió explícita i aquesta prova ho dirà. */
        foreach (['coordinator', 'worker'] as $rol) {
            $this->comA($rol);
            $this->getJson('/api/v1/expedient/' . self::PROF)->assertForbidden();
        }
    }

    public function test_el_detall_per_fet_te_el_mateix_perimetre_que_el_resum(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'fets' => []], 200)]);

        $this->comA('hr');
        $this->getJson('/api/v1/expedient/' . self::PROF . '/detall')->assertOk();

        foreach (['coordinator', 'worker'] as $rol) {
            $this->comA($rol);
            $this->getJson('/api/v1/expedient/' . self::PROF . '/detall')->assertForbidden();
        }
    }

    public function test_sense_sessio_no_s_hi_arriba(): void
    {
        $this->getJson('/api/v1/expedient/' . self::PROF)->assertUnauthorized();
    }

    /* ── 2. Quan domi no respon ─────────────────────────────────────────────────────── */

    /**
     * Un expedient a mitges és pitjor que cap. Si domi falla i això tornés 200 amb una
     * llista buida, qui el mirés conclouria que aquella persona no té cap incidència —i
     * podria decidir sobre ella amb aquesta idea.
     */
    public function test_si_domi_falla_es_diu_i_NO_es_torna_un_expedient_buit(): void
    {
        Http::fake(['*' => Http::response(['error' => 'petada'], 500)]);
        $this->comA('admin');

        $r = $this->getJson('/api/v1/expedient/' . self::PROF);

        $r->assertStatus(424)->assertJsonPath('ok', false);
        $this->assertNotSame(200, $r->status(), 'Un error de domi no pot semblar un expedient net.');
    }

    public function test_si_domi_no_es_accessible_tambe_es_diu(): void
    {
        Http::fake(function () { throw new \Illuminate\Http\Client\ConnectionException('sense xarxa'); });
        $this->comA('admin');

        $this->getJson('/api/v1/expedient/' . self::PROF)
            ->assertStatus(424)
            ->assertJsonPath('ok', false);
    }

    /** Sense token configurat no s'ha de fer cap crida a cegues: es diu què falta. */
    public function test_sense_token_configurat_ho_diu_clar(): void
    {
        $this->env('DOMI_EXPEDIENT_TOKEN', '');
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->comA('admin');

        $this->getJson('/api/v1/expedient/' . self::PROF)
            ->assertStatus(500)
            ->assertJsonPath('ok', false);

        Http::assertNothingSent();
    }

    /* ── 3. Que no s'hi afegeix res pel camí ────────────────────────────────────────── */

    /**
     * El controlador és un proxy: la qualificació i la sanció són humanes i es fan a domi
     * (RGPD art. 22). Si algun dia algú hi afegís un càlcul —una puntuació, una etiqueta de
     * gravetat—, això seria una decisió automatitzada sobre una persona presa aquí, i
     * aquesta prova ho posaria en vermell.
     */
    public function test_es_retorna_EXACTAMENT_el_que_diu_domi(): void
    {
        $delDomi = [
            'ok' => true,
            'professional' => self::PROF,
            'blocs' => [
                ['clau' => 'absencies', 'objectiu' => 3, 'font' => 'domi'],
                ['clau' => 'retards',   'objectiu' => 1, 'font' => 'domi'],
            ],
        ];
        Http::fake(['*' => Http::response($delDomi, 200)]);
        $this->comA('hr');

        $r = $this->getJson('/api/v1/expedient/' . self::PROF)->assertOk();

        $this->assertSame($delDomi, $r->json(),
            'S\'ha afegit o tret alguna cosa entre domi i qui mira l\'expedient.');
    }

    /** Les dates del filtre han d'arribar a domi: si es perdessin, es veuria tot l'historial. */
    public function test_el_periode_consultat_arriba_a_domi(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->comA('admin');

        $this->getJson('/api/v1/expedient/' . self::PROF . '?desde=2026-01-01&fins=2026-06-30')->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'desde=2026-01-01')
                && str_contains($request->url(), 'fins=2026-06-30')
                && str_contains($request->url(), 'professional=' . self::PROF);
        });
    }

    /**
     * El nom del professional el posa qui consulta. Ha d'arribar a domi tal com és, també si
     * porta caràcters que s'han d'escapar: si es partís, es consultaria l'expedient d'una
     * altra persona —o de ningú— sense que es notés.
     */
    public function test_un_nom_amb_caracters_especials_no_es_parteix(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->comA('admin');

        $this->getJson('/api/v1/expedient/' . rawurlencode('J. Gomez & Fill'))->assertOk();

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'J. Gomez & Fill'));
    }
}
