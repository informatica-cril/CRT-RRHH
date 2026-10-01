<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migra start_time/end_time (work_logs) i start_time/end_time (work_log_segments),
 * guardats fins ara com a UTC real, a hora de Madrid directa — el mateix conveni
 * que ja usaven created_at/updated_at. Objectiu: que qualsevol consulta directa a
 * BD (auditoria, inspecció de treball) es pugui llegir tal qual, sense haver de
 * sumar-hi mentalment 1-2h.
 *
 * NOMÉS toca els 4 camps de work_logs (start_time, end_time, break_start_time,
 * break_end_time) i els 2 de work_log_segments (start_time, end_time). La resta
 * de columnes (created_at, updated_at, date...) ja estaven bé i no es toquen.
 *
 * Per defecte fa un DRY-RUN (no escriu res, només mostra quantes files canviarien
 * i un parell d'exemples). Cal passar --apply explícitament per escriure de veritat.
 */
class MigrarHoresFichatgeAMadrid extends Command
{
    protected $signature = 'worklogs:migrar-hores-a-madrid {--apply : Aplica els canvis de debò (sense això, només simula)}';
    protected $description = 'Converteix start_time/end_time/break_* de work_logs i work_log_segments de UTC a hora de Madrid directa';

    private const CAMPS_WORKLOG = ['start_time', 'end_time', 'break_start_time', 'break_end_time'];
    private const CAMPS_SEGMENT = ['start_time', 'end_time'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if (! $apply) {
            $this->warn('MODE SIMULACIÓ (dry-run). No s\'escriurà res a la BD.');
            $this->warn('Quan ho hagis revisat, torna a executar amb --apply per aplicar-ho de debò.');
        } else {
            $this->error('MODE APLICAR: es van a modificar dades reals. Assegura\'t d\'haver fet backup de la BD.');
            if (! $this->confirm('Has fet un backup de la base de dades i vols continuar?', false)) {
                $this->info('Cancel·lat.');
                return self::SUCCESS;
            }
        }

        $this->migrarTaula('work_logs', self::CAMPS_WORKLOG, $apply);
        $this->migrarTaula('work_log_segments', self::CAMPS_SEGMENT, $apply);

        $this->info($apply ? 'Fet.' : 'Simulació acabada. Executa amb --apply per aplicar-ho de debò.');
        return self::SUCCESS;
    }

    private function migrarTaula(string $taula, array $camps, bool $apply): void
    {
        $this->info("--- {$taula} ---");
        $files = DB::table($taula)->get();
        $actualitzades = 0;
        $exemplesMostrats = 0;

        foreach ($files as $fila) {
            $canvis = [];
            foreach ($camps as $camp) {
                $raw = $fila->{$camp} ?? null;
                if ($raw === null) {
                    continue;
                }
                // Convenció actual (abans de migrar): el valor guardat és UTC real.
                $nou = Carbon::parse($raw, 'UTC')->setTimezone('Europe/Madrid')->toDateTimeString();
                if ($nou !== $raw) {
                    $canvis[$camp] = $nou;
                }
            }

            if (empty($canvis)) {
                continue;
            }

            $actualitzades++;
            if ($exemplesMostrats < 5) {
                $detall = collect($canvis)->map(fn ($nou, $camp) => "{$camp}: {$fila->{$camp}} -> {$nou}")->implode(' | ');
                $this->line("  #{$fila->id}  {$detall}");
                $exemplesMostrats++;
            }

            if ($apply) {
                DB::table($taula)->where('id', $fila->id)->update($canvis);
            }
        }

        $this->info("{$taula}: " . ($apply ? 'actualitzades' : 'canviarien') . " {$actualitzades} files.");
    }
}
