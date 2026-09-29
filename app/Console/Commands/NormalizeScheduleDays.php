<?php

namespace App\Console\Commands;

use App\Models\WorkSchedule;
use Illuminate\Console\Command;

class NormalizeScheduleDays extends Command
{
    protected $signature = 'schedules:normalize-days {--apply : Aplica els canvis (per defecte és dry-run)}';

    protected $description = 'Normalitza work_schedules.days (day/active/start/end). Per defecte NOMÉS informa (dry-run).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply
            ? '⚠️  MODE APPLY: es desaran els canvis a work_schedules.'
            : '🔎 MODE DRY-RUN: no es desa res. Usa --apply per aplicar.');
        $this->newLine();

        $schedules = WorkSchedule::all();
        $changed = 0;
        $malformed = [];

        // Comptar treballadors actius per plantilla (per prioritzar)
        $usersBySchedule = \App\Models\User::where('active', 1)
            ->selectRaw('work_schedule_id, COUNT(*) as n')
            ->groupBy('work_schedule_id')->pluck('n', 'work_schedule_id');

        $rows = [];
        foreach ($schedules as $s) {
            $original = is_array($s->days) ? $s->days : (json_decode($s->days ?? '[]', true) ?: []);
            $normalized = WorkSchedule::normalizeDays($original);

            $origJson = json_encode($original);
            $normJson = json_encode($normalized);

            $emptyBefore = count($original) === 0;
            $missingActive = collect($original)->contains(fn ($d) => is_array($d) && ! array_key_exists('active', $d));
            $missingDay = collect($original)->contains(fn ($d) => is_array($d) && ! isset($d['day']));
            $droppedEntries = count($original) - count($normalized);

            if ($origJson !== $normJson) {
                $changed++;
                $rows[] = [
                    'id' => $s->id,
                    'actius' => (int) ($usersBySchedule[$s->id] ?? 0),
                    'name' => mb_strimwidth($s->name, 0, 40, '…'),
                    'entrades' => count($original) . '→' . count($normalized),
                    'issues' => implode(',', array_filter([
                        $emptyBefore ? 'BUIT' : null,
                        $missingActive ? 'sense-active' : null,
                        $missingDay ? 'sense-day' : null,
                        $droppedEntries > 0 ? "descarta-$droppedEntries" : null,
                    ])) ?: '—',
                ];
            }

            // Plantilles que quedarien sense cap dia laborable (risc: "no treballa")
            $activeAfter = collect($normalized)->where('active', true)->count();
            if ($activeAfter === 0 && ($usersBySchedule[$s->id] ?? 0) > 0) {
                $malformed[] = $s->id . ' (' . ($usersBySchedule[$s->id] ?? 0) . ' actius): ' . $s->name;
            }

            if ($apply && $origJson !== $normJson) {
                $s->days = $normalized;
                $s->saveQuietly();
            }
        }

        if ($rows) {
            $this->table(['id', 'actius', 'nom', 'entrades', 'problemes'], $rows);
        }
        $this->newLine();
        $this->line("Plantilles que canviarien: <fg=yellow>$changed</> de " . $schedules->count());

        if ($malformed) {
            $this->newLine();
            $this->error('⛔ Plantilles SENSE cap dia laborable després de normalitzar (assignades a actius) — cal revisar-les a mà:');
            foreach ($malformed as $m) {
                $this->line('   • ' . $m);
            }
        }

        $this->newLine();
        $this->info($apply ? "✅ Aplicats $changed canvis." : 'ℹ️  Dry-run: cap canvi desat. Revisa i executa amb --apply (fes BACKUP abans).');

        return self::SUCCESS;
    }
}
