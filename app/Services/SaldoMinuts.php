<?php

namespace App\Services;

use App\Models\SaldoMinutsApunt;
use App\Models\SaldoMinutsSetting;
use App\Models\WorkLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Saldo de minuts (RRHH, 24-10-2026). Els minuts fitxats fins a una finestra (5 per defecte) abans de
 * l'entrada o després de la sortida sumen al saldo de la persona; entrar tard o sortir d'hora dins de
 * la finestra hi resta. El saldo no passa del tope (±15): el que el tope deixa fora segueix el camí de
 * sempre (hores de més sense autoritzar / revisió). Fora de la finestra no hi entra res.
 *
 * - És un LLIBRE A PART: el fitxatge real (entrada, sortida, trams) no es toca mai.
 * - Es calcula sempre al servidor, repassant els fitxatges de la persona en ordre: una correcció de
 *   RRHH, un rebuig o un esborrat deixen el saldo coherent (idempotent).
 * - Els minuts que el saldo absorbeix es resten de les hores de més sense autoritzar del fitxatge
 *   (només si és pendent), recordant el valor d'abans perquè repetir el càlcul no les resti dos cops.
 * - Desactivat per defecte i només compta des de saldo_minuts_settings.actiu_des (sense històric).
 *   Mai pot impedir fitxar: qualsevol error es registra i s'ignora.
 */
final class SaldoMinuts
{
    private static ?bool $activa = null;
    private static bool $dins = false;

    public static function oblida(): void
    {
        self::$activa = null;
        SaldoMinutsSetting::oblida();
    }

    public static function activa(): bool
    {
        if (self::$activa === null) {
            try {
                self::$activa = SaldoMinutsSetting::disponible() && SaldoMinutsSetting::getSettings()->enabled;
            } catch (\Throwable $e) {
                self::$activa = false;
            }
        }

        return self::$activa;
    }

    /** Finestra en minuts si el saldo està actiu; null si no (llavors manen els marges de sempre). */
    public static function finestraActiva(): ?int
    {
        return self::activa() ? SaldoMinutsSetting::getSettings()->finestra_minuts : null;
    }

    /**
     * Pura. Minuts d'un fitxatge que entren al saldo, respecte dels límits de tram de l'horari del dia.
     * $finestres = [[Carbon inici, Carbon fi], ...] (hora de Madrid). Retorna [entrada, sortida] amb
     * signe: + abans de l'hora d'entrada / després de la de sortida, − entrar tard / sortir d'hora.
     * Només compta el límit més proper i només si és dins de ±$finestra; els segons es treuen.
     */
    public static function deltes(array $finestres, Carbon $entrada, Carbon $sortida, int $finestra): array
    {
        $e = $entrada->copy()->seconds(0)->getTimestamp();
        $s = $sortida->copy()->seconds(0)->getTimestamp();
        $entradaDelta = null;
        $sortidaDelta = null;
        foreach ($finestres as [$ini, $fi]) {
            $d = intdiv($ini->copy()->seconds(0)->getTimestamp() - $e, 60);
            if (abs($d) <= $finestra && ($entradaDelta === null || abs($d) < abs($entradaDelta))) $entradaDelta = $d;
            $d = intdiv($s - $fi->copy()->seconds(0)->getTimestamp(), 60);
            if (abs($d) <= $finestra && ($sortidaDelta === null || abs($d) < abs($sortidaDelta))) $sortidaDelta = $d;
        }

        return [$entradaDelta ?? 0, $sortidaDelta ?? 0];
    }

    /** Pura. Aplica el tope al saldo: retorna [aplicat, excedent, saldo nou]. */
    public static function aplicaTope(int $saldo, int $volgut, int $tope): array
    {
        $nou = max(-$tope, min($tope, $saldo + $volgut));
        $aplicat = $nou - $saldo;

        return [$aplicat, $volgut - $aplicat, $nou];
    }

    /** Enganxat a WorkLog::saved / deleted: recalcula el saldo de la persona si el fitxatge ha canviat de veres. */
    public static function alGuardar(WorkLog $log): void
    {
        if (self::$dins || ! self::activa()) return;
        if (! $log->exists) {
            // esborrat
        } elseif (! $log->wasRecentlyCreated && ! $log->wasChanged(['status', 'start_time', 'end_time', 'date', 'extra_hours_authorized', 'extra_hours_unauthorized'])) {
            return;
        }
        try {
            self::recalcula((int) $log->user_id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Torna a calcular el llibre d'una persona (idempotent). */
    public static function recalcula(int $userId): void
    {
        if (self::$dins) return;
        $conf = SaldoMinutsSetting::getSettings();
        $des = $conf->actiu_des ? $conf->actiu_des->toDateString() : null;
        if (! $des) return;
        $finestra = (int) $conf->finestra_minuts;
        $tope = (int) $conf->tope_minuts;

        self::$dins = true;
        try {
            DB::transaction(function () use ($userId, $des, $finestra, $tope) {
                $logs = WorkLog::with('user.workSchedule')->where('user_id', $userId)
                    ->whereNotNull('end_time')->where('date', '>=', $des)
                    ->where('status', '!=', 'rejected')->where('auto_closed', false)->where('fitxatge_automatic', false)
                    ->get();
                $ajusts = SaldoMinutsApunt::where('user_id', $userId)->where('tipus', 'ajust')->where('date', '>=', $des)->get();

                $linies = [];
                foreach ($logs as $l) $linies[] = [substr((string) $l->getRawOriginal('date'), 0, 10), (string) $l->getRawOriginal('start_time'), $l, null];
                foreach ($ajusts as $a) $linies[] = [$a->date->toDateString(), '99', null, $a];
                usort($linies, fn ($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);

                $existents = SaldoMinutsApunt::where('user_id', $userId)->where('tipus', 'fitxatge')->get()->keyBy('work_log_id');
                $vius = [];
                $saldo = 0;
                foreach ($linies as [$dia, , $log, $ajust]) {
                    if ($ajust) {
                        $saldo += (int) $ajust->delta_aplicat;
                        $ajust->update(['saldo_despres' => $saldo]);
                        continue;
                    }
                    $finestres = HorariDia::finestres($log->user, $dia);
                    if (! $finestres) continue;
                    [$ent, $sor] = self::deltes(
                        $finestres,
                        Carbon::parse($log->getRawOriginal('start_time'), 'Europe/Madrid'),
                        Carbon::parse($log->getRawOriginal('end_time'), 'Europe/Madrid'),
                        $finestra,
                    );
                    if ($ent === 0 && $sor === 0) continue;
                    [$aplicat, $excedent, $saldo] = self::aplicaTope($saldo, $ent + $sor, $tope);

                    $ap = $existents->get($log->id);
                    [$abans, $despres] = self::restaExtra($log, $ap, $aplicat);
                    SaldoMinutsApunt::updateOrCreate(['work_log_id' => $log->id], [
                        'user_id' => $userId, 'tipus' => 'fitxatge', 'date' => $dia,
                        'entrada_delta' => $ent, 'sortida_delta' => $sor, 'delta_aplicat' => $aplicat,
                        'excedent' => $excedent, 'saldo_despres' => $saldo,
                        'extra_abans' => $abans, 'extra_despres' => $despres,
                    ]);
                    $vius[] = $log->id;
                }
                // Fitxatges que ja no hi compten (rebutjats, esborrats, sense horari, ja sense minuts): fora del llibre.
                SaldoMinutsApunt::where('user_id', $userId)->where('tipus', 'fitxatge')
                    ->whereNotIn('work_log_id', $vius ?: [0])->delete();
            });
        } finally {
            self::$dins = false;
        }
    }

    /**
     * Resta dels minuts que el saldo absorbeix de les hores de més sense autoritzar del fitxatge (només si és
     * pendent). Retorna [abans, després] per recordar-ho: si el valor actual és el que vam deixar, es parteix
     * de l'«abans» i no es resta dos cops; si l'ha canviat algú (l'app, una correcció), es parteix del nou.
     */
    private static function restaExtra(WorkLog $log, ?SaldoMinutsApunt $ap, int $aplicat): array
    {
        $actual = (float) $log->extra_hours_unauthorized;
        if ($log->status !== 'pending') {
            return [$ap?->extra_abans, $ap?->extra_despres];
        }
        $base = ($ap && $ap->extra_despres !== null && abs($actual - (float) $ap->extra_despres) < 0.0005)
            ? (float) $ap->extra_abans : $actual;
        $nou = max(0.0, round($base - max(0, $aplicat) / 60, 2));
        if (abs($nou - $actual) >= 0.0005) {
            $log->forceFill([
                'extra_hours_unauthorized' => $nou,
                'hour_status' => ($log->hour_status === 'extra' && $nou <= 0) ? 'ok' : ($log->hour_status === 'ok' && $nou > 0 ? 'extra' : $log->hour_status),
            ])->saveQuietly();
        }

        return [$base, $nou];
    }

    /**
     * Què passaria si la persona fitxa ARA ($tipus 'entrada' o 'sortida'): els minuts d'aquest fitxatge respecte de
     * l'horari d'avui, el que n'entraria al saldo (amb el tope) i com quedaria. Serveix per avisar abans de fitxar;
     * el càlcul de veres es fa en tancar la jornada. En una sortida, hi compta també l'entrada de la jornada oberta.
     */
    public static function previsio(\App\Models\User $user, string $tipus, ?Carbon $ara = null): array
    {
        if (! self::activa()) return ['activa' => false];
        $conf = SaldoMinutsSetting::getSettings();
        $ara ??= Carbon::now('Europe/Madrid');
        $finestres = HorariDia::finestres($user, $ara->toDateString());
        $saldo = self::saldo($user->id);
        $entrada = 0;
        $delta = 0;
        if ($finestres) {
            [$e, $s] = self::deltes($finestres, $ara, $ara, (int) $conf->finestra_minuts);
            $delta = $tipus === 'entrada' ? $e : $s;
            if ($tipus === 'sortida') {
                $oberta = WorkLog::where('user_id', $user->id)->whereNull('end_time')->orderByDesc('id')->first();
                if ($oberta) {
                    $dia = substr((string) $oberta->getRawOriginal('start_time'), 0, 10);
                    $fin = HorariDia::finestres($user, $dia);
                    if ($fin) {
                        $iniciat = Carbon::parse($oberta->getRawOriginal('start_time'), 'Europe/Madrid');
                        $entrada = self::deltes($fin, $iniciat, $iniciat, (int) $conf->finestra_minuts)[0];
                    }
                }
            }
        }
        [$aplicat, $excedent, $previst] = self::aplicaTope($saldo, $entrada + $delta, (int) $conf->tope_minuts);

        return [
            'activa' => true, 'tipus' => $tipus, 'delta' => $delta, 'entrada_delta' => $entrada,
            'aplicat' => $aplicat, 'excedent' => $excedent, 'saldo' => $saldo, 'saldo_previst' => $previst,
            'finestra' => (int) $conf->finestra_minuts, 'tope' => (int) $conf->tope_minuts,
        ];
    }

    /** L'apunt d'un fitxatge (per al detall), o null si el saldo no hi té res. */
    public static function apuntDe(int $workLogId): ?array
    {
        if (! self::activa()) return null;
        $a = SaldoMinutsApunt::where('work_log_id', $workLogId)->first();

        return $a ? $a->only(['entrada_delta', 'sortida_delta', 'delta_aplicat', 'excedent', 'saldo_despres']) : null;
    }

    /** Saldo actual d'una persona (0 si no n'hi ha cap línia). */
    public static function saldo(int $userId): int
    {
        if (! SaldoMinutsSetting::disponible()) return 0;
        $darrera = SaldoMinutsApunt::where('user_id', $userId)->orderByDesc('date')->orderByDesc('id')->first();

        return $darrera ? (int) $darrera->saldo_despres : 0;
    }
}
