<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Persones que fitxen sense horari: RRHH les veu a la Safata per assignar-n'hi un. */
class SenseHorariTest extends TestCase
{
    use RefreshDatabase;

    private function persona(array $extra = []): User
    {
        return User::create($extra + ['name' => 'P ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
    }

    public function test_rrhh_veu_qui_no_te_horari_i_la_safata_ho_compta(): void
    {
        $horari = WorkSchedule::create(['name' => 'H', 'total_hours_weekly' => 20, 'days' => [['day' => 1, 'start' => '09:00', 'end' => '13:00']]]);
        $buit = WorkSchedule::create(['name' => 'Buit', 'total_hours_weekly' => 30, 'days' => []]);
        $sense = $this->persona(['name' => 'A Sense Horari']);
        $horariBuit = $this->persona(['name' => 'B Horari Buit', 'work_schedule_id' => $buit->id]); // com no tenir-ne
        $this->persona(['work_schedule_id' => $horari->id]);                    // en té
        $this->persona(['relacio' => 'autonom']);                                // autònom: no fitxa
        $this->persona(['active' => false]);                                     // de baixa
        $hr = $this->persona(['role' => 'hr']);                                  // gestió, no fitxa

        Sanctum::actingAs($hr);
        $llista = $this->getJson('/api/v1/users/sense-horari')->assertOk()->json();
        $this->assertSame([$sense->id, $horariBuit->id], array_column($llista, 'id'));
        $this->assertSame(['Sense horari', 'Horari sense cap dia'], array_column($llista, 'motiu'));

        $cua = collect($this->getJson('/api/v1/safata/resum')->assertOk()->json('cues'))->firstWhere('clau', 'sensehorari');
        $this->assertSame(2, $cua['n']);
    }

    public function test_un_treballador_no_veu_la_llista(): void
    {
        Sanctum::actingAs($this->persona());
        $this->getJson('/api/v1/users/sense-horari')->assertForbidden();
    }
}
