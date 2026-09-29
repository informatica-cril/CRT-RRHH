<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Persones actives que comparteixen el mateix compte de l'app domiciliària.
 *
 * Quan dues fitxes apunten al mateix usuari de domi, el pont no sap de quina és
 * l'activitat que arriba: en una hi cauen les hores duplicades i de l'altra es
 * descarten en silenci perquè el DNI no casa. I aquelles hores són les que
 * alimenten la bossa anual i el sostre del planificador.
 *
 * Criteri de supervivència: es queda la fitxa amb activitat real (fitxatges i,
 * en cas d'empat, absències; després la més antiga). La duplicada es dona de
 * BAIXA, mai s'esborra: l'art. 34.9 de l'Estatut obliga a conservar el registre
 * horari quatre anys.
 *
 * El DNI del supervivent NO es toca mai. Si les dues fitxes en porten un de
 * diferent, es reporta perquè ho decideixi una persona: sobreescriure una
 * identitat a cegues és pitjor que el problema que s'arregla.
 */
class IdentitatsDuplicades extends Command
{
    protected $signature = 'identitats:duplicades {--aplica : Escriu els canvis (per defecte només informa)}';
    protected $description = 'Troba i resol persones actives que comparteixen compte de domi';

    public function handle(): int
    {
        $aplica = (bool) $this->option('aplica');

        $grups = DB::table('users')
            ->select('domi_username', DB::raw('COUNT(*) n'))
            ->whereNotNull('domi_username')->where('domi_username', '<>', '')
            ->where('active', true)
            ->groupBy('domi_username')->havingRaw('COUNT(*) > 1')
            ->pluck('n', 'domi_username');

        if ($grups->isEmpty()) {
            $this->info('Cap compte de domi compartit entre persones actives.');
            return self::SUCCESS;
        }

        $this->warn($grups->count() . ' compte(s) de domi compartits entre persones actives.');
        if (! $aplica) $this->line('Simulacio: no s\'escriu res. Torna-ho a executar amb --aplica.');

        $baixes = 0; $revisar = [];

        foreach ($grups as $compte => $n) {
            /* Els comptadors van a part i no sobre el model: assignar-los-hi els
               convertiria en camps a desar i el save() intentaria escriure columnes
               que no existeixen. */
            $fitxes = User::where('domi_username', $compte)->where('active', true)->get();
            $fit = []; $abs = [];
            foreach ($fitxes as $u) {
                $fit[$u->id] = DB::table('work_logs')->where('user_id', $u->id)->count();
                $abs[$u->id] = DB::table('absences')->where('user_id', $u->id)->count();
            }
            $fitxes = $fitxes->sortByDesc(fn($u) => [$fit[$u->id], $abs[$u->id], -$u->id])->values();

            $viu = $fitxes->first();
            $this->newLine();
            $this->line("== $compte ==");
            $this->line("   ES QUEDA  id={$viu->id}  dni=" . ($viu->dni ?: '(cap)')
                      . "  fitxatges={$fit[$viu->id]}  absencies={$abs[$viu->id]}  {$viu->name}");

            foreach ($fitxes->slice(1) as $dup) {
                $this->line("   BAIXA     id={$dup->id}  dni=" . ($dup->dni ?: '(cap)')
                          . "  fitxatges={$fit[$dup->id]}  absencies={$abs[$dup->id]}  {$dup->name}");

                if ($dup->dni && $viu->dni && $dup->dni !== $viu->dni) {
                    $revisar[] = "$compte: la fitxa que es dona de baixa (id={$dup->id}) porta el DNI {$dup->dni} "
                               . "i la que es queda (id={$viu->id}) porta {$viu->dni}. Cal decidir quin es el bo.";
                }
                if ($fit[$dup->id] > 0) {
                    $revisar[] = "$compte: la fitxa id={$dup->id} te {$fit[$dup->id]} fitxatge(s) que quedaran "
                               . "penjats d'una persona donada de baixa. Es conserven (art. 34.9 ET) pero no sumen.";
                }

                if ($aplica) {
                    /* Es desvincula del compte de domi perque el pont deixi d'atribuir-li
                       activitat, i es dona de baixa. Res s'esborra. */
                    $dup->domi_username = null;
                    $dup->active = false;
                    $dup->save();
                    $baixes++;
                }
            }
        }

        $this->newLine();
        if ($revisar) {
            $this->warn('Cal decisio humana:');
            foreach (array_unique($revisar) as $r) $this->line('  - ' . $r);
            $this->newLine();
        }

        $this->info($aplica ? "$baixes fitxa(es) donades de baixa i desvinculades de domi."
                            : 'Res escrit. Revisa la llista i torna-hi amb --aplica.');
        return self::SUCCESS;
    }
}
