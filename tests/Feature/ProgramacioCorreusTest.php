<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/** Correus automàtics programats: què s'envia sol i què no. */
class ProgramacioCorreusTest extends TestCase
{
    private function comandaments(): array
    {
        return collect(app(Schedule::class)->events())->map(fn ($e) => (string) $e->command)->all();
    }

    public function test_no_s_envia_el_correu_diari_de_jornades_d_altres_dies(): void
    {
        // Decisió de RRHH (08-10-2026): arribava per fitxatges antics i es repetia cada dia.
        foreach ($this->comandaments() as $c) {
            $this->assertStringNotContainsString('worklogs:avis-sense-sortida', $c);
        }
    }

    public function test_els_recordatoris_del_mateix_dia_segueixen(): void
    {
        $this->assertTrue(collect($this->comandaments())->contains(fn ($c) => str_contains($c, 'worklogs:send-reminders')));
    }
}
