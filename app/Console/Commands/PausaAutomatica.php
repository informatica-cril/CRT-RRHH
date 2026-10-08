<?php

namespace App\Console\Commands;

use App\Models\BreakSetting;
use App\Models\WorkLog;
use App\Services\PausaObligatoria;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Obre i tanca la pausa obligatòria des del servidor (cada minut), perquè no depengui
 * que la pantalla del treballador estigui oberta (ordinadors compartits).
 */
class PausaAutomatica extends Command
{
    protected $signature = 'worklogs:pausa-automatica';
    protected $description = 'Obre la pausa obligatòria a l\'hora que toca i la tanca en acabar la durada';

    public function handle(PausaObligatoria $pausa): int
    {
        $ara = Carbon::now('Europe/Madrid');
        $settings = BreakSetting::getSettings();
        $durada = (int) $settings->break_duration_minutes;
        // Només jornades d'avui o de les darreres 24 h: una jornada oblidada oberta de fa dies no rep pausa.
        $desde = $ara->copy()->subDay()->toDateTimeString();

        $obertes = 0;
        WorkLog::whereNull('end_time')
            ->where('start_time', '>=', $desde)
            ->where(fn ($q) => $q->whereNull('break_status')->orWhere('break_status', 'pending'))
            ->whereNull('break_end_time')
            ->with('user.workSchedule')
            ->get()
            ->each(function (WorkLog $log) use ($pausa, $settings, $ara, $durada, &$obertes) {
                // Als autònoms no els aplica el registre de jornada.
                if (! $log->user || $log->user->esAutonom()) {
                    return;
                }
                $hora = $pausa->horaPausa($log, $log->user, $settings);
                if ($hora && $hora->lte($ara) && $pausa->obrir($log, $ara, $durada)) {
                    $obertes++;
                }
            });

        $tancades = 0;
        WorkLog::where('break_status', 'active')
            ->whereNull('break_end_time')
            ->where('start_time', '>=', $desde)
            ->get()
            ->each(function (WorkLog $log) use ($pausa, $ara, $durada, &$tancades) {
                if ($pausa->tancarSiHaAcabat($log, $ara, $durada)) {
                    $tancades++;
                }
            });

        $this->info("Pauses obertes: {$obertes}. Pauses tancades: {$tancades}.");

        return self::SUCCESS;
    }
}
