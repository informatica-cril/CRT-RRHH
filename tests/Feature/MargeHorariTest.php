<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Migració 2026_10_13_000003: trams curts fora d'horari ja creats passen a aprovats (i es pot desfer). */
class MargeHorariTest extends TestCase
{
    use RefreshDatabase;

    public function test_aprova_el_tram_curt_i_respecta_els_llargs_i_els_fora_de_zona(): void
    {
        $u = User::create(['name' => 'P', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        $log = WorkLog::create(['user_id' => $u->id, 'date' => '2026-10-13', 'start_time' => '2026-10-13 09:00:00',
            'end_time' => '2026-10-13 16:00:17', 'status' => 'pending', 'segmented' => true]);
        $tram = fn ($n, $ini, $fi, $min, $horari, $zona, $estat) => WorkLogSegment::create(['work_log_id' => $log->id,
            'segment_number' => $n, 'start_time' => "2026-10-13 $ini", 'end_time' => "2026-10-13 $fi", 'duration_minutes' => $min,
            'in_schedule' => $horari, 'in_zone' => $zona, 'status' => $estat]);
        $tram(1, '09:00:00', '16:00:00', 420, true, true, 'approved');
        $curt = $tram(2, '16:00:00', '16:00:17', 0, false, true, 'pending');

        $log2 = WorkLog::create(['user_id' => $u->id, 'date' => '2026-10-12', 'start_time' => '2026-10-12 09:00:00',
            'end_time' => '2026-10-12 16:30:00', 'status' => 'pending', 'segmented' => true]);
        WorkLogSegment::create(['work_log_id' => $log2->id, 'segment_number' => 1, 'start_time' => '2026-10-12 09:00:00', 'end_time' => '2026-10-12 16:00:00', 'duration_minutes' => 420, 'in_schedule' => true, 'in_zone' => true, 'status' => 'approved']);
        $llarg = WorkLogSegment::create(['work_log_id' => $log2->id, 'segment_number' => 2, 'start_time' => '2026-10-12 16:00:00', 'end_time' => '2026-10-12 16:30:00', 'duration_minutes' => 30, 'in_schedule' => false, 'in_zone' => true, 'status' => 'pending']);

        $m = require database_path('migrations/2026_10_13_000003_marge_horari_trams_curts.php');
        $m->up();

        $this->assertSame('approved', $curt->fresh()->status);
        $this->assertSame('pending', $llarg->fresh()->status);
        $this->assertEquals(7.0, (float) $log->fresh()->effective_hours);

        $m->down();
        $this->assertSame('pending', $curt->fresh()->status);
    }
}
