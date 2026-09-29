<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;

class HolidaySeeder extends Seeder
{
    public function run()
    {
        $holidays = [
            // --- 2025 ---
            ['date' => '2025-01-01', 'name' => "Cap d'Any", 'type' => 'national', 'year' => 2025],
            ['date' => '2025-01-06', 'name' => 'Reis', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-04-18', 'name' => 'Divendres Sant', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-04-21', 'name' => 'Dilluns de Pasqua', 'type' => 'regional', 'year' => 2025],
            ['date' => '2025-05-01', 'name' => 'Festa del Treball', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-06-09', 'name' => 'Segona Pasqua', 'type' => 'local', 'year' => 2025],
            ['date' => '2025-06-24', 'name' => 'Sant Joan', 'type' => 'regional', 'year' => 2025],
            ['date' => '2025-08-15', 'name' => "L'Assumpció", 'type' => 'national', 'year' => 2025],
            ['date' => '2025-09-11', 'name' => 'Diada Nacional de Catalunya', 'type' => 'regional', 'year' => 2025],
            ['date' => '2025-09-24', 'name' => 'La Mercè', 'type' => 'local', 'year' => 2025],
            ['date' => '2025-10-12', 'name' => "Festa Nacional d'Espanya", 'type' => 'national', 'year' => 2025],
            ['date' => '2025-11-01', 'name' => 'Tots Sants', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-12-06', 'name' => 'Dia de la Constitució', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-12-08', 'name' => 'La Puríssima', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-12-25', 'name' => 'Nadal', 'type' => 'national', 'year' => 2025],
            ['date' => '2025-12-26', 'name' => 'Sant Esteve', 'type' => 'regional', 'year' => 2025],

            // --- 2026 ---
            ['date' => '2026-01-01', 'name' => "Cap d'Any", 'type' => 'national', 'year' => 2026],
            ['date' => '2026-01-06', 'name' => 'Reis', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-04-03', 'name' => 'Divendres Sant', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-04-06', 'name' => 'Dilluns de Pasqua', 'type' => 'regional', 'year' => 2026],
            ['date' => '2026-05-01', 'name' => 'Festa del Treball', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-05-25', 'name' => 'Segona Pasqua', 'type' => 'local', 'year' => 2026],
            ['date' => '2026-06-24', 'name' => 'Sant Joan', 'type' => 'regional', 'year' => 2026],
            ['date' => '2026-08-15', 'name' => "L'Assumpció", 'type' => 'national', 'year' => 2026],
            ['date' => '2026-09-11', 'name' => 'Diada Nacional de Catalunya', 'type' => 'regional', 'year' => 2026],
            ['date' => '2026-09-24', 'name' => 'La Mercè', 'type' => 'local', 'year' => 2026],
            ['date' => '2026-10-12', 'name' => "Festa Nacional d'Espanya", 'type' => 'national', 'year' => 2026],
            ['date' => '2026-11-01', 'name' => 'Tots Sants', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-06', 'name' => 'Dia de la Constitució', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-08', 'name' => 'La Puríssima', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-25', 'name' => 'Nadal', 'type' => 'national', 'year' => 2026],
            ['date' => '2026-12-26', 'name' => 'Sant Esteve', 'type' => 'regional', 'year' => 2026],
        ];

        foreach ($holidays as $h) {
            Holiday::updateOrCreate(
                ['date' => $h['date']],
                $h
            );
        }
    }
}
