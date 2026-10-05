<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hospital de Barcelona (SCIAS, av. Diagonal 658-660): personal de CRT que hi treballa.
 *
 * Dues coses, perquè serveixen per a coses diferents:
 *  - Departament «Hospital de Barcelona»: el dada de qui hi és (fitxa → Lloc i servei, i v_treballadors).
 *  - Centre de fitxatge amb el seu punt: on poden fitxar. L'app no té pantalla per crear centres
 *    (els de CRIL van sortir de dades antigues), per això es crea aquí.
 * Coordenades d'OpenStreetMap; radi de 150 m. Idempotent: si ja hi són, no es dupliquen.
 */
return new class extends Migration
{
    private const CODI = 'HOSP_BCN';
    private const NOM = 'Hospital de Barcelona';

    public function up(): void
    {
        $ara = now();

        if (! DB::table('departments')->where('code', self::CODI)->exists()) {
            DB::table('departments')->insert([
                'code' => self::CODI, 'name' => self::NOM, 'service' => 'RHB', 'modality' => 'HOSP',
                'sort' => (int) DB::table('departments')->max('sort') + 1, 'active' => true,
                'created_at' => $ara, 'updated_at' => $ara,
            ]);
        }

        $centre = DB::table('ambulatory_centers')->where('name', self::NOM)->value('id');
        if (! $centre) {
            $centre = DB::table('ambulatory_centers')->insertGetId([
                'name' => self::NOM, 'active' => true, 'created_at' => $ara, 'updated_at' => $ara,
            ]);
        }
        if (! DB::table('work_locations')->where('ambulatory_center_id', $centre)->exists()) {
            DB::table('work_locations')->insert([
                'ambulatory_center_id' => $centre, 'name' => self::NOM,
                'address' => 'Av. Diagonal, 658-660, 08034 Barcelona',
                'lat' => 41.3899084, 'lng' => 2.1297145, 'radius' => 150, 'active' => true,
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
        $dep = DB::table('departments')->where('code', self::CODI)->value('id');
        if ($dep) {
            DB::table('department_user')->where('department_id', $dep)->delete();
            DB::table('departments')->where('id', $dep)->delete();
        }
    }
};
