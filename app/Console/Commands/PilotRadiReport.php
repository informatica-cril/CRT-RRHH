<?php

namespace App\Console\Commands;

use App\Services\PilotRadiStats;
use Illuminate\Console\Command;

/**
 * Informe empíric del PILOT per a la validació del radi de verificació (EIPD §6.1.bis).
 * El càlcul viu a App\Services\PilotRadiStats (font única, compartida amb el panell
 * visual d'admin). Sense dades no inventa res: ho diu i surt.
 */
class PilotRadiReport extends Command
{
    protected $signature = 'pilot:radi-report {--from=} {--to=}';

    protected $description = 'Informe empíric del pilot (precisió GPS, distàncies, taxa fora_radi) per justificar el radi mínim davant el DPD';

    public function handle(): int
    {
        $from = $this->option('from') ?: now()->subDays(30)->toDateString();
        $to = $this->option('to') ?: now()->toDateString();
        $st = PilotRadiStats::compute($from, $to);

        $this->info("Informe del pilot — radi de verificació ({$from} → {$to})");
        $this->line(str_repeat('─', 60));

        if ($st['n_hitos'] === 0 && $st['n_visites'] === 0) {
            $this->warn('Sense dades d\'hitos en el període. El pilot encara no ha generat evidència.');

            return self::SUCCESS;
        }

        if ($st['precisio']) {
            $p = $st['precisio'];
            $this->line(sprintf('Precisió GPS reportada (n=%d hitos):', $st['n_hitos']));
            $this->line(sprintf('  p50=%d m · p90=%d m · p95=%d m · màx=%d m', $p['p50'], $p['p90'], $p['p95'], $p['max']));
            $this->info(sprintf('  → Radi mínim recomanat (BASE %d m + p95 precisió, tots els hitos): %d m',
                $st['radi_base_m'], $st['radi_recomanat_m']));
            if ($st['radi_marcatges_correctes_m']) {
                $this->info(sprintf('  → Radi segons EIPD (p95 dels MARCATGES CORRECTES): %d m ← mètrica de la resolució del DPD',
                    $st['radi_marcatges_correctes_m']));
            }
        }

        if ($st['distancies']) {
            $d = $st['distancies'];
            $f = $st['fora_radi'];
            $this->line(sprintf('Distàncies al domicili (n=%d visites):', $st['n_visites']));
            $this->line(sprintf('  p50=%d m · p90=%d m · p95=%d m · màx=%d m', $d['p50'], $d['p90'], $d['p95'], $d['max']));
            $this->line(sprintf('  fora_radi: %d de %d (%.1f%%)', $f['n'], $st['n_visites'], $f['pct']));
            if ($f['zona_grisa'] > 0) {
                $this->warn(sprintf('  ⚠ %d fora_radi en zona grisa (dist ≤ radi+20%%): candidats a fals negatiu — revisar amb el DPD', $f['zona_grisa']));
            }
        }

        $this->line(str_repeat('─', 60));
        $this->line('Aquest informe és l\'evidència empírica exigida per l\'EIPD (§6.1.bis): la taxa');
        $this->line('d\'error real del GPS en entorn urbà dens per fixar el radi mínim necessari.');

        return self::SUCCESS;
    }
}
