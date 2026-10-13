<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Migració 2026_10_13_000001: una sortida sense GPS no és «fora de zona a 4.600 km». */
class SortidaSenseGpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_corregeix_les_sortides_sense_gps_i_es_pot_desfer(): void
    {
        $u = User::create(['name' => 'P', 'email' => uniqid() . '@test.local', 'password' => bcrypt('x'), 'role' => 'worker', 'active' => true]);
        $base = ['user_id' => $u->id, 'date' => '2026-10-13', 'start_time' => '2026-10-13 09:07:00', 'end_time' => '2026-10-13 13:01:00',
            'status' => 'pending', 'start_location_match' => true, 'created_at' => now(), 'updated_at' => now()];
        // Sortida sense GPS validada contra el 0,0.
        $senseGps = DB::table('work_logs')->insertGetId($base + ['end_location_match' => false, 'end_location_distance' => 4606000,
            'location_match' => false, 'hour_status' => 'out_of_area', 'hours_out_of_area' => 0]);
        // Sortida amb GPS realment fora: no es toca.
        $foraDeVeritat = DB::table('work_logs')->insertGetId($base + ['end_location_lat' => '41.5', 'end_location_lng' => '2.1',
            'end_location_match' => false, 'end_location_distance' => 17000, 'location_match' => false, 'hour_status' => 'out_of_area']);

        $migracio = require database_path('migrations/2026_10_13_000001_sortides_sense_gps_no_son_fora_de_zona.php');
        $migracio->up();

        $l = DB::table('work_logs')->find($senseGps);
        $this->assertNull($l->end_location_match);
        $this->assertNull($l->end_location_distance);
        $this->assertSame('ok', $l->hour_status);
        $this->assertSame('out_of_area', DB::table('work_logs')->find($foraDeVeritat)->hour_status);

        $migracio->down();
        $this->assertSame('out_of_area', DB::table('work_logs')->find($senseGps)->hour_status);
    }
}
