<?php

namespace Tests\Feature;

use App\Models\SecondFactorSetting;
use App\Models\User;
use App\Support\SecondFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * La política de segon factor, decidida des de l'aplicació.
 *
 * Direcció (01-08-2026): «vull decidir-ho jo». Vivia a config/security.php —o sigui al .env—
 * i canviar-la volia dir demanar-ho a informàtica i esperar un desplegament: la decisió era
 * de Direcció i el comandament el tenia una altra persona.
 *
 * EL QUE ES FIXA AQUÍ, per ordre d'importància:
 *   1. Que el mode de la base MANA sobre el .env, que és el sentit del canvi.
 *   2. Que si la taula no hi és es cau al .env i NO s'obre cap porta que estigués tancada.
 *   3. Que la pantalla diu, ABANS de decidir, qui es quedaria fora.
 *   4. Que només l'admin ho toca: no és administrar un compte, és decidir com entra tothom.
 */
class PoliticaSegonFactorTest extends TestCase
{
    use RefreshDatabase;

    private function politica(string $mode, array $rols = []): void
    {
        SecondFactorSetting::actual()->update(['mode' => $mode, 'roles' => $rols]);
    }

    /* ── 1. La base mana ────────────────────────────────────────────────────────────── */

    public function test_el_mode_all_obliga_tothom_encara_que_el_env_digui_que_no(): void
    {
        config(['security.second_factor_required' => false]);
        $this->politica(SecondFactorSetting::ALL);

        $u = User::factory()->create(['role' => 'worker']);

        $this->assertTrue(SecondFactor::obligatoriPer($u),
            'La política de la base no ha manat sobre el .env.');
    }

    public function test_el_mode_off_NO_obliga_ningu(): void
    {
        $this->politica(SecondFactorSetting::OFF);

        $this->assertFalse(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'admin'])));
        $this->assertFalse(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'worker'])));
    }

    /**
     * El mode que Direcció necessitarà de debò: començar pels comptes amb més abast
     * —administració i RRHH— sense tocar els 75 professionals de carrer el mateix dia.
     */
    public function test_el_mode_roles_obliga_NOMES_els_de_la_llista(): void
    {
        $this->politica(SecondFactorSetting::ROLES, ['admin', 'hr']);

        $this->assertTrue(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'admin'])));
        $this->assertTrue(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'hr'])));
        $this->assertFalse(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'worker'])));
        $this->assertFalse(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'coordinator'])));
    }

    /** Passar de 'roles' a 'all' no ha de deixar la llista antiga fent efecte. */
    public function test_canviar_de_mode_neteja_la_llista_anterior(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->putJson('/api/v1/seguretat/segon-factor', ['mode' => 'roles', 'roles' => ['hr']])->assertOk();
        $this->putJson('/api/v1/seguretat/segon-factor', ['mode' => 'off'])->assertOk();

        $this->assertSame([], SecondFactorSetting::actual()->roles);
        $this->assertFalse(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'hr'])));
    }

    /* ── 2. El kill-switch del servidor segueix per sobre ───────────────────────────── */

    /**
     * Si algú ha hagut d'apagar el 2FA al servidor per una emergència, la política de la
     * base NO l'ha de tornar a engegar sense que ningú se n'adoni.
     */
    public function test_el_kill_switch_del_servidor_guanya(): void
    {
        $this->politica(SecondFactorSetting::ALL);
        $_ENV['SECOND_FACTOR_OFF'] = $_SERVER['SECOND_FACTOR_OFF'] = '1';

        $this->assertFalse(SecondFactor::obligatoriPer(User::factory()->create(['role' => 'admin'])));

        unset($_ENV['SECOND_FACTOR_OFF'], $_SERVER['SECOND_FACTOR_OFF']);
    }

    /* ── 3. L'impacte, abans de decidir ─────────────────────────────────────────────── */

    /**
     * El mètode per defecte de tothom és «tauleta corporativa». Per a qui treballa des d'un
     * ordinador això vol dir que el seu segon factor és un aparell que no porta: el dia que
     * s'activi l'obligatorietat, es queda fora. La pantalla ho ha de dir ABANS, amb noms.
     */
    public function test_la_pantalla_avisa_de_qui_quedaria_fora(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'work_type' => 'AMBULATORIA', 'second_factor' => SecondFactor::DISPOSITIU,
        ]));

        /* De carrer: la tauleta ÉS el seu segon factor, no està en risc. */
        User::factory()->create(['role' => 'worker', 'work_type' => 'DOMICILIARIA',
            'second_factor' => SecondFactor::DISPOSITIU, 'active' => true]);
        /* D'oficina amb mètode tauleta: en risc. */
        $risc = User::factory()->create(['role' => 'worker', 'work_type' => 'AMBULATORIA',
            'second_factor' => SecondFactor::DISPOSITIU, 'active' => true]);
        /* D'oficina però ja enrolada: no està en risc. */
        User::factory()->create(['role' => 'worker', 'work_type' => 'AMBULATORIA',
            'second_factor' => SecondFactor::TOTP, 'totp_confirmed' => true, 'active' => true]);

        $r = $this->getJson('/api/v1/seguretat/segon-factor')->assertOk();

        $noms = collect($r->json('impacte.en_risc_noms'))->pluck('id');
        $this->assertTrue($noms->contains($risc->id), 'No avisa de qui es quedaria fora.');
        $this->assertSame(1, $r->json('impacte.amb_totp_confirmat'));
    }

    public function test_qui_ja_te_TOTP_confirmat_no_surt_en_risc(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'work_type' => 'DOMICILIARIA']));

        User::factory()->create(['role' => 'worker', 'work_type' => 'AMBULATORIA',
            'second_factor' => SecondFactor::TOTP, 'totp_confirmed' => true, 'active' => true]);

        $this->assertSame(0, $this->getJson('/api/v1/seguretat/segon-factor')->json('impacte.en_risc_total'));
    }

    /* ── 4. Qui hi pot tocar ────────────────────────────────────────────────────────── */

    public function test_nomes_l_admin_canvia_la_politica(): void
    {
        foreach (['hr', 'coordinator', 'worker'] as $rol) {
            Sanctum::actingAs(User::factory()->create(['role' => $rol]));
            $this->getJson('/api/v1/seguretat/segon-factor')->assertForbidden();
            $this->putJson('/api/v1/seguretat/segon-factor', ['mode' => 'all'])->assertForbidden();
        }

        $this->assertSame('off', SecondFactorSetting::actual()->mode);
    }

    /** Qui afluixa el segon factor de tota una plantilla ha de constar (ENS categoria ALTA). */
    public function test_queda_constancia_de_qui_ho_decideix(): void
    {
        Sanctum::actingAs($admin = User::factory()->create(['role' => 'admin']));

        $this->putJson('/api/v1/seguretat/segon-factor', ['mode' => 'all'])->assertOk();

        $s = SecondFactorSetting::actual();
        $this->assertSame($admin->email, $s->changed_by);
        $this->assertNotNull($s->changed_at);
    }

    public function test_no_s_accepten_modes_ni_rols_inventats(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->putJson('/api/v1/seguretat/segon-factor', ['mode' => 'quan_toqui'])->assertStatus(422);
        $this->putJson('/api/v1/seguretat/segon-factor', ['mode' => 'roles', 'roles' => ['direccio']])->assertStatus(422);

        $this->assertSame('off', SecondFactorSetting::actual()->mode);
    }
}
