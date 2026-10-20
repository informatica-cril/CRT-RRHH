<?php

namespace App\Services;

use App\Models\AprovacioAutomaticaSetting;
use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avís per a RRHH: persones que porten 2 setmanes seguides fitxant al límit del marge
 * (entren entre 1 i X min tard o surten entre 1 i X min abans). Només informa: no bloqueja res.
 */
final class AvisMarge
{
    public const DIES_RATXA = 14;

    public static function llista(): array
    {
        $marge = AprovacioAutomaticaSetting::getSettings()->marge_minuts;
        $des = now('Europe/Madrid')->subDays(60)->toDateString();
        $descartats = DB::table('avisos_marge_descartats')->get()
            ->map(fn ($d) => $d->user_id . '|' . $d->inici_ratxa)->flip();

        $avisos = [];
        WorkLog::with('user.workSchedule')
            ->where('date', '>=', $des)->whereNotNull('end_time')->where('status', '!=', 'rejected')
            ->orderBy('date', 'desc')->get()
            ->groupBy('user_id')
            ->each(function ($logs) use ($marge, $descartats, &$avisos) {
                $ratxa = [];
                foreach ($logs as $log) {                       // del més recent al més antic
                    $us = self::usMarge($log, $marge);
                    if (! $us) break;                           // un dia puntual talla la ratxa
                    $ratxa[] = $us + ['id' => $log->id, 'dia' => substr($log->getRawOriginal('date'), 0, 10)];
                }
                if (! $ratxa) return;
                $fi = Carbon::parse($ratxa[0]['dia']);
                $inici = Carbon::parse(end($ratxa)['dia']);
                if ($fi->lt(now('Europe/Madrid')->subDays(7)->startOfDay())) return;   // ratxa antiga
                if ($inici->diffInDays($fi) + 1 < self::DIES_RATXA) return;            // encara no fa 2 setmanes
                if ($descartats->has($logs->first()->user_id . '|' . $inici->toDateString())) return;

                $avisos[] = [
                    'user_id' => $logs->first()->user_id,
                    'nom' => $logs->first()->user?->name,
                    'inici' => $inici->toDateString(),
                    'fi' => $fi->toDateString(),
                    'dies' => count($ratxa),
                    'entrades_tard' => collect($ratxa)->where('tard', '>', 0)->count(),
                    'sortides_abans' => collect($ratxa)->where('abans', '>', 0)->count(),
                    'log_ids' => array_column($ratxa, 'id'),
                ];
            });

        return $avisos;
    }

    /** Minuts tard / abans dins del marge, o null si aquell dia va ser puntual (o fora del marge). */
    private static function usMarge(WorkLog $log, int $marge): ?array
    {
        $dia = substr($log->getRawOriginal('start_time'), 0, 10);
        $finestres = AprovacioAutomatica::finestres($log, $dia);
        if (! $finestres) return null;
        $inici = Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid')->seconds(0);
        $fi = Carbon::parse($log->getRawOriginal('end_time'), 'Europe/Madrid')->seconds(0);

        // Tram de l'horari més proper a l'entrada i a la sortida.
        $hIni = collect(array_column($finestres, 0))->sortBy(fn ($h) => abs($inici->diffInMinutes($h, false)))->first();
        $hFi = collect(array_column($finestres, 1))->sortBy(fn ($h) => abs($fi->diffInMinutes($h, false)))->first();
        $tard = (int) $hIni->diffInMinutes($inici, false);   // + = ha entrat tard
        $abans = (int) $fi->diffInMinutes($hFi, false);      // + = ha sortit abans

        $tard = ($tard >= 1 && $tard <= $marge) ? $tard : 0;
        $abans = ($abans >= 1 && $abans <= $marge) ? $abans : 0;

        return ($tard || $abans) ? ['tard' => $tard, 'abans' => $abans] : null;
    }
}