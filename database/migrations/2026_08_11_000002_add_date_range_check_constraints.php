<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Invariants de rang de dates a prova de bombes (CHECK constraints, MySQL 8+/9).
 * Fa IMPOSSIBLE que un final sigui anterior al seu inici. Verificat: 0 files violadores.
 * Idempotent (només afegeix el que no hi és) per poder reexecutar sense xocar.
 *
 * FORA a propòsit:
 *  - work_logs (end_time < start_time): 628 fichatges de seed incoherents; netejar primer.
 *  - disciplinary_cases: té un FK user_id orfe (apunta a usuaris inexistents) que fa fallar
 *    qualsevol ALTER TABLE (ERROR 1452); cal arreglar la integritat abans de blindar-la.
 */
return new class extends Migration
{
    private array $checks = [
        ['work_log_segments',      'chk_wls_end_ge_start',    'end_time >= start_time'],
        ['authorization_codes',    'chk_authcode_to_ge_from', 'valid_to IS NULL OR valid_to >= valid_from'],
        ['complementary_pacts',    'chk_pact_end_ge_start',   'period_end >= period_start'],
        ['excedencias',            'chk_exced_end_ge_start',  'end_date IS NULL OR end_date >= start_date'],
        ['absences',               'chk_abs_end_ge_start',    'end_date >= start_date'],
        ['rlt_reports',            'chk_rlt_fins_ge_desde',   'periode_fins >= periode_desde'],
        ['location_worker',        'chk_locw_to_ge_from',     'valid_to IS NULL OR valid_to >= valid_from'],
        ['zone_worker',            'chk_zonew_to_ge_from',    'valid_to IS NULL OR valid_to >= valid_from'],
        ['ambulatory_center_user', 'chk_ambu_to_ge_from',     'valid_to IS NULL OR valid_to >= valid_from'],
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
