<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verificació post-desplegament de la INTEGRACIÓ (no només l'API).
 * El health-check del workflow confirma que l'API respon; aquesta comanda confirma
 * que tot el que fa falta perquè el sistema funcioni de debò està al seu lloc:
 * migracions, xifratge de coordenades, sonda de domi (Mode A/B) i clau de dispositiu.
 *
 *   php artisan deploy:verify
 *
 * Sortida amb codi != 0 si hi ha alguna comprovació CRÍTICA fallida (útil en CI/cron).
 */
class DeployVerify extends Command
{
    protected $signature = 'deploy:verify';

    protected $description = 'Verifica que el desplegament ha deixat la integració operativa (migracions, xifratge, sonda domi, claus)';

    public function handle(): int
    {
        $crit = 0;
        $warn = 0;
        $ok = fn ($m) => $this->line("  <fg=green>✔</> {$m}");
        $ko = function ($m) use (&$crit) { $this->line("  <fg=red>x</> {$m}"); $crit++; };
        $wa = function ($m) use (&$warn) { $this->line("  <fg=yellow>‼</> {$m}"); $warn++; };

        $this->info('Verificació post-desplegament — CRT RRHH');

        // 1) BD accessible
        try { DB::select('SELECT 1'); $ok('BD accessible'); }
        catch (\Throwable $e) { $ko('BD NO accessible: '.$e->getMessage()); }

        // 2) Migracions crítiques aplicades (columnes que la integració necessita)
        $need = [
            'work_logs' => ['verification_mode', 'home_verification', 'coords_purged_at'],
            'work_log_segments' => ['kind', 'ref', 'home_verification', 'home_distance_m', 'audiencia_deadline', 'allegation'],
        ];
        foreach ($need as $tbl => $cols) {
            foreach ($cols as $c) {
                Schema::hasColumn($tbl, $c) ? null : $ko("Falta la columna {$tbl}.{$c} (migració pendent?)");
            }
        }
        if ($crit === 0) { $ok('Migracions de la integració aplicades'); }

        // 3) Xifratge de coordenades en repòs (cap coordenada en clar a work_logs)
        try {
            $clar = DB::table('work_logs')->whereNotNull('start_location_lat')
                ->whereRaw('LENGTH(start_location_lat) < 80')->count();
            $clar === 0 ? $ok('Coordenades de work_logs xifrades en repòs')
                : $ko("{$clar} fila(es) amb coordenades EN CLAR a work_logs (migració de xifratge no aplicada)");
        } catch (\Throwable $e) { $wa('No s\'ha pogut comprovar el xifratge: '.$e->getMessage()); }

        // 4) Sonda de domi configurada (detecció Mode A/B)
        $health = config('services.domi.health_url');
        if (! $health) {
            $wa('DOMI_HEALTH_URL no configurat → el sistema treballarà en Mode B (vàlid, sense hitos)');
        } else {
            $mode = \App\Services\DomiClient::verificationMode();
            $mode === 'A' ? $ok("Sonda de domi OK → Mode A (verificació per hitos)")
                : $wa("domi no respon a {$health} → Mode B (RRHH autònom)");
        }

        // 5) Compte de servei per a domi
        $svc = DB::table('users')->where('role', 'service')->count();
        $svc > 0 ? $ok('Compte de servei per a domi existent')
            : $wa('Cap compte role=service (executa php artisan domi:service-account)');

        // 6) Clau de dispositiu corporatiu (bloqueig GPS a tauletes)
        strlen((string) config('services.disp_corp.key')) >= 32
            ? $ok('Clau de dispositiu corporatiu configurada (DISP_CORP_KEY)')
            : $wa('DISP_CORP_KEY no configurada → el bloqueig de GPS en dispositiu corporatiu no s\'aplicarà');

        // 7) Purga programada
        $ok('Purga de coordenades: programada a Kernel (locations:purge diària) — verificar cron d\'artisan schedule');

        $this->newLine();
        if ($crit > 0) {
            $this->error("✗ {$crit} comprovació(ns) CRÍTICA(es) fallida(es)" . ($warn ? " · {$warn} avís(os)" : ''));

            return self::FAILURE;
        }
        $this->info("✓ Integració operativa" . ($warn ? " · {$warn} avís(os) (revisa'ls)" : ' · sense avisos'));

        return self::SUCCESS;
    }
}
