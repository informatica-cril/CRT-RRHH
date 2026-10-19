<?php

namespace App\Services;

use App\Models\AuthorizationCode;
use App\Models\WorkLog;
use Carbon\Carbon;

/**
 * Hores de més autoritzades (RRHH, 20-10-2026): una autorització és d'una persona, per a uns dies i
 * unes hores, i s'aplica SOLA als seus fitxatges. Abans la persona havia d'escriure un codi al
 * fitxatge i, si no ho feia, les hores autoritzades no comptaven.
 *
 * - En fitxar la sortida, les hores de més que tingui el fitxatge es cobreixen amb l'autorització
 *   vigent d'aquell dia (fins a les hores que li quedin).
 * - En crear una autorització, s'aplica als fitxatges ja tancats d'aquells dies.
 * - La taula i el camp segueixen sent els de sempre (authorization_codes, authorized_extra_code):
 *   l'app mòbil publicada els continua llegint igual.
 * - Un codi antic que ja s'havia gastat (used) no es torna a fer servir.
 */
final class AutoritzacioHores
{
    /** Hores que encara queden d'una autorització (les autoritzades menys les ja aplicades). */
    public static function queden(AuthorizationCode $a): float
    {
        $aplicades = (float) WorkLog::where('authorized_extra_code', $a->code)->sum('extra_hours_authorized');

        return max(0.0, round((float) $a->authorized_hours - $aplicades, 2));
    }

    /** Autoritzacions vigents d'una persona per a un dia, de la més antiga a la més nova. */
    public static function vigents(int $userId, string $dia)
    {
        return AuthorizationCode::where('user_id', $userId)
            ->where('revoked', false)->where('used', false)
            ->where('valid_from', '<=', Carbon::parse($dia)->endOfDay())
            ->where('valid_to', '>=', Carbon::parse($dia)->startOfDay())
            ->orderBy('valid_from')->orderBy('id')->get();
    }

    /** Cobreix les hores de més d'un fitxatge tancat amb l'autorització del dia. Retorna les hores aplicades. */
    public static function aplica(WorkLog $log): float
    {
        $pendents = round((float) $log->extra_hours_unauthorized, 2);
        if (! $log->getRawOriginal('end_time') || $pendents <= 0 || $log->authorized_extra_code) return 0.0;

        $dia = substr((string) $log->getRawOriginal('date'), 0, 10);
        foreach (self::vigents($log->user_id, $dia) as $a) {
            $queden = self::queden($a);
            if ($queden <= 0) { $a->markUsed(); continue; }
            $hores = round(min($pendents, $queden), 2);
            $log->forceFill([
                'authorized_extra_code' => $a->code,
                'extra_hours_authorized' => round((float) $log->extra_hours_authorized + $hores, 2),
                'extra_hours_unauthorized' => round($pendents - $hores, 2),
            ])->save();
            if ($queden - $hores <= 0.001) $a->markUsed();

            return $hores;
        }

        return 0.0;
    }

    /** En crear una autorització: s'aplica als fitxatges ja tancats d'aquells dies. */
    public static function aplicaAlsTancats(AuthorizationCode $a): float
    {
        if (! $a->user_id || $a->revoked) return 0.0;
        $total = 0.0;
        $logs = WorkLog::where('user_id', $a->user_id)->whereNotNull('end_time')
            ->whereNull('authorized_extra_code')->where('extra_hours_unauthorized', '>', 0)
            ->whereDate('date', '>=', Carbon::parse($a->valid_from)->toDateString())
            ->whereDate('date', '<=', Carbon::parse($a->valid_to)->toDateString())
            ->orderBy('date')->get();
        foreach ($logs as $log) {
            $total += self::aplica($log);
            if ($a->fresh()->used) break;
        }

        return round($total, 2);
    }
}
