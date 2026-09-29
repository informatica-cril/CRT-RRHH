<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('worklogs:send-reminders')->everyMinute();
        // Purga mensual de coordenades > 48 mesos (minimització RGPD)
        // Purga DIÀRIA (EIPD Annex V): coordenades a 30 dies, registre a 4 anys
        $schedule->command('locations:purge --apply')->dailyAt('03:30');
        // Purga dels indicadors de rendiment als 4 anys del tancament del període (política 3.7).
        // Mensual: el termini es compta en anys i la tramesa que l'alimenta també és mensual.
        $schedule->command('rendiment:purge --apply')->monthlyOn(1, '04:00');
        // Segell d'integritat diari (1 crèdit FNMT/dia; engloba les firmes de totes les apps).
        $schedule->command('integrity:seal-day')->dailyAt(config('integrity.seal_at', '23:45'));
        /* Bitllet del compte de servei de domi: si caduca sense avisar, el pont entre
           les dues apps mor en silenci. Deixa avís a la traça d'auditoria (té pantalla). */
        $schedule->command('domi:service-account --estat')->dailyAt('07:15');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
