<?php

namespace App\Console\Commands;

use App\Support\Millores;
use Illuminate\Console\Command;

class GeneraMillores extends Command
{
    protected $signature = 'millores:generar';

    protected $description = 'Repas nocturn: busca millores de totes les aplicacions a partir dels indicadors reals';

    public function handle(): int
    {
        $r = Millores::genera();
        if (! $r['ok']) {
            $this->warn('[millores] ' . $r['motiu']);

            return self::SUCCESS;
        }
        $this->info(sprintf('[millores] %d propostes noves en %d ms', $r['noves'], $r['ms'] ?? 0));

        return self::SUCCESS;
    }
}
