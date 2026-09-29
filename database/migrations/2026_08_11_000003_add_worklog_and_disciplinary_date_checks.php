<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tanca els constraints de dates que van quedar pendents perquè hi havia dades brutes
 * (ja netejades en aquesta mateixa tanda):
 *  - work_logs: 628 fichatges amb end_time < start_time -> reconstruït end = start + total_hores.
 *  - disciplinary_cases: 1 FK user_id orfe -> posat a NULL (coherent amb ON DELETE SET NULL).
 * Idempotent.
 */
return new class extends Migration
{
    private array $checks = [
        ['work_logs',          'chk_wl_end_ge_start',      'end_time IS NULL OR end_time >= start_time'],
        ['work_logs',          'chk_wl_break_end_ge_start', 'break_end_time IS NULL OR break_start_time IS NULL OR break_end_time >= break_start_time'],
        ['disciplinary_cases', 'chk_disc_coneix_ge_fet',   'data_coneixement IS NULL OR data_fet IS NULL OR data_coneixement >= data_fet'],
        ['disciplinary_cases', 'chk_disc_resolt_ge_obert', 'resolt_ts IS NULL OR obert_ts IS NULL OR resolt_ts >= obert_ts'],
        ['disciplinary_cases', 'chk_disc_termini_ge_audi', 'termini_alegacions IS NULL OR audiencia_rlt_ts IS NULL OR termini_alegacions >= DATE(audiencia_rlt_ts)'],
    ];

    private function exists(string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('CONSTRAINT_SCHEMA = DATABASE()')
            ->where('CONSTRAINT_NAME', $name)->exists();
    }

    public function up(): void
    {
        // Els CHECK amb nom i information_schema són de MySQL: a sqlite (tests) no s'hi entra.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach ($this->checks as [$table, $name, $rule]) {
            if (! $this->exists($name)) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$rule})");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        foreach (array_reverse($this->checks) as [$table, $name, $rule]) {
            if ($this->exists($name)) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
            }
        }
    }
};
