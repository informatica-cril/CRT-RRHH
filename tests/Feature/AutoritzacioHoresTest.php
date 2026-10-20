<?php

namespace Tests\Feature;

use App\Models\AuthorizationCode;
use App\Models\User;
use App\Models\WorkLog;
use App\Services\AutoritzacioHores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Hores de més: una autorització és sempre d'una persona i s'aplica sola als seus fitxatges dels
 * dies autoritzats (fins a les hores que li queden). Ja no cal escriure cap codi.
 */
class AutoritzacioHoresTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hr = User::create(['name' => 'RRHH', 'email' => 'h' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true]);
    }

    private function persona(): User
    {
        return User::create(['name' => 'Persona', 'email' => 'p' . uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
    }

    private function fitxatge(User $u, string $dia, float $deMes): WorkLog
    {
        return WorkLog::create(['user_id' => $u->id, 'date' => $dia, 'start_time' => "{$dia} 08:00:00", 'end_time' => "{$dia} 16:00:00",
            'status' => 'pending', 'total_hours_worked' => 8, 'extra_hours_unauthorized' => $deMes]);
    }

    private function autoritza(?User $u, float $hores, string $des, string $fins): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->hr);

        return $this->postJson('/api/v1/auth-codes', array_filter([
            'concept' => 'Visites que s\'allarguen', 'authorized_hours' => $hores, 'type' => 'extraordinaria',
            'time_slot_start' => '06:00', 'time_slot_end' => '22:00', 'generated_by' => $this->hr->id,
            'user_id' => $u?->id, 'valid_from' => "{$des} 00:00:00", 'valid_to' => "{$fins} 23:59:59",
        ], fn ($v) => $v !== null));
    }

    public function test_sempre_ha_de_ser_d_una_persona_i_el_codi_el_posa_el_servidor(): void
    {
        $this->autoritza(null, 2, '2026-10-20', '2026-10-22')->assertStatus(422)->assertJsonValidationErrors('user_id');

        $r = $this->autoritza($this->persona(), 2, '2026-10-20', '2026-10-22')->assertCreated();
        $this->assertNotEmpty($r->json('code'));
    }

    public function test_en_autoritzar_s_aplica_als_fitxatges_tancats_d_aquells_dies(): void
    {
        $u = $this->persona();
        $dins = $this->fitxatge($u, '2026-10-21', 1.5);
        $fora = $this->fitxatge($u, '2026-10-25', 1);
        $altre = $this->fitxatge($this->persona(), '2026-10-21', 1);

        $r = $this->autoritza($u, 2, '2026-10-20', '2026-10-22')->assertCreated();
        $this->assertEquals(1.5, $r->json('aplicades'));

        $dins->refresh();
        $this->assertEquals(1.5, $dins->extra_hours_authorized);
        $this->assertEquals(0, (float) $dins->extra_hours_unauthorized);
        $this->assertSame($r->json('code'), $dins->authorized_extra_code);
        $this->assertNull($fora->fresh()->authorized_extra_code);
        $this->assertNull($altre->fresh()->authorized_extra_code);
        $this->assertFalse(AuthorizationCode::find($r->json('id'))->used);   // en queden 0,5 h
    }

    public function test_gestio_accepta_les_hores_de_mes_d_un_fitxatge(): void
    {
        // Jornada completa: les hores de més són extraordinàries.
        $horari = \App\Models\WorkSchedule::create(['name' => 'Completa', 'total_hours_weekly' => 37.5,
            'days' => collect([1, 2, 3, 4, 5])->map(fn ($d) => ['day' => $d, 'start' => '08:00', 'end' => '15:30'])->all()]);
        $u = $this->persona();
        $u->update(['work_schedule_id' => $horari->id]);
        $log = $this->fitxatge($u, '2026-10-16', 4.15);   // ha cobert el torn d'una companya

        Sanctum::actingAs($this->hr);
        $this->postJson("/api/v1/work-logs/{$log->id}/accepta-hores-de-mes", ['motiu' => "Ha cobert el torn d'una companya"])
            ->assertOk()->assertJsonPath('aplicades', 4.15)->assertJsonPath('pendents', 0);

        $log->refresh();
        $this->assertEquals(4.15, $log->extra_hours_authorized);
        $codi = AuthorizationCode::where('code', $log->authorized_extra_code)->first();
        $this->assertSame('extraordinaria', $codi->type);
        $this->assertSame($u->id, $codi->user_id);

        // Ja no en queden: tornar-hi diu que no hi ha res per acceptar.
        $this->postJson("/api/v1/work-logs/{$log->id}/accepta-hores-de-mes")->assertStatus(422);
    }

    public function test_si_ja_hi_havia_una_autoritzacio_d_aquell_dia_s_aplica_aquella(): void
    {
        $u = $this->persona();
        $log = $this->fitxatge($u, '2026-10-16', 2);
        // Una autorització d'aquell dia que ningú no va fer servir (abans calia escriure el codi).
        $vella = AuthorizationCode::create(['code' => 'VELLA-1', 'concept' => 'Substitució 16/10', 'authorized_hours' => 5.67,
            'type' => 'complementaria', 'factor' => 1, 'user_id' => $u->id, 'generated_by' => $this->hr->id,
            'time_slot_start' => '06:00', 'time_slot_end' => '22:00',
            'valid_from' => '2026-10-16 00:00:00', 'valid_to' => '2026-10-16 23:59:59']);

        Sanctum::actingAs($this->hr);
        $this->postJson("/api/v1/work-logs/{$log->id}/accepta-hores-de-mes")->assertOk()->assertJsonPath('de_autoritzacio_existent', true);
        $this->assertSame('VELLA-1', $log->fresh()->authorized_extra_code);
        $this->assertSame(1, AuthorizationCode::count());
    }

    public function test_coordinacio_i_treballadors_no_accepten_hores(): void
    {
        $u = $this->persona();
        $log = $this->fitxatge($u, '2026-10-16', 2);
        Sanctum::actingAs($u);
        $this->postJson("/api/v1/work-logs/{$log->id}/accepta-hores-de-mes")->assertForbidden();
    }

    public function test_en_fitxar_la_sortida_s_aplica_el_que_queda_i_despres_s_esgota(): void
    {
        $u = $this->persona();
        $this->fitxatge($u, '2026-10-21', 1.5);
        $codi = AuthorizationCode::find($this->autoritza($u, 2, '2026-10-20', '2026-10-22')->json('id'));

        $nou = $this->fitxatge($u, '2026-10-22', 1);
        $this->assertEquals(0.5, AutoritzacioHores::aplica($nou));
        $nou->refresh();
        $this->assertEquals(0.5, $nou->extra_hours_authorized);
        $this->assertEquals(0.5, (float) $nou->extra_hours_unauthorized);
        $this->assertTrue($codi->fresh()->used);

        $this->assertEquals(0, AutoritzacioHores::aplica($this->fitxatge($u, '2026-10-22', 1)));
    }
}
