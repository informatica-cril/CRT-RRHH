<?php

namespace App\Console\Commands;

use App\Services\IntegrityLedger;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Tancament i segellat diari del llibre d'integritat (1 segell FNMT/dia per a TOTES les apps).
 *   php artisan integrity:seal-day              → segella el dia d'avui
 *   php artisan integrity:seal-day --date=2026-07-29
 *   php artisan integrity:seal-day --no-tsa     → tanca la cadena sense demanar segell extern
 */
class SealIntegrityDay extends Command
{
    protected $signature = 'integrity:seal-day {--date= : Dia a segellar (Y-m-d); per defecte avui} {--no-tsa : No demanar segell de temps extern}';
    protected $description = 'Tanca i segella el llibre d\'integritat del dia (cadena de hash + segell de temps FNMT).';

    public function handle(IntegrityLedger $ledger): int
    {
        $day = $this->option('date') ? Carbon::parse($this->option('date')) : now();
        $withTsa = ! $this->option('no-tsa');

        $seal = $ledger->sealDay($day, $withTsa);

        $this->info(sprintf(
            'Segell %s · esdeveniments: %d · arrel: %s… · TSA: %s%s',
            $seal->seal_date->toDateString(),
            $seal->events_count,
            substr((string) $seal->root_hash, 0, 16),
            $seal->tsa_status,
            $seal->tsa_error ? ' (' . $seal->tsa_error . ')' : ''
        ));

        return self::SUCCESS;
    }
}
