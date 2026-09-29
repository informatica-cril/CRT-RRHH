<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Estadística empírica del pilot per a la validació del radi (EIPD §6.1.bis).
 * Font única per a la comanda CLI (pilot:radi-report) i el panell visual d'admin.
 */
class PilotRadiStats
{
    public const BASE_M = 50;

    public static function compute(string $from, string $to): array
    {
        $accs = DB::table('location_tracking')
            ->where('event_type', 'like', 'hito_%')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotNull('accuracy')
            ->pluck('accuracy')->map(fn ($a) => (float) $a)->sort()->values();

        $visites = DB::table('work_log_segments')
            ->where('kind', 'visita')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotNull('home_distance_m')
            ->get(['home_distance_m', 'home_radius_m', 'home_verification']);

        $pct = fn ($c, $p) => $c->isEmpty() ? null : $c[(int) floor(($c->count() - 1) * $p)];

        $dists = $visites->pluck('home_distance_m')->map(fn ($d) => (int) $d)->sort()->values();
        $fora = $visites->where('home_verification', 'fora_radi');
        // Zona grisa del sensor: fora_radi amb distància ≤ radi+20% → candidat a fals negatiu
        $grisos = $fora->filter(fn ($v) => $v->home_radius_m && $v->home_distance_m <= $v->home_radius_m * 1.2);

        return [
            'from' => $from,
            'to' => $to,
            'n_hitos' => $accs->count(),
            'n_visites' => $visites->count(),
            'precisio' => $accs->isEmpty() ? null : [
                'p50' => round($pct($accs, 0.50)), 'p90' => round($pct($accs, 0.90)),
                'p95' => round($pct($accs, 0.95)), 'max' => round($accs->last()),
            ],
            'distancies' => $dists->isEmpty() ? null : [
                'p50' => $pct($dists, 0.50), 'p90' => $pct($dists, 0.90),
                'p95' => $pct($dists, 0.95), 'max' => $dists->last(),
            ],
            'fora_radi' => $visites->isEmpty() ? null : [
                'n' => $fora->count(),
                'pct' => round(100 * $fora->count() / max(1, $visites->count()), 1),
                'zona_grisa' => $grisos->count(),
            ],
            'radi_base_m' => self::BASE_M,
            // El radi aconsellat: base + p95 de la precisió REAL observada al pilot
            'radi_recomanat_m' => $accs->isEmpty() ? null : self::BASE_M + (int) ceil($pct($accs, 0.95)),
            // EIPD (cas d'ús 3): radi definitiu = mínim tècnic que cobreixi el p95 de
            // precisions dels MARCATGES CORRECTES. La precisió del marcatge es depura
            // del radi dinàmic aplicat (radi = base + precisió, quan no hi ha radi fix).
            'radi_marcatges_correctes_m' => (function () use ($visites, $pct) {
                $accsOk = $visites->where('home_verification', 'verificat')
                    ->pluck('home_radius_m')->filter()
                    ->map(fn ($r) => max(0, (int) $r - self::BASE_M))
                    ->sort()->values();

                return $accsOk->isEmpty() ? null : self::BASE_M + (int) ceil($pct($accsOk, 0.95));
            })(),
        ];
    }
}
