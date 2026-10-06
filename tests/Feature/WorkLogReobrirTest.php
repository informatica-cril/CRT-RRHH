<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** «Revisar de nou»: RRHH i admin tornen a pendent el que ja s'havia resolt. */
class WorkLogReobrirTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create([
            'name' => 'Persona ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);
    }

    private function fitxatge(string $status = 'approved'): WorkLog
    {
        return WorkLog::create([
            'user_id' => $this->user('worker')->id, 'date' => '2026-10-05',
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-05 15:00:00',
            'status' => $status, 'segmented' => true,
        ]);
    }

    private function tram(WorkLog $log, string $status): WorkLogSegment
    {
        return WorkLogSegment::create([
            'work_log_id' => $log->id, 'segment_number' => 1,
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-05 15:00:00',
            'duration_minutes' => 420, 'status' => $status,
        ]);
    }

    public function test_rrhh_reobre_un_fitxatge_aprovat_i_queda_tracat(): void
    {
        $hr = $this->user('hr');
        Sanctum::actingAs($hr);
        $log = $this->fitxatge('approved');

        $this->postJson("/api/v1/work-logs/{$log->id}/reobrir", ['motiu' => 'Les hores no quadren amb el quadrant'])
            ->assertOk()->assertJsonPath('status', 'pending');

        $this->assertDatabaseHas('work_log_modifications', [
            'work_log_id' => $log->id, 'user_id' => $hr->id, 'action' => 'modified',
        ]);
    }

    public function test_cal_motiu_i_no_es_reobre_un_de_pendent(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $log = $this->fitxatge('approved');

        $this->postJson("/api/v1/work-logs/{$log->id}/reobrir", ['motiu' => 'curt'])->assertStatus(422);
        $this->postJson("/api/v1/work-logs/{$this->fitxatge('pending')->id}/reobrir", ['motiu' => 'Motiu prou llarg'])->assertStatus(422);
        $this->assertSame('approved', $log->fresh()->status);
    }

    public function test_coordinacio_i_treballadors_no_poden_reobrir(): void
    {
        $log = $this->fitxatge('approved');
        $tram = $this->tram($log, 'approved');

        foreach (['coordinator', 'worker'] as $rol) {
            Sanctum::actingAs($this->user($rol));
            $this->postJson("/api/v1/work-logs/{$log->id}/reobrir", ['motiu' => 'Motiu prou llarg'])->assertForbidden();
            $this->postJson("/api/v1/work-logs/{$log->id}/segments/{$tram->id}/reobrir", ['motiu' => 'Motiu prou llarg'])->assertForbidden();
        }
        $this->assertSame('approved', $tram->fresh()->status);
    }

    public function test_reobrir_un_tram_el_deixa_de_comptar_i_torna_el_fitxatge_a_pendent(): void
    {
        Sanctum::actingAs($this->user('hr'));
        $log = $this->fitxatge('approved');
        $tram = $this->tram($log, 'approved');
        $log->recalcularEfectivo();
        $this->assertEquals(7.0, (float) $log->fresh()->effective_hours);

        $this->postJson("/api/v1/work-logs/{$log->id}/segments/{$tram->id}/reobrir", ['motiu' => 'Revisar la zona del tram'])
            ->assertOk()->assertJsonPath('status', 'pending');

        $this->assertEquals(0.0, (float) $log->fresh()->effective_hours);
        $this->assertSame('pending', $log->fresh()->status);
    }

    public function test_un_tram_d_un_altre_fitxatge_dona_404(): void
    {
        Sanctum::actingAs($this->user('hr'));
        $tram = $this->tram($this->fitxatge('approved'), 'approved');
        $altre = $this->fitxatge('approved');

        $this->postJson("/api/v1/work-logs/{$altre->id}/segments/{$tram->id}/reobrir", ['motiu' => 'Motiu prou llarg'])->assertNotFound();
    }
}
