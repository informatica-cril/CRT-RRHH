<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLocation;
use App\Models\WorkLog;
use App\Models\WorkLogAlert;
use App\Models\WorkLogSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** En assignar un centre o punt nou, els fitxatges pendents fora de zona es tornen a comprovar. */
class ReavaluaZonaTest extends TestCase
{
    use RefreshDatabase;

    // Punt nou (p. ex. un segon centre) i una posició a 50 m d'ell.
    private const NOU = [41.3900, 2.1700];
    private const A_PROP = [41.39045, 2.1700];
    private const LLUNY = [41.4500, 2.2500];

    private function persona(string $tipus = 'AMBULATORIA'): User
    {
        return User::create([
            'name' => 'Persona ' . uniqid(), 'email' => uniqid() . '@test.local',
            'password' => bcrypt('x'), 'role' => 'worker', 'active' => true, 'work_type' => $tipus,
        ]);
    }

    private function fitxatgeForaZona(User $u, array $pos, string $status = 'pending'): WorkLog
    {
        $log = WorkLog::create([
            'user_id' => $u->id, 'date' => '2026-10-05',
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-05 15:00:00',
            'total_hours_worked' => 7, 'hours_worked' => 0, 'hours_out_of_area' => 7, 'hour_status' => 'out_of_area',
            'start_location_lat' => $pos[0], 'start_location_lng' => $pos[1], 'start_location_match' => false,
            'end_location_lat' => $pos[0], 'end_location_lng' => $pos[1], 'end_location_match' => false,
            'status' => $status, 'segmented' => true,
        ]);
        WorkLogSegment::create([
            'work_log_id' => $log->id, 'segment_number' => 1,
            'start_time' => '2026-10-05 08:00:00', 'end_time' => '2026-10-05 15:00:00',
            'duration_minutes' => 420, 'status' => 'pending', 'in_zone' => false, 'in_schedule' => true,
        ]);
        WorkLogAlert::create(['work_log_id' => $log->id, 'user_id' => $u->id, 'type' => 'out_of_zone', 'message' => 'Fora de zona', 'scheduled_at' => now()]);

        return $log;
    }

    private function assignaPuntNou(User $u)
    {
        Sanctum::actingAs(User::create([
            'name' => 'RRHH', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'hr', 'active' => true,
        ]));
        $punt = WorkLocation::create(['name' => 'Centre nou', 'lat' => self::NOU[0], 'lng' => self::NOU[1], 'radius' => 100, 'active' => true]);

        return $this->postJson("/api/v1/work-locations/{$punt->id}/assign", ['user_id' => $u->id, 'valid_from' => '2026-10-06']);
    }

    public function test_el_fitxatge_pendent_dins_del_punt_nou_deixa_de_ser_fora_de_zona(): void
    {
        $u = $this->persona();
        $log = $this->fitxatgeForaZona($u, self::A_PROP);

        $this->assignaPuntNou($u)->assertOk()->assertJsonPath('fitxatges_reavaluats', 1);

        $log->refresh();
        $this->assertTrue($log->start_location_match);
        $this->assertTrue($log->end_location_match);
        $this->assertSame('ok', $log->hour_status);
        $this->assertEquals(0.0, (float) $log->hours_out_of_area);
        $this->assertEquals(7.0, (float) $log->effective_hours);
        $this->assertSame('pending', $log->status); // el fitxatge el valida igualment una persona

        $tram = WorkLogSegment::where('work_log_id', $log->id)->first();
        $this->assertTrue((bool) $tram->in_zone);
        $this->assertSame('approved', $tram->status);
        $this->assertNotNull(WorkLogAlert::where('work_log_id', $log->id)->first()->dismissed_at);
        $this->assertDatabaseHas('work_log_modifications', ['work_log_id' => $log->id, 'action' => 'modified']);
    }

    public function test_no_toca_els_que_queden_lluny_ni_els_ja_resolts(): void
    {
        $u = $this->persona();
        $lluny = $this->fitxatgeForaZona($u, self::LLUNY);
        $resolt = $this->fitxatgeForaZona($u, self::A_PROP, 'approved');

        $this->assignaPuntNou($u)->assertOk()->assertJsonPath('fitxatges_reavaluats', 0);

        $this->assertFalse($lluny->fresh()->start_location_match);
        $this->assertFalse($resolt->fresh()->start_location_match);
    }

    public function test_no_toca_un_tram_amb_audiencia_oberta(): void
    {
        $u = $this->persona();
        $log = $this->fitxatgeForaZona($u, self::A_PROP);
        WorkLogSegment::where('work_log_id', $log->id)->update(['audiencia_requested_at' => now(), 'audiencia_deadline' => now()->addDays(7)]);

        $this->assignaPuntNou($u)->assertOk();

        $tram = WorkLogSegment::where('work_log_id', $log->id)->first();
        $this->assertTrue((bool) $tram->in_zone);
        $this->assertSame('pending', $tram->status); // l'audiència la resol una persona
    }

    public function test_domiciliaria_no_es_recalcula(): void
    {
        $u = $this->persona('DOMICILIARIA');
        $log = $this->fitxatgeForaZona($u, self::A_PROP);

        $this->assignaPuntNou($u)->assertOk()->assertJsonPath('fitxatges_reavaluats', 0);
        $this->assertFalse($log->fresh()->start_location_match);
    }
}
