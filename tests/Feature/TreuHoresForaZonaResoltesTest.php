<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Migració 2026_10_08_000001: treu el «-Xh (Fora Zona)» dels fitxatges que els trams ja donen per bons. */
class TreuHoresForaZonaResoltesTest extends TestCase
{
    use RefreshDatabase;

    private function fitxatge(int $userId, bool $entradaDins, string $estatTram): int
    {
        $id = DB::table('work_logs')->insertGetId([
            'user_id' => $userId, 'date' => '2026-10-05', 'start_time' => '2026-10-05 09:08:00', 'end_time' => '2026-10-05 13:28:00',
            'total_hours_worked' => 4.33, 'hours_worked' => 0, 'hours_out_of_area' => 4.33, 'hour_status' => 'out_of_area',
            'start_location_match' => $entradaDins, 'end_location_match' => false, 'segmented' => true, 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('work_log_segments')->insert([
            'work_log_id' => $id, 'segment_number' => 1, 'start_time' => '2026-10-05 09:08:00', 'end_time' => '2026-10-05 13:28:00',
            'duration_minutes' => 260, 'in_zone' => $entradaDins, 'in_schedule' => true, 'status' => $estatTram,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_corregeix_nomes_els_resolts_pels_trams_i_es_pot_desfer(): void
    {
        $u = User::create(['name' => 'P', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        $resolt = $this->fitxatge($u->id, true, 'approved');
        $foraDeVeritat = $this->fitxatge($u->id, false, 'pending');

        $migracio = require database_path('migrations/2026_10_08_000001_treu_hores_fora_zona_ja_resoltes_per_trams.php');
        $migracio->up();

        $this->assertEquals(0, (float) DB::table('work_logs')->find($resolt)->hours_out_of_area);
        $this->assertEquals(4.33, (float) DB::table('work_logs')->find($resolt)->hours_worked);
        $this->assertSame('out_of_area', DB::table('work_logs')->find($resolt)->hour_status);
        $this->assertEquals(4.33, (float) DB::table('work_logs')->find($foraDeVeritat)->hours_out_of_area);

        $migracio->down();
        $this->assertEquals(4.33, (float) DB::table('work_logs')->find($resolt)->hours_out_of_area);
        $this->assertEquals(0, (float) DB::table('work_logs')->find($resolt)->hours_worked);
    }
}
