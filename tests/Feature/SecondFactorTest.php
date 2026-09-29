<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SecondFactor;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Segon factor a RRHH (ENS ALTA), simètric al de domi. Vegeu
 * domi_crt.gt/docs/PROPOSTA-CREDENCIALS-ENS.md §4.
 */
class SecondFactorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixa la política de segon factor.
     *
     * Des de l'01-08-2026 la font de veritat és la taula `second_factor_settings` —Direcció:
     * «vull decidir-ho jo»— i ja no `config/security.php`, que llegia el .env. Aquestes
     * proves configuraven la política per config; ara la configuren on mana de debò.
     * El .env segueix sent la xarxa per si la taula no hi és, i això es prova a
     * PoliticaSegonFactorTest.
     */
    private function politica(string $mode, array $rols = []): void
    {
        \App\Models\SecondFactorSetting::actual()->update(['mode' => $mode, 'roles' => $rols]);
    }

    private function worker(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'W', 'email' => 'w' . uniqid() . '@test.local',
            'password' => Hash::make('Bona2026!segura'), 'role' => 'worker', 'active' => true,
        ], $attrs));
    }

    public function test_totp_vector_rfc(): void
    {
        // Vector RFC 6238 de 8 dígits -> 6 últims (fem 6 dígits com Google Authenticator).
        $sec = Totp::base32Encode('12345678901234567890');
        $this->assertSame('287082', Totp::codi($sec, 59));
        $this->assertSame('081804', Totp::codi($sec, 1111111109));
    }

    public function test_totp_verifica_finestra(): void
    {
        $sec = Totp::nouSecret();
        $this->assertTrue(Totp::verifica($sec, Totp::codi($sec)));
        $this->assertTrue(Totp::verifica($sec, Totp::codi($sec, time() - 30)), 'finestra ±1');
        $this->assertFalse(Totp::verifica($sec, Totp::codi($sec, time() - 90)), 'massa antic');
        $this->assertFalse(Totp::verifica($sec, '000000'));
    }

    public function test_metode_per_defecte_i_admin_el_canvia(): void
    {
        $u = $this->worker();
        $this->assertSame(SecondFactor::DISPOSITIU, SecondFactor::metode($u));
        $this->assertTrue(SecondFactor::adminSetMetode($u, SecondFactor::TOTP, 'admin@test.local'));
        $this->assertSame(SecondFactor::TOTP, SecondFactor::metode($u->fresh()));
        $this->assertFalse(SecondFactor::adminSetMetode($u, 'invalid', 'admin@test.local'));
    }

    public function test_enrolament_totp(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $prep = SecondFactor::totpPreparar($u);
        $u->refresh();
        // Abans de confirmar, no valida.
        $this->assertFalse(SecondFactor::verifica($u, ['totp_codi' => Totp::codi($prep['secret'])])['ok']);
        $this->assertFalse(SecondFactor::totpConfirmar($u, '000000'));
        $this->assertTrue(SecondFactor::totpConfirmar($u->fresh(), Totp::codi($prep['secret'])));
        // El secret es desa xifrat, mai en clar.
        $this->assertNotSame($prep['secret'], $u->fresh()->totp_secret);
    }

    public function test_login_faseA_no_bloqueja(): void
    {
        $this->politica('off');   // Fase A
        $this->worker(['email' => 'fa@test.local', 'second_factor' => 'totp']);
        // Sense codi, però Fase A -> entra igual (retorna token).
        $this->postJson('/api/v1/auth/login', ['email' => 'fa@test.local', 'password' => 'Bona2026!segura'])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_login_faseB_totp_demana_codi(): void
    {
        $this->politica('all');    // Fase B
        $u = $this->worker(['email' => 'fb@test.local', 'second_factor' => 'totp']);
        $prep = SecondFactor::totpPreparar($u);
        SecondFactor::totpConfirmar($u->fresh(), Totp::codi($prep['secret']));

        // Sense codi -> demana segon factor, SENSE token.
        $this->postJson('/api/v1/auth/login', ['email' => 'fb@test.local', 'password' => 'Bona2026!segura'])
            ->assertOk()->assertJson(['second_factor_required' => true, 'method' => 'totp'])
            ->assertJsonMissing(['token' => true]);

        // Amb codi correcte -> token.
        $this->postJson('/api/v1/auth/login', ['email' => 'fb@test.local', 'password' => 'Bona2026!segura',
            'totp_codi' => Totp::codi($prep['secret'])])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_login_faseB_dispositiu_sense_token_no_entra(): void
    {
        $this->politica('all');
        $this->worker(['email' => 'disp@test.local']);   // mètode dispositiu per defecte
        $this->postJson('/api/v1/auth/login', ['email' => 'disp@test.local', 'password' => 'Bona2026!segura'])
            ->assertStatus(422);   // dispositiu no reconegut
    }

    public function test_admin_assigna_metode(): void
    {
        $admin = User::create(['name' => 'A', 'email' => 'adm@test.local',
            'password' => Hash::make('x'), 'role' => 'admin', 'active' => true]);
        $u = $this->worker();
        \Laravel\Sanctum\Sanctum::actingAs($admin);
        $this->putJson('/api/v1/users/' . $u->id . '/2fa', ['method' => 'totp'])->assertOk();
        $this->assertSame('totp', $u->fresh()->second_factor);
        $this->putJson('/api/v1/users/' . $u->id . '/2fa', ['method' => 'cara'])->assertStatus(422);
    }

    // ── 2FA VOLUNTARI: l'interruptor que Direcció NO ha activat ───────────────
    //
    // Ordre de Direcció: el segon factor ha de quedar disponible i opcional. Aquests tests
    // fixen què vol dir "opcional" a la pràctica: qui NO s'enrola no nota res, i qui S'ENROLA
    // queda protegit de debò (si no, activar-lo no serviria de res i seria un fals sentit de
    // seguretat). Els dos interruptors d'imposició (per rols i global) queden apagats.

    /** Enrola completament un usuari i retorna el seu secret TOTP en clar. */
    private function enrola(User $u): string
    {
        $prep = SecondFactor::totpPreparar($u);
        SecondFactor::totpConfirmar($u->fresh(), Totp::codi($prep['secret']));
        SecondFactor::generaCodisRecuperacio($u->fresh());

        return $prep['secret'];
    }

    public function test_qui_no_s_enrola_no_nota_res(): void
    {
        $this->politica('off');
        $this->worker(['email' => 'lliure@test.local', 'second_factor' => 'totp']);   // assignat, MAI enrolat

        $this->postJson('/api/v1/auth/login', ['email' => 'lliure@test.local', 'password' => 'Bona2026!segura'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_qui_s_enrola_voluntariament_se_l_exigeix_a_si_mateix(): void
    {
        $this->politica('off');
        $u = $this->worker(['email' => 'volu@test.local', 'second_factor' => 'totp']);
        $secret = $this->enrola($u);

        // Sense codi: no entra, tot i que el 2FA NO és obligatori per a ningú.
        $this->postJson('/api/v1/auth/login', ['email' => 'volu@test.local', 'password' => 'Bona2026!segura'])
            ->assertOk()->assertJson(['second_factor_required' => true])->assertJsonMissing(['token' => true]);

        $this->postJson('/api/v1/auth/login', ['email' => 'volu@test.local',
            'password' => 'Bona2026!segura', 'totp_codi' => Totp::codi($secret)])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_l_interruptor_per_rols_obliga_nomes_els_rols_indicats(): void
    {
        $this->politica('roles', ['admin', 'hr']);
        $admin  = $this->worker(['role' => 'admin']);
        $hr     = $this->worker(['role' => 'hr']);
        $worker = $this->worker(['role' => 'worker']);

        $this->assertTrue(SecondFactor::obligatoriPer($admin));
        $this->assertTrue(SecondFactor::obligatoriPer($hr));
        $this->assertFalse(SecondFactor::obligatoriPer($worker));
        $this->assertTrue(SecondFactor::exigit($admin));
        $this->assertFalse(SecondFactor::exigit($worker));
    }

    public function test_el_kill_switch_ho_apaga_tot(): void
    {
        $this->politica('all');
        $u = $this->worker(['email' => 'kill@test.local', 'second_factor' => 'totp']);
        $this->enrola($u);

        putenv('SECOND_FACTOR_OFF=1');
        try {
            $this->assertFalse(SecondFactor::exigit($u->fresh()));
            $this->postJson('/api/v1/auth/login', ['email' => 'kill@test.local', 'password' => 'Bona2026!segura'])
                ->assertOk()->assertJsonStructure(['token']);
        } finally {
            putenv('SECOND_FACTOR_OFF');
        }
    }

    // ── Còdis de recuperació ─────────────────────────────────────────────────

    public function test_l_enrolament_lliura_els_codis_de_recuperacio_un_sol_cop(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $prep = SecondFactor::totpPreparar($u);

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $codis = $this->postJson('/api/v1/auth/2fa/confirm', ['code' => Totp::codi($prep['secret'])])
            ->assertOk()->json('recovery_codes');

        $this->assertCount(SecondFactor::CODIS_RECUPERACIO, $codis);
        $this->assertSame(count($codis), count(array_unique($codis)));
        $this->assertSame(SecondFactor::CODIS_RECUPERACIO, SecondFactor::codisRecuperacioRestants($u->fresh()));

        // Es desa el HASH, no el codi: cap codi en clar apareix a la columna.
        $desat = (string) $u->fresh()->totp_recovery_codes;
        foreach ($codis as $c) {
            $this->assertStringNotContainsString($c, $desat);
        }
    }

    public function test_un_codi_de_recuperacio_entra_una_vegada_i_es_crema(): void
    {
        $this->politica('off');
        $u = $this->worker(['email' => 'rec@test.local', 'second_factor' => 'totp']);
        $prep = SecondFactor::totpPreparar($u);
        SecondFactor::totpConfirmar($u->fresh(), Totp::codi($prep['secret']));
        $codis = SecondFactor::generaCodisRecuperacio($u->fresh());

        $login = fn (array $extra) => $this->postJson('/api/v1/auth/login',
            array_merge(['email' => 'rec@test.local', 'password' => 'Bona2026!segura'], $extra));

        $login(['codi_recuperacio' => $codis[0]])->assertOk()->assertJsonStructure(['token']);
        $this->assertSame(count($codis) - 1, SecondFactor::codisRecuperacioRestants($u->fresh()));

        // El mateix codi ja no val: és d'un sol ús.
        $login(['codi_recuperacio' => $codis[0]])->assertOk()->assertJson(['second_factor_required' => true]);
        // Un codi inventat, tampoc.
        $login(['codi_recuperacio' => 'AAAAA-BBBBB'])->assertOk()->assertJson(['second_factor_required' => true]);
        // Un altre dels lliurats, sí.
        $login(['codi_recuperacio' => $codis[1]])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_el_codi_de_recuperacio_s_accepta_amb_guions_i_en_minuscules(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $this->enrola($u);
        $codis = SecondFactor::generaCodisRecuperacio($u->fresh());

        $this->assertTrue(SecondFactor::consumeixCodiRecuperacio($u->fresh(),
            strtolower(str_replace('-', ' ', $codis[0]))));
    }

    public function test_regenerar_els_codis_exigeix_un_codi_vigent_i_invalida_els_antics(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $secret = $this->enrola($u);
        $antics = SecondFactor::generaCodisRecuperacio($u->fresh());

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $this->postJson('/api/v1/auth/2fa/recovery-codes', ['code' => '000000'])->assertStatus(422);

        $nous = $this->postJson('/api/v1/auth/2fa/recovery-codes', ['code' => Totp::codi($secret)])
            ->assertOk()->json('recovery_codes');

        $this->assertCount(SecondFactor::CODIS_RECUPERACIO, $nous);
        $this->assertEmpty(array_intersect($antics, $nous));
        $this->assertFalse(SecondFactor::consumeixCodiRecuperacio($u->fresh(), $antics[0]),
            'un codi del joc antic no pot seguir valent després de regenerar');
    }

    // ── Enrolament: no es pot moure de dispositiu en calent ───────────────────

    public function test_no_es_pot_reenrolar_sense_desactivar_primer(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $this->enrola($u);

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $this->postJson('/api/v1/auth/2fa/prepare')->assertStatus(422)
            ->assertJson(['error' => 'already_enrolled']);

        // I l'enrolament vigent no s'ha tocat.
        $this->assertTrue(SecondFactor::enrolat($u->fresh()));
    }

    public function test_qui_no_te_metode_totp_no_es_pot_enrolar(): void
    {
        $u = $this->worker();   // mètode 'dispositiu'
        \Laravel\Sanctum\Sanctum::actingAs($u);
        $this->postJson('/api/v1/auth/2fa/prepare')->assertStatus(422)->assertJson(['error' => 'not_totp']);
    }

    // ── Desactivació ─────────────────────────────────────────────────────────

    public function test_desactivar_exigeix_contrasenya_i_codi(): void
    {
        $this->politica('off');
        $u = $this->worker(['email' => 'off@test.local', 'second_factor' => 'totp']);
        $secret = $this->enrola($u);

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $this->deleteJson('/api/v1/auth/2fa', ['password' => 'dolenta', 'code' => Totp::codi($secret)])
            ->assertStatus(422);
        $this->deleteJson('/api/v1/auth/2fa', ['password' => 'Bona2026!segura', 'code' => '000000'])
            ->assertStatus(422);
        $this->assertTrue(SecondFactor::enrolat($u->fresh()), 'cap dels dos intents fallits l\'ha desactivat');

        $this->deleteJson('/api/v1/auth/2fa', ['password' => 'Bona2026!segura', 'code' => Totp::codi($secret)])
            ->assertOk()->assertJson(['success' => true]);

        $fresc = $u->fresh();
        $this->assertFalse(SecondFactor::enrolat($fresc));
        $this->assertNull($fresc->totp_secret);
        $this->assertNull($fresc->totp_recovery_codes);
        $this->assertFalse(SecondFactor::exigit($fresc));

        // I torna a entrar sense codi, com abans d'activar-lo.
        $this->postJson('/api/v1/auth/login', ['email' => 'off@test.local', 'password' => 'Bona2026!segura'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_es_pot_desactivar_amb_un_codi_de_recuperacio(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $this->enrola($u);
        $codis = SecondFactor::generaCodisRecuperacio($u->fresh());

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $this->deleteJson('/api/v1/auth/2fa',
            ['password' => 'Bona2026!segura', 'codi_recuperacio' => $codis[0]])->assertOk();
        $this->assertFalse(SecondFactor::enrolat($u->fresh()));
    }

    public function test_no_es_pot_desactivar_el_que_direccio_ha_fet_obligatori(): void
    {
        $this->politica('roles', ['hr']);
        $u = $this->worker(['role' => 'hr', 'second_factor' => 'totp']);
        $secret = $this->enrola($u);

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $this->deleteJson('/api/v1/auth/2fa', ['password' => 'Bona2026!segura', 'code' => Totp::codi($secret)])
            ->assertStatus(403);
        $this->assertTrue(SecondFactor::enrolat($u->fresh()));
    }

    // ── Estat i fuites ───────────────────────────────────────────────────────

    public function test_l_estat_del_segon_factor_es_consultable(): void
    {
        $this->politica('off');
        $u = $this->worker(['second_factor' => 'totp']);

        \Laravel\Sanctum\Sanctum::actingAs($u);
        $this->getJson('/api/v1/auth/2fa')->assertOk()->assertJson([
            'method' => 'totp', 'enrolled' => false, 'required' => false,
            'enforced' => false, 'recovery_codes_left' => 0,
        ]);

        $this->enrola($u);
        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        $this->getJson('/api/v1/auth/2fa')->assertOk()->assertJson([
            'enrolled' => true, 'required' => false, 'enforced' => true,
            'recovery_codes_left' => SecondFactor::CODIS_RECUPERACIO,
        ]);
    }

    public function test_ni_el_secret_ni_els_codis_surten_mai_per_l_api(): void
    {
        $u = $this->worker(['second_factor' => 'totp']);
        $secret = $this->enrola($u);

        \Laravel\Sanctum\Sanctum::actingAs($u->fresh());
        foreach (['/api/v1/auth/2fa', '/api/v1/users/me', '/api/v1/users/' . $u->id] as $ruta) {
            $cru = $this->getJson($ruta)->getContent();
            $this->assertStringNotContainsString('totp_secret', $cru, "fuita a {$ruta}");
            $this->assertStringNotContainsString('totp_recovery_codes', $cru, "fuita a {$ruta}");
            $this->assertStringNotContainsString($secret, $cru, "el secret en clar viatja a {$ruta}");
        }
    }
}
