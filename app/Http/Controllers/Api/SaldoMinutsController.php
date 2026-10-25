<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaldoMinutsApunt;
use App\Models\AuditLog;
use App\Models\SaldoMinutsSetting;
use App\Models\User;
use Carbon\Carbon;
use App\Services\SaldoMinuts;
use Illuminate\Http\Request;

/**
 * Saldo de minuts, la part de la persona: el seu saldo amb els últims moviments i la previsió d'un fitxatge
 * (veure App\Services\SaldoMinuts). Cadascú només veu el seu; la vista de gestió va a part.
 */
class SaldoMinutsController extends Controller
{
    /** GET /api/v1/saldo-minuts */
    public function index(Request $request)
    {
        if (! SaldoMinuts::activa()) {
            return response()->json(['activa' => false]);
        }
        $conf = SaldoMinutsSetting::getSettings();
        $user = $request->user();

        return response()->json([
            'activa' => true,
            'finestra' => (int) $conf->finestra_minuts,
            'tope' => (int) $conf->tope_minuts,
            'saldo' => SaldoMinuts::saldo($user->id),
            'apunts' => SaldoMinutsApunt::where('user_id', $user->id)->orderByDesc('date')->orderByDesc('id')->limit(15)
                ->get(['date', 'tipus', 'entrada_delta', 'sortida_delta', 'delta_aplicat', 'excedent', 'saldo_despres', 'nota']),
        ]);
    }

    /** GET /api/v1/saldo-minuts/previsio?tipus=entrada|sortida */
    public function previsio(Request $request)
    {
        $data = $request->validate(['tipus' => 'required|in:entrada,sortida']);

        return response()->json(SaldoMinuts::previsio($request->user(), $data['tipus']));
    }
    // ── Gestió (RRHH i admin) ─────────────────────────────────────────────────

    /** GET /api/v1/saldo-minuts/config — interruptor, finestra, tope i data d'activació. */
    public function config()
    {
        if (! SaldoMinutsSetting::disponible()) {
            return response()->json(['disponible' => false]);
        }

        return response()->json(['disponible' => true] + SaldoMinutsSetting::getSettings()->toArray());
    }

    /** PUT /api/v1/saldo-minuts/config { enabled, finestra_minuts, tope_minuts, actiu_des? } */
    public function updateConfig(Request $request)
    {
        abort_unless(SaldoMinutsSetting::disponible(), 503, 'El saldo de minuts encara no està instal·lat en aquest servidor.');
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'finestra_minuts' => 'required|integer|min:1|max:15',
            'tope_minuts' => 'required|integer|min:1|max:60',
            'actiu_des' => 'nullable|date_format:Y-m-d',
        ]);
        $conf = SaldoMinutsSetting::getSettings();
        // Al activar-lo per primera vegada compta des d'avui (sense històric); RRHH pot posar una altra data.
        if ($data['enabled'] && empty($data['actiu_des']) && ! $conf->actiu_des) {
            $data['actiu_des'] = Carbon::now('Europe/Madrid')->toDateString();
        }
        if (empty($data['actiu_des'])) unset($data['actiu_des']);
        $conf->update($data);
        SaldoMinuts::oblida();
        $this->audita($request, 'SALDO_MINUTS_CONFIG', 'Saldo de minuts ' . ($conf->enabled ? 'activat' : 'desactivat')
            . " (finestra {$conf->finestra_minuts} min, tope ±{$conf->tope_minuts} min, des del " . ($conf->actiu_des?->toDateString() ?? '—') . ')');

        return response()->json(['disponible' => true] + $conf->fresh()->toArray());
    }

    /** GET /api/v1/saldo-minuts/persones?min=N — el saldo de cada persona que en té algun (ordenat per saldo absolut). */
    public function persones(Request $request)
    {
        if (! SaldoMinuts::activa()) return response()->json(['activa' => false, 'persones' => []]);
        $min = max(0, (int) $request->query('min', 0));
        $ultims = SaldoMinutsApunt::select('user_id', 'saldo_despres', 'date', 'id')->orderBy('date')->orderBy('id')->get()
            ->groupBy('user_id')->map(fn ($l) => $l->last());
        $noms = User::whereIn('id', $ultims->keys())->get(['id', 'name', 'work_type'])->keyBy('id');
        $files = $ultims->map(fn ($a, $uid) => [
            'user_id' => (int) $uid, 'nom' => $noms[$uid]->name ?? "Persona #{$uid}",
            'saldo' => (int) $a->saldo_despres, 'ultim_dia' => $a->date->toDateString(),
        ])->filter(fn ($f) => abs($f['saldo']) >= $min)
            ->sortByDesc(fn ($f) => abs($f['saldo']))->values();
        $conf = SaldoMinutsSetting::getSettings();

        return response()->json(['activa' => true, 'tope' => (int) $conf->tope_minuts, 'persones' => $files]);
    }

    /** GET /api/v1/saldo-minuts/persones/{user} — els últims moviments d'una persona. */
    public function detall(User $user)
    {
        if (! SaldoMinuts::activa()) return response()->json(['activa' => false]);

        return response()->json([
            'activa' => true, 'user_id' => $user->id, 'nom' => $user->name, 'saldo' => SaldoMinuts::saldo($user->id),
            'tope' => (int) SaldoMinutsSetting::getSettings()->tope_minuts,
            'apunts' => SaldoMinutsApunt::where('user_id', $user->id)->orderByDesc('date')->orderByDesc('id')->limit(60)
                ->get(['work_log_id', 'date', 'tipus', 'entrada_delta', 'sortida_delta', 'delta_aplicat', 'excedent', 'saldo_despres', 'nota']),
        ]);
    }

    /**
     * POST /api/v1/saldo-minuts/persones/{user}/ajust { decisio: compensat|ajustat|zero, minuts?, nota }
     * La decisió de RRHH o admin sobre el saldo (p. ex. a final de mes): res no caduca sol.
     *   compensat  el saldo es compensa en temps de treball o descans i queda a zero
     *   zero       es tanca a zero sense compensació (queda escrit per què)
     *   ajustat    es suma o resta un nombre de minuts (el resultat ha de ser dins del tope)
     */
    public function ajust(Request $request, User $user)
    {
        abort_unless(SaldoMinuts::activa(), 422, 'El saldo de minuts no està actiu.');
        $data = $request->validate([
            'decisio' => 'required|in:compensat,ajustat,zero',
            'minuts' => 'required_if:decisio,ajustat|nullable|integer|not_in:0|min:-60|max:60',
            'nota' => 'required|string|min:5|max:400',
        ]);
        $conf = SaldoMinutsSetting::getSettings();
        $saldo = SaldoMinuts::saldo($user->id);
        if ($data['decisio'] === 'ajustat') {
            $delta = (int) $data['minuts'];
            abort_if(abs($saldo + $delta) > $conf->tope_minuts, 422, "El saldo no pot passar de ±{$conf->tope_minuts} min.");
        } else {
            abort_if($saldo === 0, 422, 'Aquesta persona no té saldo.');
            $delta = -$saldo;
        }
        $etiqueta = ['compensat' => 'Compensat', 'zero' => 'Tancat a zero', 'ajustat' => 'Ajust'][$data['decisio']];
        SaldoMinutsApunt::create([
            'user_id' => $user->id, 'tipus' => 'ajust', 'date' => Carbon::now('Europe/Madrid')->toDateString(),
            'delta_aplicat' => $delta, 'saldo_despres' => $saldo + $delta,
            'nota' => mb_substr("{$etiqueta}: {$data['nota']}", 0, 500), 'decidit_per' => $request->user()->id,
        ]);
        SaldoMinuts::recalcula($user->id);
        $this->audita($request, 'SALDO_MINUTS_AJUST', "{$etiqueta} el saldo de minuts de {$user->name} ({$saldo} → " . ($saldo + $delta) . " min). Motiu: {$data['nota']}");

        return response()->json(['ok' => true, 'saldo' => SaldoMinuts::saldo($user->id)]);
    }

    private function audita(Request $request, string $accio, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => $accio, 'entity_type' => 'saldo_minuts',
                'description' => $descripcio,
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
