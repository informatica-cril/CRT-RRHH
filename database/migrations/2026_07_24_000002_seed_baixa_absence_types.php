<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Afegeix les BAIXES laborals com a tipus de permís/absència (categoria 'baja'):
     *  - Baixa IT (malaltia comuna)
     *  - Baixa AT (accident de treball)
     * Les pot introduir tant el treballador (autoservei) com el Responsable RRHH.
     * Remunerades, amb justificació obligatòria i sense tope (durada oberta).
     * Insert idempotent (no duplica si ja existeixen pel nom).
     */
    private array $tipus = [
        ['name' => 'Baixa IT (malaltia comuna)',    'category' => 'baja'],
        ['name' => 'Baixa AT (accident de treball)', 'category' => 'baja'],
    ];

    public function up(): void
    {
        $now = now();
        foreach ($this->tipus as $t) {
            if (DB::table('absence_types')->where('name', $t['name'])->exists()) {
                continue;
            }
            DB::table('absence_types')->insert([
                'name'                   => $t['name'],
                'category'               => $t['category'],
                'recoverable'            => false,
                'remunerated'            => true,
                'max_days'               => null,
                'max_per_year'           => null,
                'max_lifetime'           => null,
                'requires_justification' => true,
                'advance_notice_hours'   => 0,
                'extends_with_travel'    => false,
                'extra_days_travel'      => null,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('absence_types')
            ->whereIn('name', array_column($this->tipus, 'name'))
            ->delete();
    }
};
