<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Backfill dels pactes JA signats abans que el codi nasqués sol amb l'alta:
 * materialitza el codi anual de cada treballador parcial amb pacte que encara
 * no en tingui cap de vigent. Idempotent: es pot repetir sense duplicar res.
 */
class PacteBackfill extends Command
{
    protected $signature = 'pacte:backfill';

    protected $description = 'Materialitza el codi anual del pacte de complementàries per als treballadors que ja el tenen signat';

    public function handle(): int
    {
        $n = 0; $saltats = 0;
        foreach (User::where('role', 'worker')->where('pacte_complementaries', true)->get() as $u) {
            $c = $u->materialitzaPacte();
            if ($c) { $this->line("✓ {$u->name}: codi {$c->code} ({$c->authorized_hours} h fins {$c->valid_to->toDateString()})"); $n++; }
            else { $saltats++; }
        }
        $this->info("{$n} codis creats · {$saltats} saltats (vigent ja existent, jornada completa o topall esgotat).");

        return self::SUCCESS;
    }
}
