<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Concilia usuaris RRHH ↔ fisios de domi PEL DNI (clau de conciliació, mai operativa) i persisteix
 * el resultat a users.domi_username — l'identificador operatiu que viatja per les APIs és sempre
 * el username de domi, MAI el DNI (dada personal: fora d'URLs i logs).
 *
 * Entrada: TSV "UserName<TAB>dni" exportat de domi:
 *   mysql domi_crt -N -e "SELECT UserName, dni FROM admin WHERE dni IS NOT NULL AND dni<>''" > /tmp/domi_dni.tsv
 *   php artisan domi:map-users /tmp/domi_dni.tsv          (dry-run: només informa)
 *   php artisan domi:map-users /tmp/domi_dni.tsv --apply  (desa el mapeig)
 */
class MapDomiUsers extends Command
{
    protected $signature = 'domi:map-users {tsv : Fitxer TSV UserName<TAB>dni exportat de domi} {--apply : Desa els canvis (per defecte dry-run)}';
    protected $description = 'Omple users.domi_username conciliant per DNI normalitzat amb l\'export de domi.';

    public function handle(): int
    {
        $path = $this->argument('tsv');
        if (! is_readable($path)) {
            $this->error("No es pot llegir {$path}");
            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'MODE APPLY: es desarà el mapeig.' : 'DRY-RUN: només informa (afegiu --apply per desar).');

        $norm = fn (?string $d) => strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $d));
        $ok = $conflicte = $sense = 0;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) !== 2) {
                continue;
            }
            [$domiUser, $dni] = $parts;
            $dniN = $norm($dni);
            if ($dniN === '') {
                continue;
            }
            $matches = User::whereRaw("UPPER(REPLACE(REPLACE(REPLACE(dni,'-',''),' ',''),'.','')) = ?", [$dniN])->get();

            if ($matches->count() === 1) {
                $u = $matches->first();
                $vell = $u->domi_username;
                if ($vell && $vell !== $domiUser) {
                    $this->warn("  CONFLICTE {$u->name}: ja mapejat a «{$vell}», domi diu «{$domiUser}» — no es toca");
                    $conflicte++;
                    continue;
                }
                $this->line("  ✓ {$domiUser} ↔ {$u->name}" . ($vell ? ' (ja estava)' : ''));
                if ($apply && ! $vell) {
                    $u->update(['domi_username' => $domiUser]);
                }
                $ok++;
            } elseif ($matches->count() > 1) {
                $this->warn("  CONFLICTE dni duplicat a RRHH per a «{$domiUser}»: " . $matches->pluck('name')->implode(', '));
                $conflicte++;
            } else {
                $this->line("  — {$domiUser}: cap usuari RRHH amb aquest DNI");
                $sense++;
            }
        }

        $this->info("Resultat: {$ok} mapejats · {$conflicte} conflictes · {$sense} sense correspondència.");
        $this->info('Els fisios de domi SENSE DNI no es poden conciliar automàticament: completar el DNI a domi o mapejar a mà (users.domi_username).');

        return self::SUCCESS;
    }
}
