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

    public function test_divendres_amb_pausa_els_trams_quadren_i_deixa_d_estar_en_curs(): void
    {
        // Cas real: Dl-Dj 08:20-16:00, Dv 08:20-15:00; divendres 2/10 sortida corregida a les 16:00
        // i pausa amb segons (13:35:57). L'efectiu ha de ser 6h40 exactes (abans 6h39) i la
        // jornada no pot quedar «en curs».
        $dies = [];
        foreach ([1, 2, 3, 4] as $d) $dies[] = ['day' => $d, 'start' => '08:20', 'end' => '16:00'];
        $dies[] = ['day' => 5, 'start' => '08:20', 'end' => '15:00'];
        $horari = \App\Models\WorkSchedule::create(['name' => '37,5', 'total_hours_weekly' => 37.5, 'days' => $dies]);
        $treb = User::factory()->create(['role' => 'worker', 'active' => true, 'work_schedule_id' => $horari->id]);

        $log = WorkLog::create([
            'user_id' => $treb->id, 'date' => '2026-10-02', 'start_time' => '2026-10-02 08:27:00',
            'break_start_time' => '2026-10-02 13:35:57', 'break_end_time' => '2026-10-02 13:55:57',
            'start_location_match' => true, 'break_start_location_match' => true, 'break_end_location_match' => true,
            'status' => 'pending', 'hour_status' => 'in_progress',
        ]);

        Sanctum::actingAs($this->user('hr'));
        $this->corregeix($log, ['start_time' => '2026-10-02 08:20', 'end_time' => '2026-10-02 16:00', 'motiu' => 'No va fitxar la sortida'])->assertOk();

        $log->refresh();
        // 16:00 en un divendres que acaba a les 15:00: 1 h de més, però ja no «en curs».
        $this->assertSame('extra', $log->hour_status);
        $this->assertEquals(1.0, (float) $log->extra_hours_unauthorized);
        $this->assertEquals(6.67, (float) $log->effective_hours);
        $trams = WorkLogSegment::where('work_log_id', $log->id)->get();
        $this->assertSame(460, (int) $trams->sum('duration_minutes'));
        $this->assertSame(60, (int) $trams->where('in_schedule', false)->where('status', 'pending')->sum('duration_minutes'));
        // La sortida corregida no té marca GPS: no és «sortida fora de zona» (abans en creava l'alerta).
        $this->assertSame(0, \App\Models\WorkLogAlert::where('work_log_id', $log->id)->where('type', 'out_of_zone')->count());
    }

    public function test_corregir_l_hora_recalcula_les_hores_de_mes_sense_autoritzar(): void
    {
        // Cas real (Sonia Nocete, dj. 15/10, horari 14:20-20:00): fitxada 14:27-21:15 (1,12 h de més);
        // corregida a 14:20-21:30 en són 1,5. Abans quedaven les 1,12 h antigues.
        $horari = \App\Models\WorkSchedule::create(['name' => '28,33', 'total_hours_weekly' => 28.33,
            'days' => [['day' => 4, 'start' => '14:20', 'end' => '20:00']]]);
        $treb = User::factory()->create(['role' => 'worker', 'active' => true, 'work_schedule_id' => $horari->id]);
        $log = WorkLog::create([
            'user_id' => $treb->id, 'date' => '2026-10-01', 'start_time' => '2026-10-01 14:27:50',
            'end_time' => '2026-10-01 21:15:10', 'total_hours_worked' => 6.79, 'hours_worked' => 6.79,
            'extra_hours_unauthorized' => 1.12, 'hour_status' => 'extra',
            'start_location_match' => true, 'end_location_match' => true, 'status' => 'approved',
        ]);

        Sanctum::actingAs($this->user('hr'));
        $this->corregeix($log, ['start_time' => '2026-10-01 14:20', 'end_time' => '2026-10-01 21:30', 'motiu' => 'Hora declarada per la persona'])->assertOk();

        $log->refresh();
        $this->assertEquals(1.5, (float) $log->extra_hours_unauthorized);
        $this->assertSame('extra', $log->hour_status);

        // Si la correcció la deixa dins de l'horari, ja no hi ha res de més.
        $this->corregeix($log, ['end_time' => '2026-10-01 20:00', 'motiu' => 'Sortida real a les 20:00'])->assertOk();
        $log->refresh();
        $this->assertEquals(0.0, (float) $log->extra_hours_unauthorized);
        $this->assertSame('ok', $log->hour_status);
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
