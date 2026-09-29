<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alta sincronitzada a domi des de la fitxa individual (Direcció, 31-07-2026).
 *
 * PER QUÈ CALIA
 *   En posar les apps a producció es tornarà a donar d'alta TOTA la plantilla per Excel.
 *   L'alta individual aprovisiona domi sola, però la CÀRREGA MASSIVA no: vuitanta crides
 *   HTTP no caben dins d'una petició web. Sense un botó a la fitxa, l'única sortida era que
 *   algú entrés al servidor a executar una comanda d'artisan.
 *
 * I SOBRETOT: fins avui «ja té compte a domi» es deduïa de `domi_username IS NOT NULL`,
 * però l'Excel OMPLE aquell camp. O sigui que després d'una càrrega massiva tothom semblava
 * aprovisionat sense que domi hagués creat res, i `domi:provisiona-pendents` no els veia:
 * 41 domiciliaris actius i la comanda només en veia 31. Ara mana `domi_provisioned_at`, que
 * només s'escriu quan domi ha confirmat.
 */
class ProvisionaDomiTest extends TestCase
{
    use RefreshDatabase;

    private function domiciliari(array $extra = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'worker', 'work_type' => 'DOMICILIARIA', 'active' => true,
        ], $extra));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.domi.provisiona_url' => 'https://domi.test/api/provisiona',
                'services.domi.url' => 'https://domi.test',
                'services.domi.token' => 'token-de-prova']);
    }

    public function test_el_boto_crea_el_compte_i_deixa_constancia(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'username' => 'A.Nou'], 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'hr']));

        $u = $this->domiciliari(['domi_username' => null]);
        $this->assertNull($u->domi_provisioned_at);

        $this->postJson("/api/v1/users/{$u->id}/provisiona-domi")->assertOk();

        $u->refresh();
        $this->assertSame('A.Nou', $u->domi_username);
        $this->assertNotNull($u->domi_provisioned_at, 'No ha quedat constància que domi ho confirmés.');
    }

    /**
     * EL CAS QUE VA MOTIVAR TOT AIXÒ. L'Excel ha posat el nom d'usuari, però domi no ha
     * creat res. Si es donés per aprovisionat, aquesta persona no sortiria mai com a
     * pendent i es quedaria sense compte per sempre, sense cap avís.
     */
    public function test_tenir_nom_d_usuari_de_l_excel_NO_es_tenir_compte(): void
    {
        $u = $this->domiciliari(['domi_username' => 'A.DeLExcel']);

        $this->assertNull($u->domi_provisioned_at,
            'Un usuari vingut de l\'Excel no pot comptar com a aprovisionat.');

        $pendents = User::where('role', 'worker')
            ->where('work_type', 'like', 'DOMICILIARIA%')
            ->whereNull('domi_provisioned_at')->where('active', true)->pluck('id');

        $this->assertTrue($pendents->contains($u->id), 'La comanda no el veuria com a pendent.');
    }

    /** Reintentar no ha de fer mal: domi ho tracta com a idempotent. */
    public function test_es_pot_tornar_a_sincronitzar_sense_trencar_res(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'username' => 'A.Nou'], 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $u = $this->domiciliari(['domi_username' => 'A.Nou']);

        $this->postJson("/api/v1/users/{$u->id}/provisiona-domi")->assertOk();
        $this->postJson("/api/v1/users/{$u->id}/provisiona-domi")->assertOk();

        $this->assertSame('A.Nou', $u->fresh()->domi_username, 'El nom d\'usuari no ha de canviar.');
    }

    /** Si domi no respon, es diu; no es marca com a fet. */
    public function test_si_domi_falla_no_es_marca_com_a_creat(): void
    {
        Http::fake(['*' => Http::response(['ok' => false, 'error' => 'domi diu que no'], 500)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $u = $this->domiciliari(['domi_username' => null]);
        $this->postJson("/api/v1/users/{$u->id}/provisiona-domi")->assertStatus(502);

        $this->assertNull($u->fresh()->domi_provisioned_at);
    }

    /** Qui no és domiciliari no té compte a domi: es diu clar, no es fa una crida inútil. */
    public function test_a_qui_no_es_domiciliari_se_li_diu_per_que(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = User::factory()->create(['role' => 'worker', 'work_type' => 'AMBULATORIA', 'active' => true]);

        $this->postJson("/api/v1/users/{$u->id}/provisiona-domi")
            ->assertStatus(422)
            ->assertJsonPath('ok', false);
    }

    public function test_nomes_admin_i_hr_poden_prement_el_boto(): void
    {
        $u = $this->domiciliari();
        foreach (['worker', 'coordinator'] as $rol) {
            Sanctum::actingAs(User::factory()->create(['role' => $rol]));
            $this->postJson("/api/v1/users/{$u->id}/provisiona-domi")->assertForbidden();
        }
    }

    /* ── TANDA SENCERA DES DE LA PANTALLA ───────────────────────────────────────────
       Direcció: «vull dependre d'informàtica el mínim possible». Això és el mateix que
       `php artisan domi:provisiona-pendents --apply`, però sense entrar al servidor. */

    public function test_la_tanda_crea_els_que_falten_i_diu_quants_queden(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'username' => 'X'], 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'hr']));

        for ($i = 0; $i < 3; $i++) { $this->domiciliari(['domi_username' => null]); }

        $this->postJson('/api/v1/users/provisiona-domi-pendents')
            ->assertOk()
            ->assertJsonPath('fets', 3)
            ->assertJsonPath('queden', 0);
    }

    /**
     * Amb més gent que la tanda, se'n fa un tros i es diu que cal tornar-hi. Fer-los tots
     * de cop seria una petició de minuts que el servidor web talla pel mig, i llavors ningú
     * sabria per on anava.
     */
    public function test_amb_mes_gent_que_la_tanda_es_reparteix(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'username' => 'X'], 200)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $total = \App\Http\Controllers\Api\UserController::MAX_TANDA + 3;
        for ($i = 0; $i < $total; $i++) { $this->domiciliari(['domi_username' => null]); }

        $r = $this->postJson('/api/v1/users/provisiona-domi-pendents')->assertOk();

        $r->assertJsonPath('fets', \App\Http\Controllers\Api\UserController::MAX_TANDA);
        $r->assertJsonPath('queden', 3);
        $r->assertJsonPath('continua', true);
    }

    /**
     * Si la tanda no ha fet CAP alta i encara en queden, insistir només repetiria el mateix
     * error: el que falla és domi o la configuració, no la tanda. La pantalla no ha d'entrar
     * en un bucle infinit.
     */
    public function test_si_no_se_n_fa_cap_no_diu_que_es_continui(): void
    {
        Http::fake(['*' => Http::response(['ok' => false, 'error' => 'domi caigut'], 500)]);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->domiciliari(['domi_username' => null]);

        $this->postJson('/api/v1/users/provisiona-domi-pendents')
            ->assertOk()
            ->assertJsonPath('fets', 0)
            ->assertJsonPath('continua', false);
    }

    public function test_la_tanda_tambe_es_nomes_d_admin_i_hr(): void
    {
        foreach (['worker', 'coordinator'] as $rol) {
            Sanctum::actingAs(User::factory()->create(['role' => $rol]));
            $this->postJson('/api/v1/users/provisiona-domi-pendents')->assertForbidden();
        }
    }
}
