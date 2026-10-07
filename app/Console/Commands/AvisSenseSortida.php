<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\WorkLogSenseSortidaController;
use App\Mail\SenseSortidaMail;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Cada matí avisa per correu les persones que tenen jornades d'altres dies sense sortida i
 * encara no han declarat a quina hora van acabar. Els recordatoris del mateix dia
 * (worklogs:send-reminders) s'acaben a mitjanit: sense això, la jornada quedava oberta i ningú
 * no se n'assabentava. Es repeteix cada dia fins que la persona ho declara.
 */
class AvisSenseSortida extends Command
{
    protected $signature = 'worklogs:avis-sense-sortida';
    protected $description = 'Correu a qui té jornades d\'altres dies sense sortida i sense declarar';

    /** Més enllà d'aquests dies no s'avisa (una jornada molt antiga la resol RRHH). */
    private const DIES_MAXIM = 31;

    public function handle(): int
    {
        $desde = Carbon::now('Europe/Madrid')->subDays(self::DIES_MAXIM)->toDateString();

        $perPersona = WorkLogSenseSortidaController::oberts()
            ->whereDate('date', '>=', $desde)
            ->with('user:id,name,email,active')
            ->orderBy('date')
            ->get()
            ->filter(fn ($l) => $l->user && $l->user->email && $l->user->active
                && WorkLogSenseSortidaController::declaracio($l) === null)
            ->groupBy('user_id');

        $enviats = 0;
        foreach ($perPersona as $logs) {
            $user = $logs->first()->user;
            try {
                Mail::to($user->email)->send(new SenseSortidaMail($user, $logs->values()));
                $enviats++;
            } catch (\Throwable $e) {
                $this->error("No s'ha pogut enviar a l'usuari #{$user->id}: {$e->getMessage()}");
            }
        }

        $this->info("Avisos enviats: {$enviats}");

        return self::SUCCESS;
    }
}
