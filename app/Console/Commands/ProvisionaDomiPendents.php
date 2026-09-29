<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DomiClient;
use Illuminate\Console\Command;

/**
 * Reintenta l'aprovisionament domi dels treballadors domiciliaris que encara no tenen compte
 * (p. ex. perquè domi estava caigut en el moment de l'alta). Idempotent: el costat domi crea,
 * vincula per DNI o actualitza. Es pot programar o llançar a mà.
 *   php artisan domi:provisiona-pendents
 */
class ProvisionaDomiPendents extends Command
{
    protected $signature = 'domi:provisiona-pendents {--apply : Aprovisiona de debò (per defecte NOMÉS llista)}';
    protected $description = 'Aprovisiona a domi els treballadors domiciliaris sense domi_username. Dry-run per defecte.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $pendents = User::where('role', 'worker')
            ->where('work_type', 'like', 'DOMICILIARIA%')
            /* PENDENT = domi no ho ha confirmat MAI, no «li falta el nom d'usuari».
               Fins al 31-07-2026 això mirava `domi_username IS NULL`, i com que l'Excel
               d'alta massiva omple aquell camp, després d'una càrrega ningú sortia pendent
               i ningú tenia compte a domi. Mesurat llavors: 41 domiciliaris actius i la
               comanda només en veia 31. */
            ->whereNull('domi_provisioned_at')
            ->where('active', true)->get();

        if ($pendents->isEmpty()) {
            $this->info('Cap treballador domiciliari pendent d\'aprovisionar.');
            return self::SUCCESS;
        }
        $this->info($apply ? 'MODE APPLY.' : 'DRY-RUN: només llista (afegiu --apply per aprovisionar).');
        $this->warn('Compte: si la persona JA té compte a domi però sense DNI a la fitxa, crear-ne un seria un DUPLICAT.');
        $this->warn('El costat domi ho bloqueja si el username base coincideix, però la protecció bona és completar el DNI a domi + domi:map-users.');

        foreach ($pendents as $u) {
            if (! $apply) {
                $this->line("  · {$u->name} (dni " . ($u->dni ?: 'SENSE') . ') → es crearia/vincularia a domi');
                continue;
            }
            $r = DomiClient::provisiona($u);
            if (! empty($r['ok'])) {
                $this->info("  ✓ {$u->name} → {$r['accio']} ({$r['username']})"
                    . (isset($r['password_temporal']) ? " · CONTRASENYA TEMPORAL: {$r['password_temporal']} (lliurar en mà; canvi forçat al primer accés)" : ''));
            } else {
                $this->warn("  ✗ {$u->name}: " . ($r['error'] ?? 'error'));
            }
        }

        return self::SUCCESS;
    }
}
