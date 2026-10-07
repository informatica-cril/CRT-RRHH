<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Domiciliària: en canviar codis postals o municipis, la pantalla comprova les marques amb els
 * polígons i el servidor aplica el resultat (només fora → dins i només en fitxatges pendents).
 */
class ReavaluaZonaCpTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => $role, 'active' => true, 'work_type' => 'DOMICILIARIA']);
    }

    private function foraZona(User $u, string $status = 'pending'): WorkLog
    {
        $log = WorkLog::create([
            'user_id' => $u->id, 'date' => '2026-10-05',
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-05 15:00:00',
            'total_hours_worked' => 7, 'hours_worked' => 0, 'hours_out_of_area' => 7, 'hour_status' => 'out_of_area',
            'start_location_lat' => 41.38, 'start_location_lng' => 2.15, 'start_location_match' => false,
            'end_location_lat' => 41.38, 'end_location_lng' => 2.15, 'end_location_match' => false,
            'status' => $status, 'segmented' => true,
        ]);
        WorkLogSegment::create(['work_log_id' => $log->id, 'segment_number' => 1,
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-05 15:00:00',
            'duration_minutes' => 420, 'status' => 'pending', 'in_zone' => false, 'in_schedule' => true]);

        return $log;
    }

    public function test_rrhh_veu_les_marques_fora_de_zona_i_l_aplica(): void
    {
        $w = $this->user('worker');
        $log = $this->foraZona($w);
        $this->foraZona($w, 'approved'); // resolt: no surt
        Sanctum::actingAs($this->user('hr'));

        $this->getJson("/api/v1/work-logs/fora-zona/{$w->id}")
            ->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $log->id)
            ->assertJsonPath('0.inici.lat', 41.38);
        $this->assertDatabaseHas('audit_logs', ['action' => 'VIEW_COORDS', 'entity_id' => $w->id]);

        $this->postJson('/api/v1/work-logs/reavalua-zona', ['user_id' => $w->id, 'resultats' => [
            ['work_log_id' => $log->id, 'inici_dins' => true, 'fi_dins' => true],
        ]])->assertOk()->assertJsonPath('fitxatges_reavaluats', 1);

        $log->refresh();
        $this->assertTrue($log->start_location_match);
        $this->assertSame('ok', $log->hour_status);
        $this->assertSame('approved', WorkLogSegment::where('work_log_id', $log->id)->first()->status);
    }

    public function test_no_s_aplica_a_fitxatges_d_una_altra_persona_ni_resolts(): void
    {
        $w = $this->user('worker');
        $altre = $this->foraZona($this->user('worker'));
        $resolt = $this->foraZona($w, 'approved');
        Sanctum::actingAs($this->user('admin'));

        $this->postJson('/api/v1/work-logs/reavalua-zona', ['user_id' => $w->id, 'resultats' => [
            ['work_log_id' => $altre->id, 'inici_dins' => true],
            ['work_log_id' => $resolt->id, 'inici_dins' => true],
        ]])->assertOk()->assertJsonPath('fitxatges_reavaluats', 0);
        $this->assertFalse($altre->fresh()->start_location_match);
    }

    public function test_nomes_rrhh_i_administracio(): void
    {
        $w = $this->user('worker');
        Sanctum::actingAs($w);
        $this->getJson("/api/v1/work-logs/fora-zona/{$w->id}")->assertForbidden();
        $this->postJson('/api/v1/work-logs/reavalua-zona', ['user_id' => $w->id, 'resultats' => []])->assertForbidden();
    }
}
