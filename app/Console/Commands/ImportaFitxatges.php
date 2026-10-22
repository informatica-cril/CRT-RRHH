<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Importa el JSON generat per `fitxatges:exporta` (altra instal·lació). La persona es busca per
 * correu (o per nom amb --usuari=ID). Idempotent: no duplica un fitxatge amb el mateix dia i entrada.
 * Els fitxatges entren amb l'estat original; per defecte només es mostra què es faria (--aplica per escriure).
 *
 *   php artisan fitxatges:importa /tmp/fitxatges.json --aplica
 */
class ImportaFitxatges extends Command
{
    protected $signature = 'fitxatges:importa {in : Fitxer JSON} {--usuari= : ID d\'usuari de destí si el correu no coincideix} {--aplica}';
    protected $description = 'Importa fitxatges exportats des d\'una altra instal·lació.';

    public function handle(): int
    {
        $dades = json_decode((string) file_get_contents($this->argument('in')), true);
        if (!is_array($dades) || !isset($dades['fitxatges'])) {
            $this->error('Fitxer no vàlid.');
            return self::FAILURE;
        }

        $user = $this->option('usuari')
            ? DB::table('users')->find($this->option('usuari'))
            : DB::table('users')->whereRaw('lower(email) = ?', [strtolower((string) $dades['email'])])->first();
        if (!$user) {
            $this->error("No trobo {$dades['name']} ({$dades['email']}) a aquesta base de dades. Useu --usuari=ID.");
            return self::FAILURE;
        }
        $this->info("Destí: {$user->name} (#{$user->id})" . ($this->option('aplica') ? '' : ' — simulació'));

        $cols = array_flip(array_filter(Schema::getColumnListing('work_logs'), fn ($c) => $c !== 'id'));
        $nous = 0;
        foreach ($dades['fitxatges'] as $f) {
            $existeix = DB::table('work_logs')->where('user_id', $user->id)
                ->where('date', $f['date'])->where('start_time', $f['start_time'])->exists();
            $this->line(($existeix ? '  ja existeix  ' : '  nou          ') . "{$f['date']} {$f['start_time']} → {$f['end_time']}");
            if ($existeix) {
                continue;
            }
            $nous++;
            if ($this->option('aplica')) {
                DB::table('work_logs')->insert(['user_id' => $user->id] + array_intersect_key($f, $cols));
            }
        }
        $this->info("{$nous} nous" . ($this->option('aplica') ? ' importats.' : ' (afegiu --aplica per importar-los).'));

        return self::SUCCESS;
    }
}
