<?php

namespace Tests\Feature;

use App\Models\AbsenceType;
use App\Models\BreakSetting;
use App\Models\ExcedenciaType;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use Database\Seeders\CatalegBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Dades de base per a producció: sense persones i sense duplicar res si es torna a executar. */
class CatalegBaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_les_dades_de_base_sense_persones_i_es_pot_repetir(): void
    {
        $this->seed(CatalegBaseSeeder::class);
        $compte = fn () => [WorkSchedule::count(), AbsenceType::count(), ExcedenciaType::count(), Holiday::count(), BreakSetting::count()];
        $primer = $compte();

        $this->assertSame(3, $primer[0]);
        $this->assertGreaterThanOrEqual(12, $primer[1]); // + els de baixa que ja crea una migració
        $this->assertSame(4, $primer[2]);
        $this->assertGreaterThan(10, $primer[3]);
        $this->assertSame(1, $primer[4]);
        $this->assertSame(0, User::count(), 'No ha de crear cap persona.');

        $this->seed(CatalegBaseSeeder::class);
        $this->assertSame($primer, $compte(), 'Tornar-lo a executar no ha de duplicar res.');
    }
}
