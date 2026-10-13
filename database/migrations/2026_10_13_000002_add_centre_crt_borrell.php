<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Centre de fitxatge «CRT» (seu de Comte Borrell), el mateix que té CRIL: a CRT només hi havia
 * l'Hospital de Barcelona i no es podia assignar la seu. Mateixes coordenades i radi que a CRIL (50 m).
 * Idempotent: si ja hi és, no es duplica.
 */
return new class extends Migration
{
    private const NOM = 'CRT';

    public function up(): void
    {
        $ara = now();
        $centre = DB::table('ambulatory_centers')->where('name', self::NOM)->value('id');
        if (! $centre) {
            $centre = DB::table('ambulatory_centers')->insertGetId([
                'name' => self::NOM, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara,
            ]);
        }
        if (! DB::table('work_locations')->where('ambulatory_center_id', $centre)->exists()) {
            DB::table('work_locations')->insert([
                'ambulatory_center_id' => $centre, 'name' => self::NOM, 'address' => 'Ambulatorio',
                'lat' => 41.37677335, 'lng' => 2.16336465, 'radius' => 50, 'active' => true,
                'created_at' => $ara, 'updated_at' => $ara,
            ]);
        }
    }

    public function down(): void
    {
        $centre = DB::table('ambulatory_centers')->where('name', self::NOM)->value('id');
        if ($centre) {
            DB::table('work_locations')->where('ambulatory_center_id', $centre)->delete();
            DB::table('ambulatory_center_user')->where('ambulatory_center_id', $centre)->delete();
            DB::table('ambulatory_centers')->where('id', $centre)->delete();
        }
    }
};
