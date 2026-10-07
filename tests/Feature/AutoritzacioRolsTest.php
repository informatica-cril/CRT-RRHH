<?php

namespace Tests\Feature;

use App\Models\DisciplinaryDocument;
use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * MATRIU D'AUTORITZACIÓ rol × ruta sensible (Fase A de maduresa).
 *
 * Aquesta suite ÉS la política d'accés escrita en codi: si algú obre un forat al
 * backend (o afluixa un middleware), aquí es posa en vermell. El frontend pot
 * amagar, però la decisió és SEMPRE del backend.
 *
 * Rols del sistema (User::isStaff = admin|coordinator|hr):
 *   admin       → super-usuari (l'equivalent de «Direccio» a domi).
 *   coordinator → gestió operativa (fichatges, zones, absències, vigilància xat).
 *   hr          → Responsable RRHH: casi-admin, PERÒ sense nòmines ni configuració
 *                 de sistema ni vigilància del xat (mateix criteri que blockHr al
 *                 router de Vue). SÍ té expedient, disciplinari, compliment i usuaris.
 *   worker      → només les seves dades.
 *   service     → compte màquina de domi: només /domi/* i dues lectures de plantilla
 *                 (RestrictServiceRole).
 *
 * Convenció de les asercions:
 *   nega()   → 403 exacte (el middleware/policy talla ABANS del controlador).
 *   permet() → qualsevol cosa MENYS 403 (200/201/404/409/422 són "he passat el
 *              control d'accés"; no acoblem la prova a la validació de negoci).
 */
class AutoritzacioRolsTest extends TestCase
{
    use RefreshDatabase;

    /** Rols que NO han de poder tocar res de gestió. */
    private const NO_GESTIO = ['worker', 'service'];

    protected function setUp(): void
    {
        parent::setUp();
        // Cap crida real a domi des dels tests (l'expedient i la conciliació la fan).
        Http::fake(['*' => Http::response(null, 500)]);
    }

    private function make(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => ucfirst($role) . ' ' . uniqid(),
            'email' => $role . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ], $extra));
    }

    private function crida(string $method, string $uri, array $body = [])
    {
        return match (strtoupper($method)) {
            'GET' => $this->getJson($uri),
            'POST' => $this->postJson($uri, $body),
            'PUT' => $this->putJson($uri, $body),
            'DELETE' => $this->deleteJson($uri),
        };
    }

    /** El rol $rol NO pot arribar a $uri: 403 abans d'entrar al controlador. */
    private function nega(string $rol, string $method, string $uri, array $body = []): void
    {
        Sanctum::actingAs($this->make($rol));
        $r = $this->crida($method, $uri, $body);
        $this->assertSame(403, $r->getStatusCode(),
            "FORAT: {$rol} ha rebut {$r->getStatusCode()} a {$method} {$uri} (esperat 403)");
    }

    /** El rol $rol SÍ ha de passar el control d'accés de $uri (no 403). */
    private function permet(string $rol, string $method, string $uri, array $body = []): void
    {
        /* El compte de servei entra amb el bitllet TAL COM s'emet a producció:
           amb els permisos acotats, no amb ["*"]. Si aquí li donéssim comodí, la
           prova diria que el pont funciona sense provar el bitllet real. */
        Sanctum::actingAs($this->make($rol),
            $rol === 'service' ? \App\Support\DomiScopes::permisos() : []);
        $r = $this->crida($method, $uri, $body);
        $this->assertNotSame(403, $r->getStatusCode(),
            "TANCAT DE MÉS: {$rol} ha rebut 403 a {$method} {$uri} i hauria de poder-hi entrar");
    }

    private function nomes(array $permesos, string $method, string $uri, array $body = []): void
    {
        foreach (['admin', 'coordinator', 'hr', 'worker', 'service'] as $rol) {
            in_array($rol, $permesos, true)
                ? $this->permet($rol, $method, $uri, $body)
                : $this->nega($rol, $method, $uri, $body);
        }
    }

    // ── NÒMINES: admin i el titular. hr NO (decisió de producte, = blockHr) ──

    public function test_nomines_llistat_i_alta_nomes_admin(): void
    {
        $this->nomes(['admin'], 'GET', '/api/v1/payroll-files');
        $this->nomes(['admin'], 'POST', '/api/v1/payroll-files', []);
        $this->nomes(['admin'], 'POST', '/api/v1/payroll-files/bulk', []);
    }

    public function test_nomines_daltri_nomes_admin(): void
    {
        $titular = $this->make('worker');
        foreach (['coordinator', 'hr', 'worker', 'service'] as $rol) {
            $this->nega($rol, 'GET', "/api/v1/payroll-files/worker/{$titular->id}");
        }
        $this->permet('admin', 'GET', "/api/v1/payroll-files/worker/{$titular->id}");
    }

    public function test_el_titular_si_veu_les_seves_nomines(): void
    {
        $w = $this->make('worker');
        Sanctum::actingAs($w);
        $this->getJson("/api/v1/payroll-files/worker/{$w->id}")->assertStatus(200);
    }

    public function test_fitxa_de_nomina_concreta_nomes_admin_o_titular(): void
    {
        $titular = $this->make('worker');
        $nomina = Payroll::create([
            'user_id' => $titular->id, 'title' => 'Nòmina', 'month' => 7, 'year' => 2026,
            'payroll_base64' => 'AAAA',
        ]);

        foreach (['coordinator', 'hr', 'worker'] as $rol) {
            $this->nega($rol, 'GET', "/api/v1/payroll-files/{$nomina->id}");
        }
        $this->permet('admin', 'GET', "/api/v1/payroll-files/{$nomina->id}");

        Sanctum::actingAs($titular);
        $this->getJson("/api/v1/payroll-files/{$nomina->id}")->assertStatus(200);
    }

    public function test_signar_una_nomina_es_acte_personal_del_titular(): void
    {
        $titular = $this->make('worker');
        $nomina = Payroll::create([
            'user_id' => $titular->id, 'title' => 'Nòmina', 'month' => 7, 'year' => 2026,
            'payroll_base64' => 'AAAA',
        ]);

        // Ningú signa en nom del treballador, ni l'admin.
        foreach (['admin', 'coordinator', 'hr', 'worker'] as $rol) {
            $this->nega($rol, 'POST', "/api/v1/payroll-files/{$nomina->id}/sign", ['signature_hash' => 'ff']);
        }
        $this->assertNull($nomina->fresh()->signed_at, 'cap tercer ha pogut signar la nòmina');

        Sanctum::actingAs($titular);
        $this->postJson("/api/v1/payroll-files/{$nomina->id}/sign", ['signature_hash' => 'ff'])
            ->assertStatus(200);
        $this->assertNotNull($nomina->fresh()->signed_at);
    }

    public function test_esborrar_nomina_nomes_admin(): void
    {
        $nomina = Payroll::create([
            'user_id' => $this->make('worker')->id, 'title' => 'N', 'month' => 7, 'year' => 2026,
            'payroll_base64' => 'AAAA',
        ]);
        foreach (['coordinator', 'hr', 'worker', 'service'] as $rol) {
            $this->nega($rol, 'DELETE', "/api/v1/payroll-files/{$nomina->id}");
        }
        $this->assertNotNull($nomina->fresh(), 'la nòmina no s\'ha d\'haver esborrat');
    }

    // ── AUDITORIA ENS i INTEGRITAT (segell FNMT): només admin ──

    public function test_auditoria_nomes_admin(): void
    {
        $this->nomes(['admin'], 'GET', '/api/v1/audit-logs');
        $this->nomes(['admin'], 'POST', '/api/v1/audit-logs', []);
    }

    public function test_integritat_nomes_admin(): void
    {
        $this->nomes(['admin'], 'GET', '/api/v1/integrity/seals');
        $this->nomes(['admin'], 'POST', '/api/v1/integrity/seal-now', []);
    }

    // ── EXPEDIENT DEL TREBALLADOR i PROCEDIMENT DISCIPLINARI: admin + hr ──

    public function test_expedient_nomes_admin_i_hr(): void
    {
        $prof = $this->make('worker');
        $this->nomes(['admin', 'hr'], 'GET', "/api/v1/expedient/{$prof->id}");
        $this->nomes(['admin', 'hr'], 'GET', "/api/v1/expedient/{$prof->id}/detall");
    }

    public function test_disciplinari_nomes_admin_i_hr(): void
    {
        $this->nomes(['admin', 'hr'], 'GET', '/api/v1/disciplinary/cases');
        $this->nomes(['admin', 'hr'], 'POST', '/api/v1/disciplinary/cases', []);
        $this->nomes(['admin', 'hr'], 'GET', '/api/v1/disciplinary/suggestions');
        $this->nomes(['admin', 'hr'], 'POST', '/api/v1/disciplinary/elements', []);
    }

    public function test_signar_escrit_disciplinari_nomes_admin_i_hr(): void
    {
        $treballador = $this->make('worker');
        $doc = DisciplinaryDocument::create([
            'professional' => $treballador->name, 'user_id' => $treballador->id,
            'tipus' => 'amonestacio', 'contingut' => 'text', 'estat' => 'esborrany',
        ]);
        foreach (['coordinator', 'worker', 'service'] as $rol) {
            $this->nega($rol, 'POST', "/api/v1/disciplinary/documents/{$doc->id}/sign");
        }
        $this->assertSame('esborrany', $doc->fresh()->estat);
    }

    public function test_acusar_recepcio_dun_escrit_nomes_el_destinatari(): void
    {
        $destinatari = $this->make('worker');
        $doc = DisciplinaryDocument::create([
            'professional' => $destinatari->name, 'user_id' => $destinatari->id,
            'tipus' => 'amonestacio', 'contingut' => 'text', 'estat' => 'notificat',
            'notificat_ts' => now(),
        ]);

        // Ni un altre treballador ni la gestió poden acusar recepció en nom seu.
        foreach (['admin', 'coordinator', 'hr', 'worker'] as $rol) {
            $this->nega($rol, 'POST', "/api/v1/disciplinary/documents/{$doc->id}/acknowledge");
        }
        $this->assertNull($doc->fresh()->acus_ts, 'ningú ha pogut acusar recepció per ell');

        Sanctum::actingAs($destinatari);
        $this->postJson("/api/v1/disciplinary/documents/{$doc->id}/acknowledge")->assertStatus(200);
        $this->assertNotNull($doc->fresh()->acus_ts);
    }

    // ── COMPLIMENT / GOVERNANÇA: gestió = admin + hr; el treballador, el seu ──

    public function test_compliment_gestio_nomes_admin_i_hr(): void
    {
        $this->nomes(['admin', 'hr'], 'GET', '/api/v1/compliance');
        $this->nomes(['admin', 'hr'], 'POST', '/api/v1/compliance', []);
    }

    public function test_el_treballador_si_veu_els_seus_pendents_de_compliment(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $this->getJson('/api/v1/compliance/pending')->assertStatus(200);
    }

    // ── USUARIS ──

    public function test_llistat_de_plantilla_nomes_gestio(): void
    {
        $this->nomes(['admin', 'hr'], 'GET', '/api/v1/users');
    }

    public function test_alta_i_baixa_dusuaris_nomes_admin_i_hr(): void
    {
        $victima = $this->make('worker');
        $this->nomes(['admin', 'hr'], 'POST', '/api/v1/users', []);
        foreach (['coordinator', 'worker', 'service'] as $rol) {
            $this->nega($rol, 'DELETE', "/api/v1/users/{$victima->id}");
        }
        $this->assertNotNull($victima->fresh());
    }

    public function test_fitxa_daltri_nomes_gestio(): void
    {
        $altre = $this->make('worker');
        $this->nega('worker', 'GET', "/api/v1/users/{$altre->id}");
        $this->nega('service', 'GET', "/api/v1/users/{$altre->id}");
        $this->nega('coordinator', 'GET', "/api/v1/users/{$altre->id}"); // coordinació ja no és gestió
        foreach (['admin', 'hr'] as $rol) {
            $this->permet($rol, 'GET', "/api/v1/users/{$altre->id}");
        }
    }

    /**
     * CANVI DE POLÍTICA, 01-08-2026 (Direcció): «el 2FA ha de poder activar-lo l'admin O
     * RRHH per a cada usuari». Abans era només d'admin, i això volia dir que donar d'alta
     * algú i deixar-lo operatiu eren dues persones diferents.
     *
     * Aquesta prova va saltar en vermell en fer el canvi, que és exactament la seva feina:
     * aquesta suite ÉS la política d'accés escrita en codi, i una política no es canvia
     * afluixant una asserció sinó deixant constància de qui ho ha decidit i quan.
     *
     * La ratlla es queda on era per a la resta: coordinació NO. Decidir com entra una
     * persona a l'aplicació és administració de comptes, no direcció del dia a dia.
     */
    public function test_segon_factor_daltri_nomes_admin_i_hr(): void
    {
        $victima = $this->make('worker');
        $this->nomes(['admin', 'hr'], 'PUT', "/api/v1/users/{$victima->id}/2fa", []);
    }

    // ── VIGILÀNCIA DEL XAT: admin + coordinator. hr NO (= blockHr) ──

    public function test_vigilancia_del_xat_exclou_hr(): void
    {
        $this->nomes(['admin'], 'GET', '/api/v1/chat/conversations/all');
        $this->nomes(['admin'], 'GET', '/api/v1/chat/search?q=a');
        $this->nomes(['admin'], 'GET', '/api/v1/chat/alerts');
        $this->nomes(['admin'], 'GET', '/api/v1/chat/alerts/unreviewed');
    }

    public function test_politica_forense_del_xat_nomes_admin(): void
    {
        $this->nomes(['admin'], 'POST', '/api/v1/chat/settings', []);
    }

    public function test_ningu_falseja_la_identitat_al_xat_pel_cos_de_la_peticio(): void
    {
        $victima = $this->make('worker', ['chat_status' => 'offline']);
        $atacant = $this->make('worker');
        Sanctum::actingAs($atacant);

        // presence/heartbeat/accept-policy portaven user_id al cos: la identitat
        // ha de sortir del token, mai del cos.
        $this->postJson('/api/v1/chat/presence', ['user_id' => $victima->id, 'status' => 'online']);
        $this->assertSame('offline', $victima->fresh()->chat_status,
            'no es pot canviar la presència d\'un altre usuari');

        $this->postJson('/api/v1/chat/accept-policy', ['user_id' => $victima->id]);
        $this->assertDatabaseMissing('chat_policy_acceptances', ['user_id' => $victima->id]);
        $this->assertDatabaseHas('chat_policy_acceptances', ['user_id' => $atacant->id]);

        $this->postJson('/api/v1/chat/heartbeat', ['user_id' => $victima->id]);
        $this->assertNull($victima->fresh()->last_chat_heartbeat);
    }

    public function test_comptador_de_no_llegits_daltri_tancat_al_worker(): void
    {
        $altre = $this->make('worker');
        $this->nega('worker', 'GET', "/api/v1/chat/unread-count/{$altre->id}");
    }

    // ── CONFIGURACIÓ DE SISTEMA: escriptura admin; lectura sense hr (= blockHr) ──

    public function test_configuracio_de_sistema_escriptura_nomes_admin(): void
    {
        $this->nomes(['admin'], 'PUT', '/api/v1/break-settings', []);
        $this->nomes(['admin'], 'GET', '/api/v1/mail-settings');
        $this->nomes(['admin'], 'PUT', '/api/v1/mail-settings', []);
        $this->nomes(['admin'], 'POST', '/api/v1/mail-settings/test', []);
    }

    public function test_la_llista_de_pauses_de_tota_la_plantilla_es_nomes_gestio(): void
    {
        // La configuració global SÍ la llegeix tothom (filtrada, veure més avall),
        // però la llista de la plantilla amb els seus overrides no s'obre.
        $this->nomes(['admin'], 'GET', '/api/v1/break-settings/workers');
    }

    // ── LECTURES FILTRADES: ni tancades de més ni obertes de més ──
    //
    // break-settings i chat/settings els consumeix el PROPI portal del treballador i
    // portaven role:admin,coordinator. El 403 arribava embolicat en try/catch: no es
    // veia cap error, es degradava la funció en silenci (la pausa obligatòria no
    // s'arribava a programar; el modal de la política de xat sortia en blanc i
    // s'acceptava una política que no es mostrava).
    //
    // El criteri NO és obrir l'endpoint, sinó retornar una càrrega filtrada: la
    // gestió veu la configuració sencera, la resta només el mínim funcional.

    /** Camps del temporitzador de pausa que el treballador ha de rebre, i cap més. */
    private const CAMPS_PAUSA_TREBALLADOR = [
        'enabled', 'threshold_hours', 'break_duration_minutes',
        'break_start_mode', 'break_start_offset_minutes', 'break_start_fixed_time',
    ];

    public function test_la_config_de_pausa_la_llegeix_tothom_menys_el_compte_de_servei(): void
    {
        $this->nomes(['admin', 'coordinator', 'hr', 'worker'], 'GET', '/api/v1/break-settings');
    }

    public function test_el_treballador_rep_els_camps_que_necessita_per_a_la_pausa(): void
    {
        Sanctum::actingAs($this->make('worker'));
        $carrega = $this->getJson('/api/v1/break-settings')->assertStatus(200)->json();

        foreach (self::CAMPS_PAUSA_TREBALLADOR as $camp) {
            $this->assertArrayHasKey($camp, $carrega,
                "sense '{$camp}' el WorkerDashboard no pot programar la pausa obligatòria");
        }
    }

    public function test_la_carrega_de_pausa_filtrada_no_porta_res_de_mes_ni_deforma_valors(): void
    {
        Sanctum::actingAs($this->make('admin'));
        $sencera = $this->getJson('/api/v1/break-settings')->assertStatus(200)->json();

        $esperats = self::CAMPS_PAUSA_TREBALLADOR;
        sort($esperats);

        foreach (['worker', 'hr'] as $rol) {
            Sanctum::actingAs($this->make($rol));
            $filtrada = $this->getJson('/api/v1/break-settings')->assertStatus(200)->json();

            $rebuts = array_keys($filtrada);
            sort($rebuts);
            $this->assertSame($esperats, $rebuts,
                "la càrrega de pausa de {$rol} ha de ser EXACTAMENT l'allowlist "
                . '(ni id, ni timestamps, ni camps de configuració que no fa servir)');

            // Mateix valor i mateix FORMAT que veu la gestió. break_start_fixed_time
            // té cast 'datetime:H:i:s' i el client fa substring(0,5) sobre l'hora: si
            // el filtre retornés un Carbon en ISO, la pausa es programaria a una hora
            // falsa en comptes de fallar de cara.
            foreach (self::CAMPS_PAUSA_TREBALLADOR as $camp) {
                $this->assertSame($sencera[$camp], $filtrada[$camp],
                    "'{$camp}' ha de valer el mateix per a {$rol} que per a la gestió");
            }
        }
    }

    public function test_la_politica_del_xat_la_llegeix_tothom_menys_el_compte_de_servei(): void
    {
        $this->nomes(['admin', 'coordinator', 'hr', 'worker'], 'GET', '/api/v1/chat/settings');
    }

    public function test_el_treballador_veu_la_politica_pero_no_les_paraules_vigilades(): void
    {
        \App\Models\ChatSetting::create([
            'forensic_keywords' => ['assetjament', 'amenaça'],
            'chat_policy_text' => 'Política d\'ús del xat corporatiu — CRT',
            'require_policy_acceptance' => true,
        ]);

        foreach (['worker', 'hr'] as $rol) {
            Sanctum::actingAs($this->make($rol));
            $carrega = $this->getJson('/api/v1/chat/settings')->assertStatus(200)->json();

            $this->assertSame(['chat_policy_text'], array_keys($carrega),
                "la config de xat de {$rol} ha de portar NOMÉS el text de la política");
            $this->assertSame('Política d\'ús del xat corporatiu — CRT',
                $carrega['chat_policy_text'],
                "{$rol} no pot acceptar una política que no se li mostra");
        }

        // I la gestió sí conserva la vigilància sencera.
        Sanctum::actingAs($this->make('admin'));
        $this->getJson('/api/v1/chat/settings')
            ->assertStatus(200)
            ->assertJsonPath('forensic_keywords', ['assetjament', 'amenaça']);
    }

    public function test_les_paraules_vigilades_per_defecte_tampoc_sescapen_sense_config(): void
    {
        // Sense fila a chat_settings el controlador retorna uns valors per defecte que
        // TAMBÉ inclouen forensic_keywords: el filtre ha de cobrir aquesta branca.
        $this->assertSame(0, \App\Models\ChatSetting::count());

        Sanctum::actingAs($this->make('worker'));
        $carrega = $this->getJson('/api/v1/chat/settings')->assertStatus(200)->json();

        $this->assertArrayNotHasKey('forensic_keywords', $carrega,
            'les paraules vigilades per defecte no poden sortir per la porta del fons');
        $this->assertNotEmpty($carrega['chat_policy_text']);
    }

    // ── INTEGRACIÓ /domi/*: NOMÉS el compte de servei ──

    public function test_domi_nomes_compte_de_servei(): void
    {
        $w = $this->make('worker', ['dni' => 'X1234567Z']);
        foreach (['admin', 'coordinator', 'hr', 'worker'] as $rol) {
            $this->nega($rol, 'POST', '/api/v1/domi/jornada/start', ['user_id' => $w->id]);
            $this->nega($rol, 'POST', '/api/v1/domi/jornada/stop', ['user_id' => $w->id]);
            $this->nega($rol, 'POST', '/api/v1/domi/hito', ['user_id' => $w->id]);
            $this->nega($rol, 'GET', "/api/v1/domi/perfil/{$w->id}");
            $this->nega($rol, 'GET', '/api/v1/domi/incidencies-obertes');
        }
        $this->assertSame(0, \App\Models\WorkLog::where('user_id', $w->id)->count(),
            'cap fichatge creat per un tercer via /domi/*');

        $this->permet('service', 'POST', '/api/v1/domi/jornada/start', ['user_id' => $w->id]);
        $this->permet('service', 'GET', "/api/v1/domi/perfil/{$w->id}");
    }

    // ── ASSIGNACIÓ DE CENTRES/ZONES: gestió ──

    public function test_assignacio_de_centres_nomes_gestio(): void
    {
        $centre = \App\Models\AmbulatoryCenter::create(['name' => 'Centre Prova', 'active' => true]);
        $victima = $this->make('worker');

        foreach (self::NO_GESTIO as $rol) {
            $this->nega($rol, 'POST', "/api/v1/ambulatory-centers/{$centre->id}/assign",
                ['user_id' => $victima->id, 'valid_from' => '2026-01-01']);
            $this->nega($rol, 'POST', "/api/v1/ambulatory-centers/{$centre->id}/remove-worker",
                ['user_id' => $victima->id]);
        }
        $this->assertSame(0, $centre->users()->count(),
            'un no-gestor no ha pogut assignar ningú al centre');

        foreach (['admin', 'hr'] as $rol) {
            $this->permet($rol, 'POST', "/api/v1/ambulatory-centers/{$centre->id}/assign",
                ['user_id' => $victima->id, 'valid_from' => '2026-01-01']);
        }
    }

    // ── El compte de SERVEI no té res més que la seva superfície ──

    public function test_compte_de_servei_acotat_a_la_seva_superficie(): void
    {
        Sanctum::actingAs($this->make('service'), \App\Support\DomiScopes::permisos());

        // Lectures de plantilla permeses (RRHH és font de veritat per a domi).
        $this->getJson('/api/v1/users/bulk-index')->assertStatus(200);
        $this->getJson('/api/v1/users/me')->assertStatus(200);

        // Tota la resta, tancada — inclosos catàlegs i escriptures.
        foreach ([
            ['GET', '/api/v1/users'], ['GET', '/api/v1/work-logs'], ['GET', '/api/v1/absences'],
            ['GET', '/api/v1/documents'], ['GET', '/api/v1/holidays'], ['GET', '/api/v1/specialties'],
            ['GET', '/api/v1/zones'], ['GET', '/api/v1/work-schedules'],
            ['GET', '/api/v1/chat/conversations'], ['POST', '/api/v1/work-logs'],
        ] as [$m, $u]) {
            $r = $this->crida($m, $u);
            $this->assertSame(403, $r->getStatusCode(),
                "FORAT: el compte de servei ha rebut {$r->getStatusCode()} a {$m} {$u}");
        }
    }

    /**
     * L'abast del bitllet no és decoratiu: un bitllet de servei que no porta el
     * permís d'una família de rutes no hi arriba, encara que el rol sí que hi
     * arribaria. Sense això, "acotar el token" seria només un comentari.
     */
    public function test_el_bitllet_de_servei_sense_permis_no_passa(): void
    {
        Sanctum::actingAs($this->make('service'), ['domi:plantilla']);

        // El que SÍ que porta.
        $this->getJson('/api/v1/domi/festius')->assertStatus(200);

        // El que NO porta: 403 encara que el rol sigui service.
        foreach ([
            ['POST', '/api/v1/domi/hito'], ['POST', '/api/v1/domi/rendiment'],
            ['POST', '/api/v1/domi/zones'], ['POST', '/api/v1/domi/extraordinaries'],
            ['POST', '/api/v1/servei/senyals'],
        ] as [$m, $u]) {
            $this->assertSame(403, $this->crida($m, $u)->getStatusCode(),
                "FORAT: {$m} {$u} passa sense el permís que li toca");
        }
    }

    /**
     * Una ruta nova sota /domi/* no ha de néixer oberta: si no és al mapa de
     * DomiScopes, el compte de servei no hi arriba ni amb tots els permisos.
     * sancions-rlt és el cas real: existeix, i domi no la crida enlloc.
     */
    public function test_ruta_de_domi_fora_del_mapa_segueix_tancada(): void
    {
        Sanctum::actingAs($this->make('service'), \App\Support\DomiScopes::permisos());

        $this->assertNull(\App\Support\DomiScopes::permisDe('api/v1/domi/sancions-rlt'));
        $this->getJson('/api/v1/domi/sancions-rlt')->assertStatus(403);
    }

    // ── NO TANCAR DE MÉS: hr ha de poder fer la seva feina ──

    public function test_hr_conserva_les_seves_funcions(): void
    {
        foreach ([
            ['GET', '/api/v1/users'],
            ['GET', '/api/v1/users/bulk-index'],
            ['POST', '/api/v1/users'],
            ['GET', '/api/v1/documents'],
            ['GET', '/api/v1/absences'],
            ['GET', '/api/v1/excedencias'],
            ['GET', '/api/v1/auth-codes'],
            ['GET', '/api/v1/work-logs'],
            ['GET', '/api/v1/conciliacio'],
            ['GET', '/api/v1/onboarding/profiles'],
            ['GET', '/api/v1/disciplinary/fault-types'],
            ['GET', '/api/v1/compliance'],
            ['POST', '/api/v1/zones'],
            ['POST', '/api/v1/absence-types'],
        ] as [$m, $u]) {
            $this->permet('hr', $m, $u);
        }
    }

    /** Direcció, 07-10-2026: coordinació és un lloc de treball, no un rol de gestió. */
    public function test_coordinacio_ja_no_te_gestio(): void
    {
        foreach ([
            ['GET', '/api/v1/work-logs'],
            ['GET', '/api/v1/absences'],
            ['GET', '/api/v1/auth-codes'],
            ['GET', '/api/v1/users'],
            ['POST', '/api/v1/zones'],
        ] as [$m, $u]) {
            $this->nega('coordinator', $m, $u);
        }
    }

    public function test_el_treballador_conserva_les_seves_vies(): void
    {
        $w = $this->make('worker');
        Sanctum::actingAs($w);

        $this->getJson('/api/v1/users/me')->assertStatus(200);
        $this->getJson('/api/v1/holidays')->assertStatus(200);
        $this->getJson('/api/v1/absence-types')->assertStatus(200);
        $this->getJson('/api/v1/work-schedules')->assertStatus(200);
        $this->getJson('/api/v1/compliance/pending')->assertStatus(200);
        $this->getJson('/api/v1/disciplinary/my-notifications')->assertStatus(200);
        $this->getJson("/api/v1/chat/unread-count/{$w->id}")->assertStatus(200);
        $this->getJson("/api/v1/payroll-files/worker/{$w->id}")->assertStatus(200);
    }
}
