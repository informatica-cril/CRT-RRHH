<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Aprovació conjunta de fitxatges: el mateix que el botó «Aprovar», per a una selecció. */
class WorkLogBulkTest extends TestCase
{
    use RefreshDatabase;

    private function usuari(string $rol): User
    {
        return User::create(['name' => 'P ' . $rol, 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => $rol, 'active' => true]);
    }

    private function fitxatge(User $u, string $estat, array $extra = []): int
    {
        return DB::table('work_logs')->insertGetId($extra + [
            'user_id' => $u->id, 'date' => '2026-09-10', 'start_time' => '2026-09-10 08:00:00', 'end_time' => '2026-09-10 15:00:00',
            'total_hours_worked' => 7, 'status' => $estat, 'hour_status' => 'ok', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_aprova_nomes_els_pendents_i_recupera_les_hores_fora_de_zona(): void
    {
        $t = $this->usuari('worker');
        $normal = $this->fitxatge($t, 'pending');
        $foraZona = $this->fitxatge($t, 'pending', ['total_hours_worked' => 5, 'hours_out_of_area' => 2, 'hour_status' => 'out_of_area']);
        $rebutjat = $this->fitxatge($t, 'rejected');

        Sanctum::actingAs($this->usuari('hr'));
        $this->postJson('/api/v1/work-logs/aprova-seleccionats', ['ids' => [$normal, $foraZona, $rebutjat]])
            ->assertOk()->assertJson(['aprovats' => 2, 'omesos' => 1]);

        $this->assertDatabaseHas('work_logs', ['id' => $normal, 'status' => 'approved']);
        $this->assertDatabaseHas('work_logs', ['id' => $foraZona, 'status' => 'approved', 'hour_status' => 'ok', 'hours_out_of_area' => 0, 'total_hours_worked' => 7]);
        $this->assertDatabaseHas('work_logs', ['id' => $rebutjat, 'status' => 'rejected']);
        $this->assertSame(2, DB::table('work_log_modifications')->where('action', 'approved')->count());
    }

    public function test_un_treballador_no_pot_aprovar(): void
    {
        $t = $this->usuari('worker');
        $id = $this->fitxatge($t, 'pending');
        Sanctum::actingAs($t);
        $this->postJson('/api/v1/work-logs/aprova-seleccionats', ['ids' => [$id]])->assertForbidden();
        $this->assertDatabaseHas('work_logs', ['id' => $id, 'status' => 'pending']);
    }
}
