<?php

namespace App\Console\Commands;

use App\Models\Absence;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkLogModification;
use App\Services\HorariDia;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fitxatge automàtic de gerència (Direcció, 20-10-2026): per a qui ho té activat (ho activa admin a
 * la fitxa), crea l'entrada i la sortida de cada tram de l'horari del dia (quadrant o plantilla),
 * aprovades i marcades com a automàtiques. No es crea res si aquell dia:
 *   - és festiu, té un permís aprovat de dia sencer o no té horari,
 *   - la persona ja hi té algun fitxatge (si ha fitxat ella, mana el seu).
 * Es mira avui i ahir (per si un dia no es va executar); tornar-lo a passar no duplica res.
 * Les hores es llegeixen en hora de Madrid sense zona, com la resta de fitxatges.
 */
class FitxatgeAutomatic extends Command
{
    protected $signature = 'worklogs:fitxatge-automatic {--dia= : Només aquest dia (AAAA-MM-DD)}';

    protected $description = "Crea els fitxatges de qui té el fitxatge automàtic activat (gerència)";

    public function handle(): int
    {
        $ara = now('Europe/Madrid');
        $dies = $this->option('dia')
            ? [Carbon::parse($this->option('dia'), 'Europe/Madrid')->toDateString()]
            : [$ara->copy()->subDay()->toDateString(), $ara->toDateString()];

        $creats = 0;
        foreach (User::where('fitxatge_automatic', true)->where('active', true)->get() as $u) {
            foreach ($dies as $dia) {
                $creats += $this->perDia($u, $dia, $ara);
            }
        }
        $this->info("Fitxatges automàtics creats: {$creats}");

        return self::SUCCESS;
    }

    private function perDia(User $u, string $dia, Carbon $ara): int
    {
        if (Holiday::whereDate('date', $dia)->exists()) return 0;
        $permis = Absence::where('user_id', $u->id)->where('approved', true)
            ->whereDate('start_date', '<=', $dia)->whereDate('end_date', '>=', $dia)
            ->whereNull('hora_inici')->exists();
        if ($permis) return 0;
        if (WorkLog::where('user_id', $u->id)->whereDate('date', $dia)->exists()) return 0;

        // Només els trams que ja han acabat: no es fitxa res del futur.
        $trams = array_filter(HorariDia::trams($u, $dia),
            fn ($t) => Carbon::parse("{$dia} {$t['end']}", 'Europe/Madrid')->lte($ara));
        if (! $trams) return 0;

        DB::transaction(function () use ($u, $dia, $trams) {
            foreach ($trams as $t) {
                $inici = Carbon::parse("{$dia} {$t['start']}", 'Europe/Madrid');
                $fi = Carbon::parse("{$dia} {$t['end']}", 'Europe/Madrid');
                $hores = round($inici->diffInMinutes($fi) / 60, 4);
                $log = WorkLog::create([
                    'user_id' => $u->id,
                    'date' => $dia,
                    'start_time' => $inici->toDateTimeString(),
                    'end_time' => $fi->toDateTimeString(),
                    'total_hours_worked' => $hores,
                    'hours_worked' => $hores,
                    'effective_hours' => $hores,
                    'hour_status' => 'ok',
                    'status' => 'approved',
                    'fitxatge_automatic' => true,
                ]);
                $log->forceFill(['auto_approved_at' => now()])->save();
                WorkLogModification::create([
                    'work_log_id' => $log->id, 'user_id' => $u->id, 'action' => 'created',
                    'new_values' => ['fitxatge_automatic' => true, 'inici' => $t['start'], 'fi' => $t['end']],
                    'comment' => "Fitxatge automàtic (gerència): entrada i sortida de l'horari del dia",
                ]);
            }
        });

        return count($trams);
    }
}
