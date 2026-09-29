<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigratePayrolls extends Command
{
    protected $signature   = 'migrate:payrolls {--dry-run : Show what would be migrated without inserting}';
    protected $description = 'Migra les nòmines de tmp_nominas → payrolls, amb deduplicació per DNI+mes+any';

    private array $monthNames = [
        1 => 'Gener', 2 => 'Febrer', 3 => 'Març',    4 => 'Abril',
        5 => 'Maig',  6 => 'Juny',   7 => 'Juliol',  8 => 'Agost',
        9 => 'Setembre', 10 => 'Octubre', 11 => 'Novembre', 12 => 'Desembre',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  MODE DRY-RUN: no s\'inserirà res.');
        }

        // ── 1. Stats de la font ────────────────────────────────
        $total = DB::table('tmp_nominas')
            ->where('id_antiguo', '>', 0)
            ->whereYear('fecha_nomina', '>', 2000)
            ->count();

        $this->info("📊 Total registres vàlids a tmp_nominas: {$total}");

        // ── 2. Construir CTE deduplicada ──────────────────────
        // Agafem el max(id_antiguo) per DNI+mes+any → elimina duplicats mantenint el més recent
        $dedupQuery = DB::table('tmp_nominas as t')
            ->select(
                DB::raw('MAX(t.id_antiguo) as id_antiguo'),
                't.dni',
                DB::raw('YEAR(t.fecha_nomina) as yr'),
                DB::raw('MONTH(t.fecha_nomina) as mo')
            )
            ->where('t.id_antiguo', '>', 0)
            ->whereYear('t.fecha_nomina', '>', 2000)
            ->whereNotNull('t.documento_pdf')
            ->where('t.documento_pdf', '!=', '0')
            ->where('t.dni', '!=', 'dni')
            ->groupBy('t.dni', 'yr', 'mo');

        $uniqueCount = (clone $dedupQuery)->get()->count();
        $this->info("📊 Únics (sense duplicats):  {$uniqueCount}");
        $this->info("📊 Duplicats eliminats:      " . ($total - $uniqueCount));

        // ── 3. Join amb users per DNI (inclou correccions de format) ────
        // Condicions de match: exacte, sense zero inicial, o amb zero inicial afegit
        $migratable = DB::table(DB::raw("({$dedupQuery->toSql()}) as dedup"))
            ->mergeBindings($dedupQuery)
            ->join('tmp_nominas as src', 'src.id_antiguo', '=', 'dedup.id_antiguo')
            ->join('users as u', function ($join) {
                $join->on('u.dni', '=', 'dedup.dni')                                    // exacte
                     ->orWhereRaw('u.dni = TRIM(LEADING \'0\' FROM dedup.dni)')         // elimina zero inicial
                     ->orWhereRaw('TRIM(LEADING \'0\' FROM u.dni) = TRIM(LEADING \'0\' FROM dedup.dni)'); // ambdós sense zero
            })
            ->select('src.*', 'u.id as new_user_id', 'dedup.yr', 'dedup.mo')
            ->get();

        $this->info("✅ Nòmines amb usuari trobat: {$migratable->count()}");

        // ── 4. DNIs sense match ───────────────────────────────
        $matchedDnis = $migratable->pluck('dni')->unique()->values()->toArray();

        $unmatchedDnis = DB::table(DB::raw("({$dedupQuery->toSql()}) as dedup2"))
            ->mergeBindings($dedupQuery)
            ->whereNotIn('dedup2.dni', $matchedDnis ?: ['__NONE__'])
            ->select('dedup2.dni', DB::raw('COUNT(*) as nomines'), DB::raw('MIN(dedup2.yr) as primer_any'), DB::raw('MAX(dedup2.yr) as ultim_any'))
            ->groupBy('dedup2.dni')
            ->get();

        $unmatched = DB::table('tmp_nominas as t')
            ->whereNotIn('t.dni', $matchedDnis ?: ['__NONE__'])
            ->where('t.id_antiguo', '>', 0)
            ->whereYear('t.fecha_nomina', '>', 2000)
            ->where('t.dni', '!=', 'dni')
            ->count();

        $this->warn("⚠️  Nòmines sense match de DNI: {$unmatched}");

        if ($unmatchedDnis->isNotEmpty()) {
            $this->newLine();
            $this->warn('DNIs no trobats a la taula users:');
            $headers = ['DNI', 'Núm. nòmines', 'Primer any', 'Últim any'];
            $rows = $unmatchedDnis->map(fn($r) => [
                $r->dni, $r->nomines, $r->primer_any, $r->ultim_any
            ])->toArray();
            $this->table($headers, $rows);
            $this->line('  → Afegeix el DNI a la fitxa del treballador i torna a executar.');
            $this->newLine();
        }

        // ── 5. Confirmar abans d'inserir ─────────────────────
        if ($dryRun) {
            $this->info('Dry-run completat. Executa sense --dry-run per fer la migració real.');
            return 0;
        }

        // Check existing payrolls
        $existing = DB::table('payrolls')->count();
        if ($existing > 0) {
            $this->warn("⚠️  Ja hi ha {$existing} nòmines a la taula payrolls.");
            if (! $this->confirm('Vols continuar? (es poden crear duplicats si ja has migrat)')) {
                $this->info('Cancel·lat.');
                return 0;
            }
        }

        // ── 6. Inserir ────────────────────────────────────────
        $this->info('Inserint nòmines...');
        $bar = $this->output->createProgressBar($migratable->count());
        $bar->start();

        $now = now();
        $inserted = 0;
        $skipped  = 0;

        foreach ($migratable->chunk(50) as $chunk) {
            $rows = [];
            foreach ($chunk as $rec) {
                $month = (int) $rec->mo;
                $year  = (int) $rec->yr;
                $title = 'Nòmina ' . ($this->monthNames[$month] ?? $month) . ' ' . $year;

                // Evita duplicats dins la mateixa execució (user_id + month + year)
                $alreadyExists = DB::table('payrolls')
                    ->where('user_id', $rec->new_user_id)
                    ->where('year', $year)
                    ->where('month', str_pad($month, 2, '0', STR_PAD_LEFT))
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Prefix data URI si no en té
                $pdfData = $rec->documento_pdf;
                if ($pdfData && ! str_starts_with($pdfData, 'data:')) {
                    $pdfData = 'data:application/pdf;base64,' . $pdfData;
                }

                $rows[] = [
                    'user_id'         => $rec->new_user_id,
                    'title'           => $title,
                    'month'           => str_pad($month, 2, '0', STR_PAD_LEFT),
                    'year'            => $year,
                    'amount'          => null,
                    'payroll_base64'  => $pdfData,
                    'file_name'       => $rec->documento_name . '.pdf',
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
                $inserted++;
                $bar->advance();
            }

            if (! empty($rows)) {
                DB::table('payrolls')->insert($rows);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Migrades:  {$inserted}");
        if ($skipped > 0) {
            $this->warn("⏭  Saltades (ja existien): {$skipped}");
        }
        $this->warn("⚠️  Pendents (DNI no trobat): {$unmatched}");
        $this->newLine();
        $this->info('Migració completada.');

        return 0;
    }
}
