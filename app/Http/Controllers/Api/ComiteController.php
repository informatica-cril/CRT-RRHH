<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComiteHora;
use App\Models\ComiteMembre;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Crèdit horari de la representació legal (art. 68.e ET).
 *
 * Qui és representant registra ell mateix les hores que dedica a la representació; RRHH no les
 * escriu, només les valida o les rebutja amb motiu. Les hores que passen del crèdit del mes es
 * poden registrar igualment (la llei no prohibeix fer-les, només deixa de garantir-ne la
 * retribució): la pantalla avisa de l'excés i qui valida decideix.
 */
class ComiteController extends Controller
{
    // ── La persona representant ───────────────────────────────────────────

    /** El mandat vigent i el resum del mes. Si no és representant, membre = null i la pantalla ho diu. */
    public function me(Request $request)
    {
        $mes = $this->mes($request);
        $membre = $this->membreDelMes($request->user()->id, $mes);

        return response()->json([
            'membre' => $membre,
            'resum'  => $membre ? $this->resum($request->user()->id, $membre, $mes) : null,
            'tipus'  => ComiteHora::TIPUS,
            'no_consumeixen' => ComiteHora::NO_CONSUMEIXEN,
        ]);
    }

    public function mevesHores(Request $request)
    {
        [$desde, $fins] = $this->limitsMes($this->mes($request));

        return ComiteHora::with('validador:id,name')
            ->where('user_id', $request->user()->id)
            ->whereBetween('data', [$desde, $fins])
            ->orderByDesc('data')->orderByDesc('hora_inici')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'data'        => 'required|date',
            'hora_inici'  => 'required|date_format:H:i',
            'hora_fi'     => 'required|date_format:H:i|after:hora_inici',
            'tipus'       => 'required|in:' . implode(',', array_keys(ComiteHora::TIPUS)),
            'motiu'       => 'required|string|min:5|max:2000',
            'justificant' => 'nullable|file|max:8192|mimes:pdf,jpg,jpeg,png',
        ], [
            'hora_fi.after' => "L'hora de fi ha de ser posterior a la d'inici.",
            'motiu.required' => 'Cal indicar el motiu: és el que justifica les hores.',
            'justificant.mimes' => 'El justificant ha de ser un PDF o una imatge (JPG o PNG).',
            'justificant.max' => 'El justificant no pot passar de 8 MB.',
        ]);

        $user = $request->user();
        $dia = Carbon::parse($data['data'])->toDateString();

        $membre = ComiteMembre::where('user_id', $user->id)->vigentEl($dia)->first();
        if (! $membre) {
            return response()->json(['message' => "El {$this->dmy($dia)} no consteu com a representant: no s'hi poden imputar hores de crèdit."], 422);
        }

        $inici = $data['hora_inici'] . ':00';
        $fi = $data['hora_fi'] . ':00';
        $solapa = ComiteHora::where('user_id', $user->id)->where('data', $dia)
            ->where('estat', '<>', 'rebutjada')
            ->where('hora_inici', '<', $fi)->where('hora_fi', '>', $inici)
            ->first();
        if ($solapa) {
            return response()->json(['message' => 'Aquesta franja es trepitja amb un altre registre del mateix dia ('
                . substr($solapa->hora_inici, 0, 5) . '–' . substr($solapa->hora_fi, 0, 5) . ').'], 422);
        }

        $hora = ComiteHora::create([
            'membre_id'        => $membre->id,
            'user_id'          => $user->id,
            'data'             => $dia,
            'hora_inici'       => $inici,
            'hora_fi'          => $fi,
            'minuts'           => Carbon::parse($inici)->diffInMinutes(Carbon::parse($fi)),
            'tipus'            => $data['tipus'],
            'consumeix_credit' => ! in_array($data['tipus'], ComiteHora::NO_CONSUMEIXEN, true),
            'motiu'            => $data['motiu'],
        ]);

        if ($request->hasFile('justificant')) {
            $this->desaJustificant($hora, $request->file('justificant'));
        }

        $this->audita($request, 'COMITE_HORES_REGISTRE', $hora->id,
            "Registre d'hores de representació del {$this->dmy($dia)} ({$hora->minuts} min)");

        return response()->json($hora->fresh(), 201);
    }

    /** Només el titular i mentre està pendent: un cop validada, la constància no es reescriu. */
    public function destroy(Request $request, ComiteHora $hora)
    {
        if ($hora->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Només qui ha registrat les hores les pot retirar.'], 403);
        }
        if ($hora->estat !== 'pendent') {
            return response()->json(['message' => "Aquest registre ja s'ha resolt i no es pot retirar."], 422);
        }

        if ($hora->justificant_path) {
            Storage::delete($hora->justificant_path);
        }
        $hora->delete();
        $this->audita($request, 'COMITE_HORES_RETIRADA', $hora->id, "Registre d'hores retirat pel titular");

        return response()->json(['ok' => true]);
    }

    public function pujaJustificant(Request $request, ComiteHora $hora)
    {
        if ($hora->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Només qui ha registrat les hores hi pot adjuntar el justificant.'], 403);
        }
        $request->validate([
            'justificant' => 'required|file|max:8192|mimes:pdf,jpg,jpeg,png',
        ], [
            'justificant.mimes' => 'El justificant ha de ser un PDF o una imatge (JPG o PNG).',
            'justificant.max' => 'El justificant no pot passar de 8 MB.',
        ]);

        $this->desaJustificant($hora, $request->file('justificant'));

        return response()->json($hora->fresh(), 201);
    }

    public function baixaJustificant(Request $request, ComiteHora $hora)
    {
        $user = $request->user();
        if ($hora->user_id !== $user->id && ! in_array($user->role, ['admin', 'hr'], true)) {
            return response()->json(['message' => 'No teniu accés a aquest justificant.'], 403);
        }
        if (! $hora->justificant_path || ! Storage::exists($hora->justificant_path)) {
            return response()->json(['message' => 'Aquest registre no té cap justificant adjuntat.'], 404);
        }

        return Storage::download($hora->justificant_path, $hora->justificant_name ?: 'justificant');
    }

    // ── Gestió (admin / hr) ───────────────────────────────────────────────

    public function membres()
    {
        return response()->json([
            'membres' => ComiteMembre::with('user:id,name,dni,email,active')
                ->orderByRaw('data_baixa IS NOT NULL')->orderByDesc('data_alta')->get(),
            'tipus'   => ComiteMembre::TIPUS,
            'carrecs' => ComiteMembre::CARRECS,
            'credit_legal' => $this->creditLegalSuggerit(),
        ]);
    }

    public function storeMembre(Request $request)
    {
        $data = $this->validaMembre($request);

        $solapa = ComiteMembre::where('user_id', $data['user_id'])
            ->where(fn ($q) => $q->whereNull('data_baixa')->orWhere('data_baixa', '>=', $data['data_alta']))
            ->when($data['data_baixa'] ?? null, fn ($q, $baixa) => $q->where('data_alta', '<=', $baixa))
            ->exists();
        if ($solapa) {
            return response()->json(['message' => 'Aquesta persona ja té un mandat que cobreix aquestes dates. Tanqueu-lo (data de baixa) abans de crear-ne un altre.'], 422);
        }

        $membre = ComiteMembre::create($data + ['creat_per' => $request->user()->id]);
        $this->audita($request, 'COMITE_MEMBRE_ALTA', $membre->id, "Alta com a representant de l'usuari {$membre->user_id}");

        return response()->json($membre->load('user:id,name,dni,email,active'), 201);
    }

    public function updateMembre(Request $request, ComiteMembre $membre)
    {
        $data = $this->validaMembre($request, $membre);
        $membre->update($data);
        $this->audita($request, 'COMITE_MEMBRE_CANVI', $membre->id, "Canvi en el mandat de l'usuari {$membre->user_id}");

        return response()->json($membre->load('user:id,name,dni,email,active'));
    }

    public function hores(Request $request)
    {
        $q = ComiteHora::with('user:id,name,dni', 'validador:id,name')
            ->orderByRaw("estat = 'pendent' DESC")->orderByDesc('data')->orderByDesc('hora_inici');

        if ($request->filled('estat')) {
            $q->where('estat', $request->query('estat'));
        }
        if ($request->filled('mes')) {
            [$desde, $fins] = $this->limitsMes($this->mes($request));
            $q->whereBetween('data', [$desde, $fins]);
        }
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->query('user_id'));
        }

        return $q->limit(500)->get();
    }

    public function valida(Request $request, ComiteHora $hora)
    {
        return $this->resol($request, $hora, 'validada', null);
    }

    public function rebutja(Request $request, ComiteHora $hora)
    {
        $data = $request->validate([
            'motiu_rebuig' => 'required|string|min:5|max:2000',
        ], [
            'motiu_rebuig.required' => 'Cal explicar per què es rebutja: la persona representant ho ha de poder rebatre.',
        ]);

        return $this->resol($request, $hora, 'rebutjada', $data['motiu_rebuig']);
    }

    /** Una fila per representant amb mandat dins el mes: crèdit, validades, pendents i restants. */
    public function resumMensual(Request $request)
    {
        $mes = $this->mes($request);
        [$desde, $fins] = $this->limitsMes($mes);

        $membres = ComiteMembre::with('user:id,name,dni')
            ->where('data_alta', '<=', $fins)
            ->where(fn ($q) => $q->whereNull('data_baixa')->orWhere('data_baixa', '>=', $desde))
            ->get();

        return response()->json([
            'mes'   => $mes,
            'files' => $membres->map(fn ($m) => ['membre' => $m] + $this->resum($m->user_id, $m, $mes))->values(),
        ]);
    }

    // ── Internes ──────────────────────────────────────────────────────────

    private function resol(Request $request, ComiteHora $hora, string $estat, ?string $motiu)
    {
        if ($hora->estat !== 'pendent') {
            return response()->json(['message' => 'Aquest registre ja està resolt (' . $hora->estat . ').'], 422);
        }
        if ($hora->user_id === $request->user()->id) {
            return response()->json(['message' => 'No podeu validar les vostres pròpies hores de representació.'], 403);
        }

        $hora->forceFill([
            'estat'        => $estat,
            'validat_per'  => $request->user()->id,
            'validat_ts'   => now(),
            'motiu_rebuig' => $motiu,
        ])->save();

        $this->audita($request, $estat === 'validada' ? 'COMITE_HORES_VALIDADA' : 'COMITE_HORES_REBUTJADA', $hora->id,
            "Hores de representació del {$this->dmy($hora->data->toDateString())} de l'usuari {$hora->user_id}: {$estat}");

        return response()->json($hora->fresh(['user:id,name,dni', 'validador:id,name']));
    }

    private function resum(int $userId, ComiteMembre $membre, string $mes): array
    {
        [$desde, $fins] = $this->limitsMes($mes);
        $hores = ComiteHora::where('user_id', $userId)->whereBetween('data', [$desde, $fins])
            ->get(['estat', 'consumeix_credit', 'minuts']);

        $suma = fn ($estat, $consumeix) => round($hores
            ->where('estat', $estat)->where('consumeix_credit', $consumeix)->sum('minuts') / 60, 2);

        $credit = (float) $membre->credit_hores_mensual;
        $validades = $suma('validada', true);
        $pendents = $suma('pendent', true);

        return [
            'credit'           => $credit,
            'validades'        => $validades,
            'pendents'         => $pendents,
            'convocades'       => $suma('validada', false) + $suma('pendent', false),
            'restants'         => round($credit - $validades, 2),
            // Si es validessin totes les pendents, quant es passaria del crèdit.
            'exces_previst'    => max(0, round($validades + $pendents - $credit, 2)),
        ];
    }

    private function membreDelMes(int $userId, string $mes): ?ComiteMembre
    {
        [$desde, $fins] = $this->limitsMes($mes);

        return ComiteMembre::where('user_id', $userId)
            ->where('data_alta', '<=', $fins)
            ->where(fn ($q) => $q->whereNull('data_baixa')->orWhere('data_baixa', '>=', $desde))
            ->orderByDesc('data_alta')->first();
    }

    private function validaMembre(Request $request, ?ComiteMembre $membre = null): array
    {
        return $request->validate([
            'user_id'              => ($membre ? 'sometimes' : 'required') . '|exists:users,id',
            'tipus_representacio'  => 'required|in:' . implode(',', array_keys(ComiteMembre::TIPUS)),
            'carrec'               => 'required|in:' . implode(',', array_keys(ComiteMembre::CARRECS)),
            'sindicat'             => 'nullable|string|max:80',
            'data_alta'            => 'required|date',
            'data_baixa'           => 'nullable|date|after_or_equal:data_alta',
            'credit_hores_mensual' => 'required|numeric|min:0|max:999',
            'notes'                => 'nullable|string|max:2000',
        ], [
            'data_baixa.after_or_equal' => "La data de baixa no pot ser anterior a la d'alta.",
        ]);
    }

    /** Plantilla laboral activa (els autònoms no compten per a l'escala de l'ET). */
    private function creditLegalSuggerit(): array
    {
        $plantilla = User::where('active', true)
            ->whereNotIn('role', ['service'])
            ->where(fn ($q) => $q->whereNull('relacio')->orWhere('relacio', '<>', 'autonom'))
            ->count();

        return ['plantilla' => $plantilla, 'hores' => ComiteMembre::creditLegal($plantilla)];
    }

    private function desaJustificant(ComiteHora $hora, $fitxer): void
    {
        if ($hora->justificant_path) {
            Storage::delete($hora->justificant_path);
        }
        $hora->forceFill([
            'justificant_path' => $fitxer->store('justificants/comite'),
            'justificant_name' => mb_substr($fitxer->getClientOriginalName(), 0, 200),
        ])->save();
    }

    private function mes(Request $request): string
    {
        $mes = (string) $request->query('mes', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes) ? $mes : now()->format('Y-m');
    }

    private function limitsMes(string $mes): array
    {
        $d = Carbon::createFromFormat('Y-m-d', $mes . '-01');

        return [$d->toDateString(), $d->copy()->endOfMonth()->toDateString()];
    }

    private function dmy(string $data): string
    {
        return Carbon::parse($data)->format('d/m/Y');
    }

    private function audita(Request $request, string $accio, ?int $id, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id'     => $request->user()?->id,
                'action'      => $accio,
                'entity_type' => 'comite',
                'entity_id'   => $id,
                'description' => mb_substr($descripcio, 0, 1000),
                'ip_address'  => $request->ip(),
                'user_agent'  => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
