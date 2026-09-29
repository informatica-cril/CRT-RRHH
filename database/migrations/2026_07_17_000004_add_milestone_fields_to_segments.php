<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hitos de servei (domi → RRHH):
     *  - kind: 'visita' (arribada→sortida a domicili) | 'desplacament' (entre visites) | null (auto).
     *  - ref: referència OPACA de la visita a domi (sense cap dada de pacient).
     *  - home_verification per segment: verificat | fora_radi | no_disponible.
     * I coherència amb el xifrat AES: les coords dels segments passen a TEXT xifrat
     * (fins ara quedaven en DECIMAL en clar, inconsistent amb work_logs).
     */
    public function up(): void
    {
        Schema::table('work_log_segments', function (Blueprint $table) {
            $table->string('kind', 20)->nullable()->after('segment_number');
            $table->string('ref', 100)->nullable()->after('kind');
            $table->string('home_verification', 20)->nullable()->after('in_schedule');
        });

        if (DB::getDriverName() !== 'sqlite') {   // sqlite no tipa estrictament: no cal MODIFY
            foreach (['start_lat', 'start_lng', 'end_lat', 'end_lng'] as $c) {
                DB::statement("ALTER TABLE `work_log_segments` MODIFY `{$c}` TEXT NULL");
            }
        }
        // Xifrar coords existents
        foreach (DB::table('work_log_segments')->get() as $row) {
            $update = [];
            foreach (['start_lat', 'start_lng', 'end_lat', 'end_lng'] as $c) {
                $val = $row->$c ?? null;
                if ($val !== null && $val !== '' && ! (strlen($val) > 80 && ! is_numeric($val))) {
                    $update[$c] = Crypt::encryptString((string) $val);
                }
            }
            if ($update) {
                DB::table('work_log_segments')->where('id', $row->id)->update($update);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('work_log_segments')->get() as $row) {
            $update = [];
            foreach (['start_lat', 'start_lng', 'end_lat', 'end_lng'] as $c) {
                $val = $row->$c ?? null;
                if ($val !== null && strlen((string) $val) > 80 && ! is_numeric($val)) {
                    try {
                        $update[$c] = Crypt::decryptString($val);
                    } catch (\Throwable $e) {
                    }
                }
            }
            if ($update) {
                DB::table('work_log_segments')->where('id', $row->id)->update($update);
            }
        }
        if (DB::getDriverName() !== 'sqlite') {
            foreach (['start_lat', 'start_lng', 'end_lat', 'end_lng'] as $c) {
                DB::statement("ALTER TABLE `work_log_segments` MODIFY `{$c}` DECIMAL(10,7) NULL");
            }
        }
        Schema::table('work_log_segments', function (Blueprint $table) {
            $table->dropColumn(['kind', 'ref', 'home_verification']);
        });
    }
};
