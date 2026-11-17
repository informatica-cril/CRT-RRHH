<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Hores de més: RRHH les pot denegar (queda traçat) i un fitxatge rebutjat no les pot acceptar. */
class HoresDeMesDenegarTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => ucfirst($rol), 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $rol, 'active' => true]);
    }

    private function fitxatge(User $u, string $estat): int
    {
        return DB::table('work_logs')->insertGetId([
            'user_id' => $u->id, 'date' => '2026-11-09', 'start_time' => '2026-11-09 09:00:00', 'end_time' => '2026-11-09 13:12:00',
            'total_hours_worked' => 4.2, 'effective_hours' => 4.2, 'extra_hours_unauthorized' => 0.2, 'hour_status' => 'extra',
            'status' => $estat, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_denegar_deixa_les_hores_a_zero_i_ho_traca(): void
    {
        $treb = $this->usuari('worker');
        $id = $this->fitxatge($treb, 'pending');
        Sanctum::actingAs($this->usuari('hr'));

        $this->postJson("/api/v1/work-logs/{$id}/denega-hores-de-mes", ['motiu' => 'No era necessari'])->assertOk()->assertJson(['denegades' => 0.2]);

        $this->assertEquals(0, DB::table('work_logs')->where('id', $id)->value('extra_hours_unauthorized'));
        $this->assertSame('ok', DB::table('work_logs')->where('id', $id)->value('hour_status'));
        $m = DB::table('work_log_modifications')->where('work_log_id', $id)->first();
        $this->assertStringContainsString('No era necessari', $m->comment);
        $this->assertStringContainsString('0.2', $m->old_values);
    }

    public function test_un_fitxatge_rebutjat_no_accepta_hores_de_mes(): void
    {
        $id = $this->fitxatge($this->usuari('worker'), 'rejected');
        Sanctum::actingAs($this->usuari('hr'));

        $this->postJson("/api/v1/work-logs/{$id}/accepta-hores-de-mes")->assertStatus(422);
    }

    public function test_un_treballador_no_pot_denegar(): void
    {
        $treb = $this->usuari('worker');
        $id = $this->fitxatge($treb, 'pending');
        Sanctum::actingAs($treb);

        $this->postJson("/api/v1/work-logs/{$id}/denega-hores-de-mes")->assertForbidden();
    }
}
