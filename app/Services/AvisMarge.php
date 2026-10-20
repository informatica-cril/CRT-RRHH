<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avís per a RRHH: persones que porten 3 fitxatges seguits fora de l'horari, de menys (entren tard
 * o surten abans) o de més (entren abans o surten tard), des d'1 minut i sense límit (RRHH,
 * 20-10-2026; abans eren 2 setmanes i només dins del marge). Només informa: no bloqueja res.
 */
final class AvisMarge
{
    /** Fitxatges seguits fora d'horari perquè surti l'avís. */
    public const DIES_RATXA = 3;

    /**
     * Sense $userId, la llista de RRHH (sense els avisos descartats). Amb $userId, només aquella
     * persona i encara que RRHH l'hagi descartat: és el que veu el treballador a la seva app.
     */
    public static function llista(?int $userId = null): array
    {
        $des = now('Europe/Madrid')->subDays(60)->toDateString();
        $descartats = DB::table('avisos_marge_descartats')->get()
            ->map(fn ($d) => $d->user_id . '|' . $d->inici_ratxa)->flip();

        $avisos = [];
        WorkLog::with('user.workSchedule')
            ->where('date', '>=', $des)->whereNotNull('end_time')->where('status', '!=', 'rejected')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->orderBy('date', 'desc')->get()
            ->groupBy('user_id')
            ->each(function ($logs) use ($descartats, $userId, &$avisos) {
                $ratxa = [];
                foreach ($logs as $log) {                       // del més recent al més antic
                    $us = self::desviacio($log);
                    if (! $us) break;                           // un dia puntual talla la ratxa
                    $ratxa[] = $us + ['id' => $log->id, 'dia' => substr($log->getRawOriginal('date'), 0, 10)];
                }
                if (! $ratxa) return;
                $fi = Carbon::parse($ratxa[0]['dia']);
                $inici = Carbon::parse(end($ratxa)['dia']);
                if ($fi->lt(now('Europe/Madrid')->subDays(7)->startOfDay())) return;   // ratxa antiga
                if (count($ratxa) < self::DIES_RATXA) return;                          // encara no són 3 seguits
                if (! $userId && $descartats->has($logs->first()->user_id . '|' . $inici->toDateString())) return;

                $avisos[] = [
                    'user_id' => $logs->first()->user_id,
                    'nom' => $logs->first()->user?->name,
                    'inici' => $inici->toDateString(),
                    'fi' => $fi->toDateString(),
                    'dies' => count($ratxa),
                    'entrades_tard' => collect($ratxa)->where('tard', '>', 0)->count(),
                    'sortides_abans' => collect($ratxa)->where('abans', '>', 0)->count(),
                    'entrades_aviat' => collect($ratxa)->where('aviat', '>', 0)->count(),
                    'sortides_tard' => collect($ratxa)->where('despres', '>', 0)->count(),
                    'log_ids' => array_column($ratxa, 'id'),
                ];
            });

        return $avisos;
    }

    /** Minuts de diferència amb l'horari (tard, abans, aviat, després), o null si va ser puntual. */
    private static function desviacio(WorkLog $log): ?array
    {
        $dia = substr($log->getRawOriginal('start_time'), 0, 10);
        $finestres = AprovacioAutomatica::finestres($log, $dia);
        if (! $finestres) return null;
        $inici = Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid')->seconds(0);
        $fi = Carbon::parse($log->getRawOriginal('end_time'), 'Europe/Madrid')->seconds(0);

        // Tram de l'horari més proper a l'entrada i a la sortida.
        $hIni = collect(array_column($finestres, 0))->sortBy(fn ($h) => abs($inici->diffInMinutes($h, false)))->first();
        $hFi = collect(array_column($finestres, 1))->sortBy(fn ($h) => abs($fi->diffInMinutes($h, false)))->first();
        $entrada = (int) $hIni->diffInMinutes($inici, false);   // + = ha entrat tard, - = aviat
        $sortida = (int) $fi->diffInMinutes($hFi, false);       // + = ha sortit abans, - = tard

        $d = [
            'tard' => max(0, $entrada), 'aviat' => max(0, -$entrada),
            'abans' => max(0, $sortida), 'despres' => max(0, -$sortida),
        ];

        return array_sum($d) > 0 ? $d : null;
    }
}