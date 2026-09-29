<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecondFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Qui pot activar el segon factor d'una altra persona.
 *
 * Direcció (01-08-2026): «el 2FA ha de poder activar-lo l'admin O RRHH per a cada usuari».
 * Fins ara la ruta era només d'admin, i això volia dir que donar d'alta algú i deixar-lo
 * operatiu eren dues persones diferents: RRHH creava el compte i havia d'anar a buscar
 * l'administrador per acabar la feina.
 *
 * ON ES QUEDA LA RATLLA. Coordinació NO. Decidir com entra una persona a l'aplicació és
 * administració de comptes, no direcció del dia a dia; i qui reparteix la feina no ha de
 * poder canviar el mètode d'entrada de qui té a càrrec.
 *
 * ELS DOS MÈTODES, i la tria no és de gust sinó del dispositiu amb què entra la persona:
 *   dispositiu → la tauleta corporativa ÉS el segon factor (mateix HMAC que el fichatge).
 *   totp       → codi de 6 xifres per a qui treballa des d'un ordinador.
 */
class SegonFactorGestioTest extends TestCase
{
    use RefreshDatabase;

    private function algu(): User
    {
        return User::factory()->create(['role' => 'worker', 'second_factor' => SecondFactor::DISPOSITIU]);
    }

    public function test_RRHH_pot_activar_el_segon_factor_d_un_treballador(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'hr']));
        $u = $this->algu();

        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'totp'])->assertOk();

        $this->assertSame(SecondFactor::TOTP, $u->fresh()->second_factor);
    }

    public function test_l_admin_tambe(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = $this->algu();

        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'totp'])->assertOk();

        $this->assertSame(SecondFactor::TOTP, $u->fresh()->second_factor);
    }

    /** La ratlla: coordinació dirigeix la feina, no administra comptes. */
    public function test_coordinacio_NO_pot_canviar_com_entra_ningu(): void
    {
        $u = $this->algu();

        Sanctum::actingAs(User::factory()->create(['role' => 'coordinator']));
        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'totp'])->assertForbidden();

        $this->assertSame(SecondFactor::DISPOSITIU, $u->fresh()->second_factor,
            'Un coordinador ha pogut canviar el segon factor.');
    }

    public function test_un_treballador_no_pot_canviar_lo_a_un_altre(): void
    {
        $u = $this->algu();

        Sanctum::actingAs(User::factory()->create(['role' => 'worker']));
        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'totp'])->assertForbidden();
    }

    /** Ni el seu propi: l'autoservei té una altra ruta (auth/2fa), amb enrolament i verificació. */
    public function test_un_treballador_tampoc_se_l_canvia_a_si_mateix_per_aqui(): void
    {
        Sanctum::actingAs($u = User::factory()->create(['role' => 'worker']));

        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'totp'])->assertForbidden();
    }

    public function test_nomes_s_accepten_els_dos_metodes_que_existeixen(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'hr']));
        $u = $this->algu();

        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'sms'])->assertStatus(422);
        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => ''])->assertStatus(422);

        $this->assertSame(SecondFactor::DISPOSITIU, $u->fresh()->second_factor);
    }

    /**
     * Tornar a «dispositiu» després d'haver assignat TOTP ha de funcionar: si algú perd el
     * telèfon i se li dona una tauleta, RRHH ho ha de poder resoldre sense esperar ningú.
     */
    public function test_es_pot_tornar_enrere_a_la_tauleta(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'hr']));
        $u = User::factory()->create(['role' => 'worker', 'second_factor' => SecondFactor::TOTP]);

        $this->putJson("/api/v1/users/{$u->id}/2fa", ['method' => 'dispositiu'])->assertOk();

        $this->assertSame(SecondFactor::DISPOSITIU, $u->fresh()->second_factor);
    }

    /** La fitxa ha de dir l'estat, si no la pantalla no pot pintar quin mètode té ningú. */
    public function test_la_fitxa_retorna_el_metode_i_si_ja_l_ha_enrolat(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'hr']));
        $u = User::factory()->create(['role' => 'worker',
            'second_factor' => SecondFactor::TOTP, 'totp_confirmed' => false]);

        $this->getJson("/api/v1/users/{$u->id}")
            ->assertOk()
            ->assertJsonPath('second_factor', 'totp')
            ->assertJsonPath('totp_confirmed', false);
    }

    /**
     * El secret TOTP no pot sortir MAI per l'API, ni per a l'admin. Qui el tingués podria
     * generar els codis d'aquella persona i entrar-hi suplantant-la: seria un segon factor
     * que no protegeix de res.
     */
    public function test_el_secret_TOTP_no_surt_mai_per_l_API(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $u = User::factory()->create(['role' => 'worker', 'totp_secret' => 'SECRET-QUE-NO-HA-DE-SORTIR']);

        $cos = $this->getJson("/api/v1/users/{$u->id}")->assertOk()->content();

        $this->assertStringNotContainsString('SECRET-QUE-NO-HA-DE-SORTIR', $cos);
        $this->assertStringNotContainsString('totp_secret', $cos);
    }
}
