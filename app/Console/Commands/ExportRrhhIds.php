<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Genera el SQL que propaga l'ID CORPORATIU (users.id de RRHH) cap a domi (admin.rrhh_user_id),
 * a partir del mapeig users.domi_username (sembrat per DNI amb domi:map-users).
 *
 * Repetible: executar després de cada tanda d'altes. El fitxer resultant l'aplica informàtica:
 *   php artisan domi:export-rrhh-ids /tmp/rrhh_ids.sql
 *   mysql domi_crt < /tmp/rrhh_ids.sql
 */
class ExportRrhhIds extends Command
{
    protected $signature = 'domi:export-rrhh-ids {out : Fitxer SQL de sortida}';
    protected $description = 'Exporta UPDATEs idempotents que omplen admin.rrhh_user_id a domi des del mapeig domi_username.';

    public function handle(): int
    {
        $users = User::whereNotNull('domi_username')->orderBy('id')->get(['id', 'name', 'domi_username']);
        if ($users->isEmpty()) {
            $this->error('Cap usuari amb domi_username: executeu primer domi:map-users.');
            return self::FAILURE;
        }
        $sql = "-- rrhh_ids.sql — generat per `php artisan domi:export-rrhh-ids` el " . now()->toDateTimeString() . "\n"
             . "-- Propaga l'ID corporatiu (users.id de RRHH) a domi. Idempotent i segur de re-executar.\n";
        foreach ($users as $u) {
            $uname = addslashes($u->domi_username);
            $sql .= "UPDATE admin SET rrhh_user_id = {$u->id} WHERE UserName = '{$uname}'; -- {$u->name}\n";
        }
        file_put_contents($this->argument('out'), $sql);
        $this->info("Escrits {$users->count()} UPDATEs a {$this->argument('out')}. Aplicar amb: mysql domi_crt < {$this->argument('out')}");

        return self::SUCCESS;
    }
}
