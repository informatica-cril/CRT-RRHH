<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WorkLog;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SeedAgendaDemo extends Command
{
    protected $signature = 'crt:seed-agenda-demo';

    protected $description = 'Font laboral de demo: hores de contracte i complementàries variables + historial de jornada, per provar els ponts amb domi';

    /** DNI (concilia amb domi) => hores/setmana, pacte compl., factor sobre el treballat real */
    private const PERFILS = [
        '90000907K' => ['h' => 37.5, 'pacte' => false, 'factor' => 1.04],  // fisio1 · COMPLETA: sense complementàries (només extraordinàries)
        '90000908E' => ['h' => 20.0, 'pacte' => false, 'factor' => 0.96],  // fisio2 · parcial, una mica per sota
        '90000909T' => ['h' => 30.0, 'pacte' => true,  'factor' => 1.00],  // fisio3 · 30 h, al dia
        '90000906C' => ['h' => 25.0, 'pacte' => false, 'factor' => 1.08],  // tpo1  · 25 h, prou per sobre
    ];

    private const DIES = [1 => 'Dilluns', 2 => 'Dimarts', 3 => 'Dimecres', 4 => 'Dijous', 5 => 'Divendres', 6 => 'Dissabte', 0 => 'Diumenge'];

    public function handle(): int
    {
        foreach (self::PERFILS as $dni => $cfg) {
            $u = User::where('dni', $dni)->first();
            if (! $u) { $this->warn("DNI $dni: sense usuari, saltat"); continue; }

            $hDia = round($cfg['h'] / 5, 4);
            $fi = $this->finalDia('09:00', $hDia);

            // Horari PROPI de cada treballador (mai compartit: evita que un pisi l'altre).
            $ws = new WorkSchedule();
            $ws->name = $u->name . ' · ' . $cfg['h'] . ' h';
            $ws->total_hours_weekly = $cfg['h'];
            $ws->days = array_map(function ($dow) use ($fi) {
                $lab = $dow >= 1 && $dow <= 5;
                return ['day' => $dow, 'name' => self::DIES[$dow], 'active' => $lab,
                        'start' => $lab ? '09:00' : null, 'end' => $lab ? $fi : null];
            }, [1, 2, 3, 4, 5, 6, 0]);
            $ws->save();

            $u->work_schedule_id = $ws->id;
            $u->work_type = 'DOMICILIARIA';
            $u->pacte_complementaries = $cfg['pacte'];
            $u->save();

            $nLogs = $this->historialJornada($u, $hDia, $cfg['factor']);

            $this->info(sprintf('%s · %s · %.1f h/set (09:00–%s) · pacte %s · %d dies de jornada (factor %.2f)',
                $dni, $u->name, $cfg['h'], $fi, $cfg['pacte'] ? 'SÍ' : 'no', $nLogs, $cfg['factor']));
        }

        $this->newLine();
        $this->line('Fet a RRHH. Ara des de domi: php tools/seed-agenda-demo.php --apply');
        return self::SUCCESS;
    }

    /** Jornada de l'1 de gener fins ahir, laborables, ~hores de contracte × factor. */
    private function historialJornada(User $u, float $hDia, float $factor): int
    {
        WorkLog::where('user_id', $u->id)
            ->whereBetween('date', [Carbon::now()->startOfYear()->toDateString(), Carbon::yesterday()->toDateString()])
            ->delete();

        $rows = [];
        for ($d = Carbon::now()->startOfYear(); $d->lt(Carbon::today()); $d->addDay()) {
            if ($d->isWeekend()) continue;
            $ef = round($hDia * $factor, 2);
            $rows[] = [
                'user_id' => $u->id, 'date' => $d->toDateString(),
                'start_time' => $d->toDateString() . ' 09:00:00',
                'end_time' => $d->copy()->addMinutes((int) round($ef * 60))->format('Y-m-d H:i:00'),
                'effective_hours' => $ef, 'hours_worked' => $ef, 'total_hours_worked' => $ef,
                'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $c) WorkLog::insert($c);
        return count($rows);
    }

    private function finalDia(string $inici, float $hores): string
    {
        $min = (int) round(strtotime($inici . ' UTC') / 60 + $hores * 60);
        return gmdate('H:i', $min * 60);
    }
}
