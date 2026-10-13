<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Migració 2026_10_13_000002: el centre «CRT» (Comte Borrell) es pot assignar a CRT com a CRIL. */
class CentreCrtBorrellTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_centre_crt_existeix_amb_el_seu_punt_i_no_es_duplica(): void
    {
        $migracio = require database_path('migrations/2026_10_13_000002_add_centre_crt_borrell.php');
        $migracio->up();   // ja l'ha executat RefreshDatabase: no ha de duplicar

        $centres = DB::table('ambulatory_centers')->where('name', 'CRT')->get();
        $this->assertCount(1, $centres);
        $punts = DB::table('work_locations')->where('ambulatory_center_id', $centres[0]->id)->get();
        $this->assertCount(1, $punts);
        $this->assertEqualsWithDelta(41.37677335, (float) $punts[0]->lat, 0.000001);
        $this->assertSame(50, (int) $punts[0]->radius);

        $migracio->down();
        $this->assertFalse(DB::table('ambulatory_centers')->where('name', 'CRT')->exists());
    }
}
