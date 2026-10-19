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
