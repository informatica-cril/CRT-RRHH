<?php

namespace App\Console\Commands;

use App\Models\Zone;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Migració a la sectorització governada per domi (11-08-2026).
 *
 * Les zones antigues de RRHH (sense domi_microzona_id) queden substituïdes per les
 * que publica domi. Aquesta comanda mou les assignacions VIGENTS de zone_worker a la
 * zona domi amb el MATEIX territori (mateixos postal_codes i municipalities) i
 * desactiva les zones antigues. Una zona antiga amb treballadors i sense equivalent
 * exacte NO es toca i es reporta: la decideix una persona.
 *
 * Sense --apply és un assaig: diu què faria i no escriu res.
 */
class DomiMigraZones extends Command
{
    protected $signature = 'domi:migra-zones {--apply : Executa els canvis (sense això, assaig)}';

    protected $description = 'Mou les assignacions de les zones antigues a les zones governades per domi i desactiva les antigues';

    private function territori(Zone $z): string
    {
        $pc = collect($z->postal_codes ?? [])->map(fn ($c) => str_pad(preg_replace('/\D/', '', (string) $c), 5, '0', STR_PAD_LEFT))->sort()->values();
        $mu = collect($z->municipalities ?? [])->map(fn ($u) => mb_strtoupper(trim((string) $u)))->sort()->values();

        return $pc->implode(',').'|'.$mu->implode(',');
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $avui = now()->toDateString();

        /* Una SUBZONA (polígon dibuixat a domi) mai és destinació automàtica: té els
           mateixos CP que la zona sencera però un territori més petit, i moure-hi algú
           li retallaria l'àmbit en silenci. */
        $domi = Zone::where('active', true)->whereNotNull('domi_microzona_id')
            ->where('type', '!=', 'SUBZONA')->get();
        $perTerritori = [];
        foreach ($domi as $z) {
            $perTerritori[$this->territori($z)] ??= $z;
        }

        $legacy = Zone::where('active', true)->whereNull('domi_microzona_id')->get();
        if ($legacy->isEmpty()) {
            $this->info('Cap zona antiga activa: res a fer.');

            return self::SUCCESS;
        }

        $moguts = 0;
        $desactivades = 0;
        $bloquejades = [];

        foreach ($legacy as $z) {
            $vigents = DB::table('zone_worker')->where('zone_id', $z->id)
                ->where('valid_from', '<=', $avui)
                ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $avui))
                ->get();

            $dest = $perTerritori[$this->territori($z)] ?? null;

            if ($vigents->isNotEmpty() && ! $dest) {
                $bloquejades[] = sprintf('#%d «%s» (%d assignacions, sense equivalent domi)', $z->id, $z->name, $vigents->count());
                continue;
            }

            foreach ($vigents as $v) {
                $jaHi = DB::table('zone_worker')->where('zone_id', $dest->id)->where('user_id', $v->user_id)
                    ->where('valid_from', '<=', $avui)
                    ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $avui))
                    ->exists();
                $this->line(sprintf('  %s treballador %d: «%s» -> «%s»%s',
                    $apply ? 'MOC' : 'mouria', $v->user_id, $z->name, $dest->name, $jaHi ? ' (ja hi era: tanco la vella)' : ''));
                if ($apply) {
                    if ($jaHi) {
                        DB::table('zone_worker')->where('zone_id', $v->zone_id)->where('user_id', $v->user_id)
                            ->where('valid_from', $v->valid_from)->delete();
                    } else {
                        DB::table('zone_worker')->where('zone_id', $v->zone_id)->where('user_id', $v->user_id)
                            ->where('valid_from', $v->valid_from)->update(['zone_id' => $dest->id, 'updated_at' => now()]);
                    }
                }
                $moguts++;
            }

            $this->line(sprintf('  %s la zona antiga #%d «%s»', $apply ? 'DESACTIVO' : 'desactivaria', $z->id, $z->name));
            if ($apply) {
                $z->update(['active' => false]);
            }
            $desactivades++;
        }

        $this->newLine();
        $this->info(sprintf('%s: %d assignacions mogudes, %d zones antigues desactivades.',
            $apply ? 'FET' : 'ASSAIG (res escrit; torna-hi amb --apply)', $moguts, $desactivades));
        foreach ($bloquejades as $b) {
            $this->warn('NO TOCADA: '.$b);
        }

        return self::SUCCESS;
    }
}
