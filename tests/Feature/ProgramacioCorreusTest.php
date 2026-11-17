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

    public function test_el_recordatori_de_jornada_oberta_ja_no_es_un_correu_programat(): void
    {
        // Decisió de RRHH (17-11-2026): es veu com a notificació dins de l'app.
        foreach ($this->comandaments() as $c) {
            $this->assertStringNotContainsString('worklogs:send-reminders', $c);
        }
    }
}
