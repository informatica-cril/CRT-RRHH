<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** «Corregir hora»: RRHH i admin corregeixen l'entrada o la sortida d'un fitxatge. */
class WorkLogCorreccioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 6, 12, 0, 0, 'Europe/Madrid'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => 'Persona ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => $role, 'active' => true,
        ]);
    }

    /** Sortida oblidada: es va fitxar l'endemà a les 09:00. */
    private function fitxatgeAmbSortidaOblidada(): WorkLog
    {
        $log = WorkLog::create([
            'user_id' => $this->user('worker')->id, 'date' => '2026-10-05',
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-06 09:00:00',
            'total_hours_worked' => 25, 'hours_worked' => 25,
            'start_location_match' => true, 'end_location_match' => true,
            'status' => 'approved', 'segmented' => true,
        ]);
        WorkLogSegment::create([
            'work_log_id' => $log->id, 'segment_number' => 1,
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-06 09:00:00',
            'duration_minutes' => 1500, 'status' => 'approved', 'in_zone' => true,
        ]);

        return $log;
    }

    private function corregeix(WorkLog $log, array $body)
    {
        return $this->postJson("/api/v1/work-logs/{$log->id}/corregir-hora", $body);
    }

    public function test_rrhh_corregeix_la_sortida_i_es_recalculen_hores_i_trams(): void
    {
        $hr = $this->user('hr');
        Sanctum::actingAs($hr);
        $log = $this->fitxatgeAmbSortidaOblidada();

        $this->corregeix($log, ['end_time' => '2026-10-05 15:00', 'motiu' => 'Sortida oblidada, confirmada amb la persona'])
            ->assertOk()->assertJsonPath('status', 'pending');

        $log->refresh();
        $this->assertSame('2026-10-05 15:00:00', $log->getRawOriginal('end_time'));
        $this->assertEquals(7.0, (float) $log->total_hours_worked);
        $this->assertTrue((bool) $log->segmented);
        $this->assertSame(420, (int) WorkLogSegment::where('work_log_id', $log->id)->sum('duration_minutes'));

        $mod = $log->modifications()->where('action', 'modified')->latest('id')->first();
        $this->assertSame($hr->id, $mod->user_id);
        $this->assertSame('2026-10-06 09:00:00', $mod->old_values['end_time']);
        $this->assertCount(1, $mod->old_values['trams']);
    }

    public function test_valida_les_hores(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $log = $this->fitxatgeAmbSortidaOblidada();
        $motiu = 'Motiu prou llarg per provar';

        $this->corregeix($log, ['end_time' => '2026-10-05 07:00', 'motiu' => $motiu])->assertStatus(422);  // abans de l'entrada
        $this->corregeix($log, ['end_time' => '2026-10-06 18:00', 'motiu' => $motiu])->assertStatus(422);  // en el futur
        $this->corregeix($log, ['start_time' => '2026-10-04 08:00', 'motiu' => $motiu])->assertStatus(422); // un altre dia
        $this->corregeix($log, ['end_time' => '2026-10-05 15:00', 'motiu' => 'curt'])->assertStatus(422);
        $this->corregeix($log, ['motiu' => $motiu])->assertStatus(422);

        $this->assertSame('2026-10-06 09:00:00', $log->fresh()->getRawOriginal('end_time'));
    }

    public function test_no_es_corregeix_amb_trams_rebutjats(): void
    {
        Sanctum::actingAs($this->user('hr'));
        $log = $this->fitxatgeAmbSortidaOblidada();
        WorkLogSegment::where('work_log_id', $log->id)->update(['status' => 'rejected']);

        $this->corregeix($log, ['end_time' => '2026-10-05 15:00', 'motiu' => 'Motiu prou llarg per provar'])
            ->assertStatus(422);
    }

    public function test_coordinacio_i_treballadors_no_poden_corregir(): void
    {
        $log = $this->fitxatgeAmbSortidaOblidada();
        foreach (['coordinator', 'worker'] as $rol) {
            Sanctum::actingAs($this->user($rol));
            $this->corregeix($log, ['end_time' => '2026-10-05 15:00', 'motiu' => 'Motiu prou llarg per provar'])->assertForbidden();
        }
    }
}
