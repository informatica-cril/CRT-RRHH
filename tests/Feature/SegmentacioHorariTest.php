<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use App\Models\WorkSchedule;
use App\Services\WorkLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Els trams es tallen amb l'horari del dia: han de quedar en hora de Madrid (com l'entrada i
 * la sortida) i amb l'horari del dia correcte, encara que l'horari tingui els 7 dies.
 */
class SegmentacioHorariTest extends TestCase
{
    use RefreshDatabase;

    /** @return \Illuminate\Support\Collection<WorkLogSegment> */
    private function trams(array $days, string $inici, string $fi)
    {
        $s = WorkSchedule::create(['name' => 'Tarda', 'total_hours_weekly' => 30, 'days' => $days]);
        $u = User::create(['name' => 'T', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'),
            'role' => 'worker', 'active' => true, 'work_schedule_id' => $s->id]);
        $log = WorkLog::create([
            'user_id' => $u->id, 'date' => '2026-10-07', // dimecres
            'start_time' => "2026-10-07 {$inici}:00", 'end_time' => "2026-10-07 {$fi}:00",
            'start_location_match' => true, 'end_location_match' => true, 'status' => 'pending',
        ]);
        app(WorkLogService::class)->segmentWorkLog($log->fresh(), $u);

        return WorkLogSegment::where('work_log_id', $log->id)->orderBy('segment_number')->get();
    }

    private function horari(string $ini, string $fi, array $dies): array
    {
        return array_map(fn ($d) => ['day' => $d, 'active' => true, 'start' => $ini, 'end' => $fi], $dies);
    }

    public function test_torn_de_tarda_fet_just_queda_en_un_tram_aprovat_amb_les_hores_reals(): void
    {
        $t = $this->trams($this->horari('14:00', '20:00', [1, 2, 3, 4, 5]), '14:00', '20:00');

        $this->assertCount(1, $t);
        $this->assertSame('2026-10-07 14:00:00', $t[0]->getRawOriginal('start_time'));
        $this->assertSame('2026-10-07 20:00:00', $t[0]->getRawOriginal('end_time'));
        $this->assertSame('approved', $t[0]->status);
        $this->assertSame(360, (int) $t[0]->duration_minutes);
    }

    public function test_els_talls_de_l_horari_queden_en_hora_de_madrid(): void
    {
        $t = $this->trams($this->horari('14:00', '20:00', [1, 2, 3, 4, 5]), '13:55', '20:05');

        $this->assertSame(['13:55', '14:00', '20:00'], $t->map(fn ($s) => substr($s->getRawOriginal('start_time'), 11, 5))->all());
        $this->assertSame(['14:00', '20:00', '20:05'], $t->map(fn ($s) => substr($s->getRawOriginal('end_time'), 11, 5))->all());
        $this->assertSame(['pending', 'approved', 'pending'], $t->pluck('status')->all());
    }

    public function test_amb_horari_de_7_dies_agafa_el_del_dia_correcte(): void
    {
        // Dimarts de 08:00 a 14:00 i dimecres de 14:00 a 20:00: el fitxatge és de dimecres.
        $days = [
            ['day' => 0, 'active' => false, 'start' => null, 'end' => null],
            ['day' => 1, 'active' => true, 'start' => '08:00', 'end' => '14:00'],
            ['day' => 2, 'active' => true, 'start' => '08:00', 'end' => '14:00'],
            ['day' => 3, 'active' => true, 'start' => '14:00', 'end' => '20:00'],
            ['day' => 4, 'active' => true, 'start' => '08:00', 'end' => '14:00'],
            ['day' => 5, 'active' => true, 'start' => '08:00', 'end' => '14:00'],
            ['day' => 6, 'active' => false, 'start' => null, 'end' => null],
        ];
        $t = $this->trams($days, '14:00', '20:00');

        $this->assertCount(1, $t);
        $this->assertTrue((bool) $t[0]->in_schedule);
        $this->assertSame('approved', $t[0]->status);
    }
}
