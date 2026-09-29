<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplementarySlotDeclaration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Declaració mensual de franges complementàries: la fa cada persona per a si mateixa
 * (mateix criteri que el pacte: l'usuari surt de l'autenticació, mai de la petició).
 * Les franges han de ser adjacents a la jornada contractada del dia.
 */
class ComplementarySlotController extends Controller
{
    private function jornadaPerDia(User $u): array
    {
        $out = [];
        foreach ((array) ($u->workSchedule?->days ?? []) as $d) {
            if (empty($d['active']) || empty($d['start']) || empty($d['end'])) {
                continue;
            }
            $out[(int) $d['day']][] = ['ini' => substr($d['start'], 0, 5), 'fi' => substr($d['end'], 0, 5)];
        }
        return $out;
    }

    private function validaAdjacencia(array $slot, array $jornadaDia): ?string
    {
        $dies = ['', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres'];
        $nom = $dies[$slot['dia']] ?? ('dia ' . $slot['dia']);
        if (!$jornadaDia) {
            return "El $nom no tens jornada contractada: les complementàries han d'anar enganxades a la jornada.";
        }
        foreach ($jornadaDia as $c) {
            if ($slot['inici'] < $c['fi'] && $slot['fi'] > $c['ini']) {
                return "La franja del $nom ({$slot['inici']}-{$slot['fi']}) trepitja la jornada ({$c['ini']}-{$c['fi']}).";
            }
        }
        foreach ($jornadaDia as $c) {
            if ($slot['fi'] === $c['ini'] || $slot['inici'] === $c['fi']) {
                return null;
            }
        }
        $trams = implode(', ', array_map(fn ($c) => $c['ini'] . '-' . $c['fi'], $jornadaDia));
        return "La franja del $nom ({$slot['inici']}-{$slot['fi']}) no toca la jornada ($trams): "
             . 'ha de començar just quan acabes o acabar just quan comences.';
    }

    public function estat(Request $request): JsonResponse
    {
        $u = $request->user()->load('workSchedule');
        $mesSeguent = CarbonImmutable::now()->addMonthNoOverflow()->startOfMonth();

        return response()->json([
            'jornada' => $this->jornadaPerDia($u),
            'mes_seguent' => $mesSeguent->toDateString(),
            'declaracions' => ComplementarySlotDeclaration::where('user_id', $u->id)
                ->where('month', '>=', CarbonImmutable::now()->startOfMonth()->toDateString())
                ->orderBy('month')->get(),
        ]);
    }

    public function declara(Request $request): JsonResponse
    {
        $u = $request->user()->load('workSchedule');
        $dades = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.dia' => ['required', 'integer', 'between:1,5'],
            'slots.*.inici' => ['required', 'date_format:H:i'],
            'slots.*.fi' => ['required', 'date_format:H:i', 'after:slots.*.inici'],
        ]);

        $mes = CarbonImmutable::parse($dades['month'])->startOfMonth();
        if ($mes <= CarbonImmutable::now()->startOfMonth()) {
            return response()->json(['error' => 'Només es pot declarar un mes futur.'], 422);
        }
        $viva = ComplementarySlotDeclaration::where('user_id', $u->id)
            ->where('month', $mes->toDateString())->where('status', '!=', 'withdrawn')->exists();
        if ($viva) {
            return response()->json(['error' => 'Aquest mes ja el tens confirmat. Per retirar-lo cal el flux amb coordinació.'], 409);
        }
        ComplementarySlotDeclaration::where('user_id', $u->id)
            ->where('month', $mes->toDateString())->where('status', 'withdrawn')->delete();

        $jornada = $this->jornadaPerDia($u);
        $hores = 0.0;
        foreach ($dades['slots'] as $s) {
            if ($err = $this->validaAdjacencia($s, $jornada[$s['dia']] ?? [])) {
                return response()->json(['error' => $err], 422);
            }
            $hores += (strtotime($s['fi']) - strtotime($s['inici'])) / 3600;
        }

        $d = ComplementarySlotDeclaration::create([
            'user_id' => $u->id,
            'month' => $mes->toDateString(),
            'slots' => $dades['slots'],
            'hours' => round($hores, 1),
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'confirmed_ip' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'id' => $d->id, 'hours' => $d->hours]);
    }

    public function retirada(Request $request): JsonResponse
    {
        $u = $request->user();
        $dades = $request->validate([
            'month' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:300'],
        ]);
        $n = ComplementarySlotDeclaration::where('user_id', $u->id)
            ->where('month', CarbonImmutable::parse($dades['month'])->startOfMonth()->toDateString())
            ->where('status', 'confirmed')
            ->update([
                'status' => 'withdrawal_requested',
                'withdrawal_reason' => $dades['reason'],
                'withdrawal_requested_at' => now(),
            ]);
        if (!$n) {
            return response()->json(['error' => 'No hi ha cap declaració confirmada per a aquest mes.'], 404);
        }
        return response()->json(['ok' => true, 'status' => 'withdrawal_requested',
            'msg' => 'Petició enviada. Coordinació ha de aprovar-la perquè pot desprogramar pacients.']);
    }

    /** Resolució del flux de retirada. Només el compte de servei (domi, on resol coordinació). */
    public function resolucio(Request $request): JsonResponse
    {
        $dades = $request->validate([
            'user_id' => ['required', 'integer'],
            'month' => ['required', 'date_format:Y-m-d'],
            'approved' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $n = ComplementarySlotDeclaration::where('user_id', $dades['user_id'])
            ->where('month', CarbonImmutable::parse($dades['month'])->startOfMonth()->toDateString())
            ->where('status', 'withdrawal_requested')
            ->update([
                'status' => $dades['approved'] ? 'withdrawn' : 'confirmed',
                'resolved_at' => now(),
                'resolved_note' => $dades['note'] ?? null,
            ]);
        if (!$n) {
            return response()->json(['error' => 'Cap retirada pendent per a aquesta persona i mes.'], 404);
        }
        return response()->json(['ok' => true, 'status' => $dades['approved'] ? 'withdrawn' : 'confirmed']);
    }
}
