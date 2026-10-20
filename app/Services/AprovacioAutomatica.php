<?php

namespace App\Services;

use App\Models\AprovacioAutomaticaSetting;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aprovació automàtica dels fitxatges correctes (RRHH, 16-10-2026). Aprovar cada dia tots els
 * fitxatges d'un en un no era assumible: un fitxatge que ho té tot bé s'aprova sol en fitxar la
 * sortida i a la llista només queden les excepcions. S'aprova si:
 *   - és pendent, té entrada i sortida i no l'ha tancat el sistema;
 *   - l'entrada i la sortida cauen a ± el marge (10 min per defecte) d'un inici i d'un final
 *     de tram del seu horari d'aquell dia;
 *   - entrada, sortida i tots els trams són dins de zona;
 *   - no té hores extra (ni codi ni més minuts de més que el marge) ni és un dia fora de quadrant.
 *     Els minuts de més dins del marge no bloquegen (RRHH, 20-10-2026): queden apuntats sense
 *     autoritzar (no compten) i es poden acceptar igualment si cal;
 *   - no té cap incidència: alertes obertes, canvis fets a mà o sense GPS, trams rebutjats,
 *     jornada amb fites de domi o pausa obligatòria sense fer.
 * Si en falla una, es queda pendent com fins ara. Els trams curts de fora d'horari que cauen dins
 * del marge s'aproven amb el fitxatge.
 */
final class AprovacioAutomatica
{
    /** Accions de l'historial que són el flux normal d'una jornada (la resta és una incidència). */
    private const ACCIONS_NORMALS = ['created', 'segmented', 'break_started', 'break_completed'];

    /** Motiu pel qual el fitxatge NO es pot aprovar sol, o null si es pot. */
    public static function motiu(WorkLog $log, int $marge): ?string
    {
        if ($log->status !== 'pending') return 'no és pendent';
        $iniciRaw = $log->getRawOriginal('start_time');
        $fiRaw = $log->getRawOriginal('end_time');
        if (! $iniciRaw || ! $fiRaw) return 'sense entrada o sortida';
        if ($log->auto_closed) return 'tancat pel sistema';
        if ($log->out_of_schedule) return 'dia fora de quadrant';
        $extraForaMarge = (float) $log->extra_hours_unauthorized * 60 > $marge + 0.5;
        if ($log->authorized_extra_code || (float) $log->extra_hours_authorized > 0 || $extraForaMarge) {
            return 'hores extra';
        }
        if ($log->hour_status === 'out_of_area' || ($log->hour_status === 'extra' && $extraForaMarge) || (float) $log->hours_out_of_area > 0) {
            return 'hores extra o fora de zona';
        }
        if ($log->start_location_match !== true && $log->start_location_match !== 1) return 'entrada fora de zona';
        if ($log->end_location_match === false || $log->end_location_match === 0) return 'sortida fora de zona';
        if ($log->break_required && $log->break_status !== 'completed') return 'pausa obligatòria sense fer';

        if ($log->alerts()->whereNull('dismissed_at')->exists()) return 'té alertes';
        if ($log->modifications()->whereNotIn('action', self::ACCIONS_NORMALS)->exists()) return 'modificat o registrat a mà';

        $trams = $log->segments()->get();
        foreach ($trams as $t) {
            if ($t->kind !== null) return 'jornada amb fites de domi';
            if (! $t->in_zone) return 'tram fora de zona';
            if ($t->status === 'rejected') return 'tram rebutjat';
            if ($t->home_verification === 'fora_radi') return 'tram fora de radi';
            if ($t->status === 'pending' && ($t->in_schedule || $t->duration_minutes > $marge)) return "temps fora d'horari";
        }

        $finestres = self::finestres($log, substr($iniciRaw, 0, 10));
        if (! $finestres) return "sense horari aquell dia";
        $inici = Carbon::parse($iniciRaw, 'Europe/Madrid')->seconds(0);
        $fi = Carbon::parse($fiRaw, 'Europe/Madrid')->seconds(0);
        $aprop = fn (Carbon $t, array $hores) => collect($hores)->contains(fn ($h) => abs($t->diffInMinutes($h, false)) <= $marge);
        if (! $aprop($inici, array_column($finestres, 0))) return "l'entrada no quadra amb l'horari";
        if (! $aprop($fi, array_column($finestres, 1))) return "la sortida no quadra amb l'horari";

        return null;
    }

    /** En fitxar la sortida: l'aprova si està activat i ho té tot bé. */
    public static function intenta(WorkLog $log): bool
    {
        $conf = AprovacioAutomaticaSetting::getSettings();
        if (! $conf->enabled) return false;
        $log->refresh();
        if (self::motiu($log, $conf->marge_minuts) !== null) return false;
        self::aprova($log, 'en fitxar la sortida');

        return true;
    }

    public static function aprova(WorkLog $log, string $context): void
    {
        DB::transaction(function () use ($log, $context) {
            $log->forceFill(['status' => 'approved', 'auto_approved_at' => now()])->save();
            $trams = $log->aprovaTramsPendents(null);
            DB::table('work_log_modifications')->insert([
                // user_id és obligatori: hi va el titular; el comentari i auto_approved_at diuen que ho ha fet l'app.
                'work_log_id' => $log->id, 'user_id' => $log->user_id, 'action' => 'approved',
                'old_values' => json_encode(['status' => 'pending']),
                'new_values' => json_encode(['status' => 'approved', 'automatic' => true] + ($trams ? ['trams_aprovats' => $trams] : [])),
                'comment' => "Aprovat automàticament ({$context}): horari, zona i hores correctes",
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    /** Trams de l'horari d'aquell dia [inici, fi] en hora de Madrid sense zona (quadrant o plantilla). */
    public static function finestres(WorkLog $log, string $dia): array
    {
        return HorariDia::finestres($log->user, $dia);
    }
}
