<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Exporta els fitxatges d'una persona que toquen una franja horària, per portar-los a CRT
 * amb `fitxatges:importa`. No exporta coordenades GPS. El fitxer conté dades personals:
 * s'ha de generar FORA del repositori i esborrar-se un cop importat.
 *
 *   php artisan fitxatges:exporta "Adonay" /tmp/fitxatges.json --des=2026-10-21 --fins=2026-10-22
 */
class ExportaFitxatges extends Command
{
    protected $signature = 'fitxatges:exporta {persona : Part del nom} {out : Fitxer JSON de sortida}
        {--des= : Data inicial (YYYY-MM-DD)} {--fins= : Data final (YYYY-MM-DD)}
        {--franja-des=13:00} {--franja-fins=15:00}';
    protected $description = 'Exporta (sense GPS) els fitxatges d\'una persona que toquen una franja horària.';

    private const COLUMNES = [
        'date', 'start_time', 'end_time', 'status', 'rejection_reason', 'total_hours_worked',
        'hours_worked', 'effective_hours', 'hour_status', 'out_of_schedule', 'extra_hours_authorized',
        'extra_hours_unauthorized', 'auto_closed', 'fitxatge_automatic', 'verification_mode',
        'break_start_time', 'break_end_time', 'created_at', 'updated_at',
    ];

    public function handle(): int
    {
        $users = DB::table('users')->where('name', 'like', '%' . $this->argument('persona') . '%')->get(['id', 'name', 'email']);
        if ($users->count() !== 1) {
            $this->error("S'esperava 1 persona i n'hi ha {$users->count()}: " . $users->pluck('name')->implode(', '));
            return self::FAILURE;
        }
        $u = $users->first();

        $cols = array_values(array_filter(self::COLUMNES, fn ($c) => Schema::hasColumn('work_logs', $c)));
        $q = DB::table('work_logs')->where('user_id', $u->id)->orderBy('date')->orderBy('start_time');
        if ($this->option('des')) {
            $q->where('date', '>=', $this->option('des'));
        }
        if ($this->option('fins')) {
            $q->where('date', '<=', $this->option('fins'));
        }

        // Hores en hora de Madrid sense zona: es comparen com a text, sense convertir.
        $des = $this->option('franja-des');
        $fins = $this->option('franja-fins');
        $files = $q->get($cols)->filter(function ($r) use ($des, $fins) {
            $ini = substr((string) $r->start_time, 11, 5);
            $fi = $r->end_time ? substr((string) $r->end_time, 11, 5) : '23:59';
            if ($r->end_time && substr((string) $r->end_time, 0, 10) > substr((string) $r->start_time, 0, 10)) {
                $fi = '23:59';
            }
            return $ini <= $fins && $fi >= $des;
        })->values();

        file_put_contents($this->argument('out'), json_encode([
            'email' => $u->email,
            'name' => $u->name,
            'fitxatges' => $files,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("{$files->count()} fitxatges de {$u->name} escrits a {$this->argument('out')}. Esborreu el fitxer després d'importar-lo.");
        foreach ($files as $r) {
            $this->line("  {$r->date} {$r->start_time} → {$r->end_time} [{$r->status}]");
        }

        return self::SUCCESS;
    }
}
