<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avís per a RRHH: persones que porten 3 fitxatges seguits fora de l'horari, de menys (entren tard
 * o surten abans) o de més (entren abans o surten tard), passant-se del marge de la bolsa de minuts
 * (5 min). Abans (20-10-2026) comptava des d'1 minut; ara el que cobreix la bolsa no avisa.
 * Només informa: no bloqueja res.
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
                $marcats = [];                                  // tots els de la ratxa, també els ja aprovats
                foreach ($logs as $log) {                       // del més recent al més antic
                    $us = self::desviacio($log);
                    if (! $us) break;                           // un dia puntual talla la ratxa
                    $marcats[] = $log->id;
                    // Un fitxatge ja aprovat està revisat: no suma a la ratxa (RRHH, 11-2026), però sí que queda
                    // marcat perquè es vegi a la llista de fitxatges de la persona.
                    if ($log->status === 'approved') continue;
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
                    'marcats_ids' => $marcats,
                ];
            });

        return $avisos;
    }

    /**
     * IDs dels fitxatges que formen part d'una ratxa de 3 o més dies seguits fora d'horari (últims 60 dies), també
     * els ja aprovats. Serveix només per MARCAR-los a la llista de fitxatges: no té res a veure amb l'avís (que
     * només compta els pendents) i es veu encara que la persona ja no tingui cap avís.
     */
    public static function marques(): array
    {
        return array_keys(self::marquesAmbRang());
    }

    /** Com marques(), però amb el rang de cada ratxa: id => [data inicial, data final] (AAAA-MM-DD). */
    public static function marquesAmbRang(): array
    {
        $des = now('Europe/Madrid')->subDays(60)->toDateString();
        $ids = [];
        WorkLog::with('user.workSchedule')
            ->where('date', '>=', $des)->whereNotNull('end_time')->where('status', '!=', 'rejected')
            ->orderBy('date')->orderBy('start_time')->get()
            ->groupBy('user_id')
            ->each(function ($logs) use (&$ids) {
                $ratxa = [];
                $tanca = function () use (&$ratxa, &$ids) {
                    if (count($ratxa) >= self::DIES_RATXA) {
                        $rang = [$ratxa[0][1], end($ratxa)[1]];
                        foreach ($ratxa as [$id]) $ids[$id] = $rang;
                    }
                    $ratxa = [];
                };
                foreach ($logs as $log) {
                    if (self::desviacio($log)) $ratxa[] = [$log->id, substr((string) $log->getRawOriginal('date'), 0, 10)]; else $tanca();
                }
                $tanca();
            });

        return $ids;
    }

    /**
     * Els dies de la ratxa (3 o més fitxatges seguits fora d'horari) que conté aquest fitxatge, en ordre, amb el
     * detall de cada dia. Buit si el fitxatge no forma part de cap ratxa. Només per consultar (RRHH/Superior).
     */
    public static function diesDeRatxa(WorkLog $log): array
    {
        $des = now('Europe/Madrid')->subDays(60)->toDateString();
        $logs = WorkLog::with('user.workSchedule')->where('user_id', $log->user_id)
            ->where('date', '>=', $des)->whereNotNull('end_time')->where('status', '!=', 'rejected')
            ->orderBy('date')->orderBy('start_time')->get();

        $ratxa = [];
        $trobada = null;
        foreach ($logs as $l) {
            $d = self::desviacio($l);
            if ($d) {
                $ratxa[] = [$l, $d];
                continue;
            }
            if (in_array($log->id, array_map(fn ($r) => $r[0]->id, $ratxa), true)) { $trobada = $ratxa; break; }
            $ratxa = [];
        }
        $trobada ??= in_array($log->id, array_map(fn ($r) => $r[0]->id, $ratxa), true) ? $ratxa : [];
        if (count($trobada) < self::DIES_RATXA) return [];

        return array_map(fn ($r) => [
            'id' => $r[0]->id,
            'dia' => substr((string) $r[0]->getRawOriginal('date'), 0, 10),
            'entrada' => substr((string) $r[0]->getRawOriginal('start_time'), 11, 5),
            'sortida' => substr((string) $r[0]->getRawOriginal('end_time'), 11, 5),
            'estat' => $r[0]->status,
            'tard' => $r[1]['tard'], 'aviat' => $r[1]['aviat'], 'abans' => $r[1]['abans'], 'despres' => $r[1]['despres'],
        ], $trobada);
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

        // Les diferències que ja cobreix la bolsa de minuts (fins a la seva finestra, 5 min) no compten: RRHH
        // no les ha de mirar. Només surt qui es passa d'aquest marge (RRHH, 11-2026: amb «des d'1 minut» el
        // número era de 56 persones i no volia dir res).
        $marge = SaldoMinuts::finestraActiva() ?? WorkLogService::MARGE_HORARI_MIN;
        $d = [
            'tard' => max(0, $entrada), 'aviat' => max(0, -$entrada),
            'abans' => max(0, $sortida), 'despres' => max(0, -$sortida),
        ];
        $d = array_map(fn ($m) => $m > $marge ? $m : 0, $d);

        return array_sum($d) > 0 ? $d : null;
    }
}