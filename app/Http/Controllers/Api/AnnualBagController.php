<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Http\Request;

/**
 * Bossa anual d'hores (Direcció, 05-08-2026): 1726 h efectives a jornada
 * completa, prorratejades pel percentatge de jornada del quadre horari. El
 * treballador la veu baixar en temps real; RRHH i Coordinació la segueixen per
 * a tothom. Règim per sobre de contracte: parcials amb complementàries pactades
 * sense superar mai la jornada completa; complets amb un màxim de 80 h
 * extraordinàries l'any remunerades a 1,25 (art. 20.1 del conveni XII).
 */
class AnnualBagController extends Controller
{
    // Font única: Support\Jornada. Aquí hi havia un 1,3 que contradeia el 1,25
    // de la pantalla de control d'hores per a la mateixa hora extraordinària.
    private const ANUAL_COMPLETA_H = \App\Support\Jornada::ANUAL_COMPLETA_H;
    private const SETMANA_COMPLETA_H = \App\Support\Jornada::SETMANA_COMPLETA_H;
    private const EXTRA_ANUAL_H = \App\Support\Jornada::EXTRA_ANUAL_H;
    private const EXTRA_FACTOR = \App\Support\Jornada::EXTRA_FACTOR;

    public function show(Request $request)
    {
        // Si no es pot calcular, es diu; el silenci deixava el treballador sense saber
        // que la seva fitxa està incompleta.
        return response()->json($this->bossaDe($request->user()) ?? $this->bossaNoCalculable($request->user()));
    }

    public function index(Request $request)
    {
        /* TOTHOM surt al quadre. Abans, qui no tenia quadre horari (o el tenia mal
           desat) tornava null i desapareixia de la llista sense cap avís: eren
           justament les fitxes que calia arreglar. Ara hi surten marcats. */
        $files = User::where('active', true)
            ->where('role', 'worker')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => $this->bossaDe($u) ?? $this->bossaNoCalculable($u))
            ->values();

        return response()->json([
            'parametres' => \App\Support\Jornada::parametres(),
            'no_calculables' => $files->where('calculable', false)->count(),
            'bosses' => $files,
        ]);
    }

    /** Fila del quadre per a qui NO es pot calcular: visible, marcada i amb el motiu. */
    private function bossaNoCalculable(User $u): array
    {
        $s = $u->workSchedule;
        if (! $s) {
            $motiu = 'Sense quadre horari assignat a la fitxa.';
        } elseif (! is_array($s->days) || $s->days === []) {
            $motiu = 'El quadre horari «' . $s->name . '» està mal desat (sense dies).';
        } else {
            $motiu = 'El quadre horari «' . $s->name . '» no suma cap hora setmanal.';
        }

        return [
            'user_id' => $u->id,
            'nom' => $u->name,
            'calculable' => false,
            'motiu_no_calculable' => $motiu,
            'setmanals_h' => null,
            'pct_jornada' => null,
            'es_complet' => null,
            'bossa_anual_h' => null,
            'treballat_h' => null,
            'pendent_h' => null,
            'desviacio_h' => null,
            'dies_laborables_restants' => null,
            'complementaries_aplicables' => null,
            'extra_tope_h' => null,
            'extra_consumides_h' => null,
            'extra_factor' => null,
        ];
    }

    private function setmanalsDe(User $u): float
    {
        $schedule = $u->workSchedule;
        if (! $schedule || ! is_array($schedule->days)) {
            return 0.0;
        }
        $min = 0;
        foreach ($schedule->days as $d) {
            if (empty($d['active']) || empty($d['start']) || empty($d['end'])) {
                continue;
            }
            [$sh, $sm] = array_map('intval', explode(':', $d['start']));
            [$eh, $em] = array_map('intval', explode(':', $d['end']));
            $min += max(0, ($eh * 60 + $em) - ($sh * 60 + $sm));
        }

        return $min / 60;
    }

    private function bossaDe(User $u): ?array
    {
        $setmanals = $this->setmanalsDe($u);
        if ($setmanals <= 0) {
            return null;
        }
        $pct = min(1.0, $setmanals / self::SETMANA_COMPLETA_H);
        $esComplet = $pct >= 0.999;
        $anual = round(self::ANUAL_COMPLETA_H * $pct, 1);

        $any = now()->year;
        $treballat = (float) WorkLog::where('user_id', $u->id)
            ->whereYear('date', $any)
            ->sum('effective_hours');

        $festius = Holiday::where('year', $any)->pluck('date')->all();
        $laborables = 0;
        $passats = 0;
        $avui = now()->toDateString();
        $d = now()->startOfYear();
        $fi = now()->endOfYear();
        while ($d->lte($fi)) {
            if ($d->isWeekday() && ! in_array($d->toDateString(), $festius, true)) {
                $laborables++;
                if ($d->toDateString() <= $avui) {
                    $passats++;
                }
            }
            $d->addDay();
        }
        $teoric = $laborables > 0 ? $anual * ($passats / $laborables) : 0.0;

        $extraConsumides = $esComplet ? max(0.0, $treballat - $teoric) : 0.0;

        return [
            'user_id' => $u->id,
            'nom' => $u->name,
            'calculable' => true,
            'motiu_no_calculable' => null,
            'setmanals_h' => round($setmanals, 2),
            'pct_jornada' => (int) round($pct * 100),
            'es_complet' => $esComplet,
            'bossa_anual_h' => $anual,
            'treballat_h' => round($treballat, 1),
            'pendent_h' => round($anual - $treballat, 1),
            'desviacio_h' => round($treballat - $teoric, 1),
            'dies_laborables_restants' => max(0, $laborables - $passats),
            'complementaries_aplicables' => ! $esComplet,
            'extra_tope_h' => $esComplet ? self::EXTRA_ANUAL_H : null,
            'extra_consumides_h' => $esComplet ? round($extraConsumides, 1) : null,
            'extra_factor' => $esComplet ? self::EXTRA_FACTOR : null,
        ];
    }
}
