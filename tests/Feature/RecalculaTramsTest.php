<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use App\Models\WorkSchedule;
use App\Services\WorkLogService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** En canviar l'horari, els trams dels fitxatges pendents es recalculen; el registre no es toca. */
class RecalculaTramsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::create(2026, 10, 7, 18, 0, 0, 'Europe/Madrid'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function horari(string $ini, string $fi): array
    {
        return array_map(fn ($d) => ['day' => $d, 'active' => true, 'start' => $ini, 'end' => $fi], [1, 2, 3, 4, 5]);
    }

    /** Com la captura: horari de 12:00 a 13:00 i fitxatge de 09:12 a 13:00 (dimecres). */
    private function cas(string $status = 'pending'): array
    {
        $s = WorkSchedule::create(['name' => 'Pràctiques', 'total_hours_weekly' => 5, 'days' => $this->horari('12:00', '13:00')]);
        $u = User::create(['name' => 'Pràctiques', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $s->id]);
        $log = WorkLog::create(['user_id' => $u->id, 'date' => '2026-10-07',
            'start_time' => '2026-10-07 09:12:00', 'end_time' => '2026-10-07 13:00:00',
            'start_location_match' => true, 'end_location_match' => true, 'status' => $status]);
        app(WorkLogService::class)->segmentWorkLog($log->fresh(), $u);

        return [$s, $u, $log];
    }

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'admin', 'active' => true]);
    }

    public function test_canviar_la_plantilla_recalcula_els_trams_dels_pendents(): void
    {
        [$s, , $log] = $this->cas();
        $this->assertEquals(1.0, (float) $log->fresh()->effective_hours); // abans: només 12:00-13:00
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/work-schedules/{$s->id}", ['days' => $this->horari('09:00', '13:00')])
            ->assertOk()->assertJsonPath('trams_recalculats', 1);

        $trams = WorkLogSegment::where('work_log_id', $log->id)->get();
        $this->assertCount(1, $trams);
        $this->assertSame('approved', $trams[0]->status);
        $this->assertSame('2026-10-07 09:12:00', $trams[0]->getRawOriginal('start_time'));
        $log->refresh();
        $this->assertEquals(3.8, (float) $log->effective_hours);
        // El registre no es toca
        $this->assertSame('2026-10-07 09:12:00', $log->getRawOriginal('start_time'));
        $this->assertSame('2026-10-07 13:00:00', $log->getRawOriginal('end_time'));
        $this->assertDatabaseHas('work_log_modifications', ['work_log_id' => $log->id, 'action' => 'modified']);
    }

    public function test_assignar_un_altre_horari_a_la_persona_tambe_recalcula(): void
    {
        [, $u, $log] = $this->cas();
        $nou = WorkSchedule::create(['name' => 'Matins', 'total_hours_weekly' => 20, 'days' => $this->horari('09:00', '13:00')]);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/users/{$u->id}", ['work_schedule_id' => $nou->id])
            ->assertOk()->assertJsonPath('trams_recalculats', 1);
        $this->assertEquals(3.8, (float) $log->fresh()->effective_hours);
    }

    public function test_no_toca_el_que_ja_ha_decidit_una_persona(): void
    {
        [$s, , $aprovat] = $this->cas('approved');
        [, , $ambDecisio] = $this->cas();
        WorkLogSegment::where('work_log_id', $ambDecisio->id)->where('status', 'pending')
            ->update(['status' => 'rejected']);
        Sanctum::actingAs($this->admin());

        $this->putJson("/api/v1/work-schedules/{$s->id}", ['days' => $this->horari('09:00', '13:00')])
            ->assertOk()->assertJsonPath('trams_recalculats', 0);
        $this->assertCount(2, WorkLogSegment::where('work_log_id', $aprovat->id)->get());
        $this->assertSame('rejected', WorkLogSegment::where('work_log_id', $ambDecisio->id)->orderBy('segment_number')->first()->status);
    }
}
