<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Xifra AES les coordenades CRUES del treballador en repòs (work_logs, location_tracking).
     * Els resultats de validació (location_match, distàncies) es mantenen EN CLAR, així el
     * geovallat i les consultes no es trenquen; el model desxifra de forma transparent.
     * NO es xifren work_locations (coordenades de centres, no dades personals).
     */
    private array $cols = [
        'work_logs' => ['start_location_lat', 'start_location_lng', 'end_location_lat', 'end_location_lng'],
        'location_tracking' => ['latitude', 'longitude'],
    ];

    public function up(): void
    {
        foreach ($this->cols as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            // 1) Ampliar a TEXT per encabir el text xifrat
            // sqlite (només tests): no té MODIFY, però tampoc tipa estrictament les
            // columnes — un DECIMAL hi encabeix text sense queixar-se. No cal tocar res.
            if (DB::getDriverName() !== 'sqlite') {
                foreach ($columns as $c) {
                    if (Schema::hasColumn($table, $c)) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$c}` TEXT NULL");
                    }
                }
            }
            // 2) Xifrar els valors existents (els que no ho estiguin ja)
            foreach (DB::table($table)->get() as $row) {
                $update = [];
                foreach ($columns as $c) {
                    $val = $row->$c ?? null;
                    if ($val !== null && $val !== '' && ! $this->looksEncrypted($val)) {
                        $update[$c] = Crypt::encryptString((string) $val);
                    }
                }
                if ($update) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->cols as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->get() as $row) {
                $update = [];
                foreach ($columns as $c) {
                    $val = $row->$c ?? null;
                    if ($val !== null && $val !== '' && $this->looksEncrypted($val)) {
                        try {
                            $update[$c] = Crypt::decryptString($val);
                        } catch (\Throwable $e) {
                        }
                    }
                }
                if ($update) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            }
            if (DB::getDriverName() !== 'sqlite') {   // vegeu up(): a sqlite no cal
                foreach ($columns as $c) {
                    if (Schema::hasColumn($table, $c)) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$c}` DECIMAL(10,7) NULL");
                    }
                }
            }
        }
    }

    private function looksEncrypted($val): bool
    {
        // Els valors de Laravel Crypt són base64 llargs; una coordenada crua és curta i numèrica.
        return is_string($val) && strlen($val) > 80 && ! is_numeric($val);
    }
};
