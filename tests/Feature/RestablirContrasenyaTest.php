<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Mail\WelcomeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Restablir la contrasenya des de la fitxa i correu de benvinguda a l'alta. */
class RestablirContrasenyaTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol, bool $actiu = true): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('vella'), 'role' => $rol, 'active' => $actiu, 'must_change_password' => false]);
    }

    public function test_rrhh_restableix_envia_correu_i_obliga_a_canviar(): void
    {
        Mail::fake();
        $treb = $this->usuari('worker');
        $treb->createToken('mobil');
        Sanctum::actingAs($this->usuari('hr'));

        $this->postJson("/api/v1/users/{$treb->id}/restableix-contrasenya")->assertOk();

        $treb->refresh();
        $this->assertFalse(Hash::check('vella', $treb->password));
        $this->assertTrue((bool) $treb->must_change_password);
        $this->assertSame(0, $treb->tokens()->count());
        Mail::assertSent(PasswordResetMail::class, fn ($m) => $m->hasTo($treb->email));
    }

    public function test_si_el_correu_falla_la_contrasenya_no_canvia(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp caigut'));
        $treb = $this->usuari('worker');
        Sanctum::actingAs($this->usuari('hr'));

        $this->postJson("/api/v1/users/{$treb->id}/restableix-contrasenya")->assertStatus(503);
        $this->assertTrue(Hash::check('vella', $treb->fresh()->password));
        $this->assertFalse((bool) $treb->fresh()->must_change_password);
    }

    public function test_limits(): void
    {
        Mail::fake();
        $admin = $this->usuari('admin');
        $treb = $this->usuari('worker');
        $inactiu = $this->usuari('worker', false);

        Sanctum::actingAs($this->usuari('hr'));
        $this->postJson("/api/v1/users/{$admin->id}/restableix-contrasenya")->assertForbidden();
        $this->postJson("/api/v1/users/{$inactiu->id}/restableix-contrasenya")->assertStatus(422);

        Sanctum::actingAs($this->usuari('worker'));
        $this->postJson("/api/v1/users/{$treb->id}/restableix-contrasenya")->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_l_alta_envia_les_credencials_per_correu(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->usuari('hr'));

        $r = $this->postJson('/api/v1/users', [
            'name' => 'Nova Persona', 'email' => 'nova@test.local', 'password' => 'Clau-Segura-2026!',
            'role' => 'worker', 'work_type' => 'AMBULATORIA', 'job_profile' => 'Fisioterapeuta',
            'dni' => '12345678Z', 'entitat' => 'CRIL',
        ]);
        $r->assertCreated()->assertJson(['correu_enviat' => true]);
        Mail::assertSent(WelcomeMail::class, fn ($m) => $m->hasTo('nova@test.local') && $m->password === 'Clau-Segura-2026!');
    }
}
