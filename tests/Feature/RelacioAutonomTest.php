<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tipus de relació laboral|autònom i tall de jornada per a autònoms.
 * Vegeu domi_crt.gt/docs/PROPOSTA-COLABORADOR-AUTONOM.md.
 *
 * El contracte que es prova:
 *   - esAutonom() és FAIL-SAFE: només 'autonom' explícit; NULL = laboral.
 *   - Els endpoints /domi/* de JORNADA responen 409 a un autònom i mai creen WorkLog.
 *   - El mateix worker com a laboral (o sense classificar) segueix pel camí normal.
 *   - perfil() NO es talla: exposa relacio i disponibilitat_setmanal.
 *   - relacio/disponibilitat són camps privilegiats: un worker no se'ls pot canviar.
 */
class RelacioAutonomTest extends TestCase
{
    use RefreshDatabase;

    private function servei(): User
    {
        return User::create([
            'name' => 'Servei domi', 'email' => 'servei@test.local',
            'password' => bcrypt('x'), 'role' => 'service', 'active' => true,
        ]);
    }

    private function worker(?string $relacio): User
    {
        return User::create([
            'name' => 'Treballador Prova', 'email' => 'worker' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'dni' => 'DNI' . strtoupper(uniqid()),
            'relacio' => $relacio, 'disponibilitat_setmanal' => $relacio === 'autonom' ? 12.5 : null,
            'active' => true,
        ]);
    }

    public function test_es_autonom_es_failsafe(): void
    {
        $this->assertTrue($this->worker('autonom')->esAutonom());
        $this->assertFalse($this->worker('laboral')->esAutonom());
        $this->assertFalse($this->worker(null)->esAutonom(), 'NULL (no classificat) ha de ser laboral');
    }

    public function test_jornada_start_autonom_409_i_cap_worklog(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        $w = $this->worker('autonom');

        $r = $this->postJson('/api/v1/domi/jornada/start', ['user_id' => $w->id]);
        $r->assertStatus(409);
        $this->assertStringContainsString('autònom', $r->json('message'));
        $this->assertSame(0, \App\Models\WorkLog::where('user_id', $w->id)->count(),
            'un 409 no pot deixar cap WorkLog creat');
    }

    public function test_hito_i_pauses_autonom_409(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        $w = $this->worker('autonom');

        $this->postJson('/api/v1/domi/hito', ['user_id' => $w->id, 'tipus' => 'arribada',
            'moment' => now()->toIso8601String()])->assertStatus(409);
        $this->postJson('/api/v1/domi/break/start', ['user_id' => $w->id])->assertStatus(409);
        $this->postJson('/api/v1/domi/break/complete', ['user_id' => $w->id])->assertStatus(409);
        $this->getJson('/api/v1/domi/break-status/' . $w->id)->assertStatus(409);
    }

    public function test_laboral_i_no_classificat_no_reben_409(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        foreach ([$this->worker('laboral'), $this->worker(null)] as $w) {
            $r = $this->getJson('/api/v1/domi/break-status/' . $w->id);
            $this->assertNotSame(409, $r->getStatusCode(),
                'un laboral (o no classificat) mai ha de rebre el 409 dels autònoms');
        }
    }

    public function test_perfil_no_es_talla_i_exposa_relacio(): void
    {
        Sanctum::actingAs($this->servei(), \App\Support\DomiScopes::permisos());
        $w = $this->worker('autonom');

        $r = $this->getJson('/api/v1/domi/perfil/' . $w->id);
        $r->assertOk();
        $this->assertSame('autonom', $r->json('relacio'));
        $this->assertSame(12.5, $r->json('disponibilitat_setmanal'));

        $l = $this->getJson('/api/v1/domi/perfil/' . $this->worker(null)->id);
        $l->assertOk();
        $this->assertNull($l->json('relacio'), 'NULL ha de viatjar com a null, mai inventar-se');
    }

    public function test_relacio_es_camp_privilegiat(): void
    {
        $w = $this->worker('laboral');
        Sanctum::actingAs($w);   // el mateix worker intenta autoproclamar-se autònom
        $this->putJson('/api/v1/users/' . $w->id, ['relacio' => 'autonom']);
        $this->assertSame('laboral', $w->fresh()->relacio,
            'un no-admin no pot canviar la seva pròpia relació');
    }

    public function test_admin_pot_classificar(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@test.local',
            'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);
        Sanctum::actingAs($admin);
        $w = $this->worker(null);
        $this->putJson('/api/v1/users/' . $w->id, ['relacio' => 'autonom', 'disponibilitat_setmanal' => 20])
            ->assertOk();
        $this->assertSame('autonom', $w->fresh()->relacio);
        $this->putJson('/api/v1/users/' . $w->id, ['relacio' => 'becari'])->assertStatus(422);
    }
}
