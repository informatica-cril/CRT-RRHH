<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Concerns\RegistraDecisions;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AbsenceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AbsenceController extends Controller
{
    use AuthorizesOwnership;
    use RegistraDecisions;

    // ── Absence Types ──

    public function indexTypes()
    {
        return response()->json(AbsenceType::all());
    }

    public function storeType(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'recoverable' => 'boolean',
            'remunerated' => 'boolean',
            'max_days' => 'nullable|integer',
            'max_per_year' => 'nullable|integer',
            'max_lifetime' => 'nullable|integer',
            'requires_justification' => 'boolean',
            'advance_notice_hours' => 'integer|min:0',
            'extends_with_travel' => 'boolean',
            'extra_days_travel' => 'nullable|integer',
            'category' => 'string',
        ]);

        return response()->json(AbsenceType::create($data), 201);
    }

    public function updateType(Request $request, AbsenceType $absenceType)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'recoverable' => 'sometimes|boolean',
            'remunerated' => 'sometimes|boolean',
            'max_days' => 'sometimes|nullable|integer',
            'max_per_year' => 'sometimes|nullable|integer',
            'max_lifetime' => 'sometimes|nullable|integer',
            'requires_justification' => 'sometimes|boolean',
            'advance_notice_hours' => 'sometimes|integer|min:0',
            'extends_with_travel' => 'sometimes|boolean',
            'extra_days_travel' => 'sometimes|nullable|integer',
            'category' => 'sometimes|string',
        ]);
        $absenceType->update($data);
        return response()->json($absenceType->fresh());
    }

    public function destroyType(AbsenceType $absenceType)
    {
        $absenceType->delete();
        return response()->json(null, 204);
    }

    // ── Absences ──

    public function index()
    {
        // Qui va decidir surt SEMPRE amb la fila: una decisió sense nom no és una decisió.
        return response()->json(
            Absence::with(['user:id,name', 'absenceType:id,name', 'approvedByUser:id,name'])->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'absence_type_id' => 'required|exists:absence_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
            'with_travel' => 'boolean',
        ]);

        // AUTORITZACIÓ: un worker només pot demanar absència per a SI MATEIX;
        // el personal de gestió pot fer-ho per a qualsevol. L'aprovació (update)
        // és sempre de gestió (ruta amb role:...).
        $this->ensureOwnerOrStaff($request, $data['user_id']);

        // Vacances: tope anual de dies NATURALS (30 per defecte), comptant en trams
        // flexibles i amb la regla d'extensió de cap de setmana (vegeu Absence::vacationDays).
        $type = \App\Models\AbsenceType::find($data['absence_type_id']);
        if ($type && $type->category === 'vacation') {
            $cap = $type->max_days ?: 30;
            $year = \Carbon\Carbon::parse($data['start_date'])->year;
            $holidays = \App\Models\Holiday::pluck('date')
                ->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())->all();

            $usedThisYear = Absence::where('user_id', $data['user_id'])
                ->where('absence_type_id', $data['absence_type_id'])
                ->where(function ($q) {
                    $q->whereNull('approved')->orWhere('approved', true); // pendents + aprovades (no rebutjades)
                })
                ->whereYear('start_date', $year)
                ->get()
                ->sum(fn ($a) => Absence::vacationDays($a->start_date, $a->end_date, $holidays));

            $thisRequest = Absence::vacationDays($data['start_date'], $data['end_date'], $holidays);

            if ($usedThisYear + $thisRequest > $cap) {
                return response()->json([
                    'message' => "Superes el màxim de {$cap} dies naturals de vacances per l'any {$year}. "
                        . "Ja tens {$usedThisYear} dies (pendents/aprovats); aquest tram en són {$thisRequest}. "
                        . 'Disponibles: ' . max(0, $cap - $usedThisYear) . '.',
                    'errors' => ['end_date' => ["Màxim {$cap} dies naturals de vacances a l'any"]],
                ], 422);
            }
        }

        return response()->json(Absence::create($data), 201);
    }

    public function bulkStore(Request $request)
    {
        $request->validate(['absences' => 'required|array']);
        $created = [];
        foreach ($request->absences as $absData) {
            $created[] = Absence::create($absData);
        }
        return response()->json($created, 201);
    }

    public function byUser($userId)
    {
        return response()->json(
            Absence::where('user_id', $userId)
                    ->with('absenceType:id,name')
                    ->orderBy('start_date', 'desc')
                    ->get()
        );
    }

    /**
     * Resolució d'una sol·licitud d'absència o permís.
     *
     * L'APROVADOR surt del token, mai del cos: abans s'hi podia posar qualsevol
     * (es va aprovar una sol·licitud signant-hi Direcció, i una altra sense ningú).
     * Es desa la data de la decisió, la denegació exigeix motiu escrit, queda a
     * l'auditoria i el treballador rep l'avís.
     */
    public function update(Request $request, Absence $absence)
    {
        $data = $request->only(['start_date', 'end_date', 'reason', 'with_travel']);

        if ($request->has('approved') && $request->input('approved') !== null) {
            $aprovada = filter_var($request->input('approved'), FILTER_VALIDATE_BOOLEAN);
            $motiu = trim((string) $request->input('denial_reason', ''));

            if (! $aprovada && $motiu === '') {
                return response()->json([
                    'message' => 'Per denegar una sol·licitud cal un motiu escrit: el treballador ha de saber per què se li denega.',
                    'errors' => ['denial_reason' => ['El motiu de la denegació és obligatori.']],
                ], 422);
            }

            $data['approved'] = $aprovada;
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
            $data['denial_reason'] = $aprovada ? null : $motiu;
        }

        $absence->update($data);
        $absence->refresh();

        if (array_key_exists('approved', $data)) {
            $tipus = optional($absence->absenceType)->name ?: 'absència';
            $periode = $absence->start_date->toDateString() . ' → ' . $absence->end_date->toDateString();
            $estat = $data['approved'] ? 'APROVADA' : 'DENEGADA';

            $this->registraDecisio($request, 'ABSENCE_' . ($data['approved'] ? 'APPROVED' : 'DENIED'),
                'absence', $absence->id,
                sprintf('%s (%s) %s per %s%s', $tipus, $periode, $estat,
                    $request->user()->name, $data['approved'] ? '' : '. Motiu: ' . $data['denial_reason']));

            $this->avisaTreballador($absence->user_id, $data['approved']
                ? "La teva sol·licitud de {$tipus} ({$periode}) ha estat APROVADA."
                : "La teva sol·licitud de {$tipus} ({$periode}) ha estat DENEGADA. Motiu: {$data['denial_reason']}");
        }

        return response()->json($absence);
    }

    /**
     * POST /api/v1/absences/{absence}/justificant — part mèdic o justificant.
     *
     * 12 dels 14 tipus d'absència en demanen un i no hi havia cap manera de
     * pujar-lo. Mateix mecanisme de pujada que la comunicació certificada
     * (validació + Storage), però a DISC PRIVAT: un part mèdic és dada de salut
     * i no pot quedar servit per URL pública.
     */
    public function pujaJustificant(Request $request, Absence $absence)
    {
        $this->ensureOwnerOrStaff($request, $absence->user_id);

        $request->validate([
            'justificant' => 'required|file|max:8192|mimes:pdf,jpg,jpeg,png',
        ], [
            'justificant.required' => 'Cal adjuntar el fitxer del justificant.',
            'justificant.mimes' => 'El justificant ha de ser un PDF o una imatge (JPG o PNG).',
            'justificant.max' => 'El justificant no pot passar de 8 MB.',
        ]);

        $f = $request->file('justificant');
        // El fitxer anterior es retira: no s'acumulen versions d'una dada de salut.
        if ($absence->justificant_path) {
            Storage::delete($absence->justificant_path);
        }
        $absence->update([
            'justificant_path' => $f->store('justificants'),
            'justificant_name' => mb_substr($f->getClientOriginalName(), 0, 200),
            'justificant_uploaded_at' => now(),
        ]);

        $this->registraDecisio($request, 'ABSENCE_JUSTIFICANT_UPLOAD', 'absence', $absence->id,
            'Justificant adjuntat: ' . $absence->justificant_name);

        return response()->json([
            'ok' => true,
            'justificant_name' => $absence->justificant_name,
            'justificant_uploaded_at' => $absence->justificant_uploaded_at,
        ], 201);
    }

    /** GET /api/v1/absences/{absence}/justificant — el baixa el titular o gestió. */
    public function baixaJustificant(Request $request, Absence $absence)
    {
        $this->ensureOwnerOrStaff($request, $absence->user_id);

        if (! $absence->justificant_path || ! Storage::exists($absence->justificant_path)) {
            return response()->json(['message' => 'Aquesta sol·licitud no té cap justificant adjuntat.'], 404);
        }

        return Storage::download($absence->justificant_path, $absence->justificant_name ?: 'justificant');
    }

    /**
     * GET /api/v1/absences/used-days/{userId}/{typeId}/{year}
     * Replicate db.js getUsedDaysByType logic
     */
    public function usedDays($userId, $typeId, $year)
    {
        $absences = Absence::where('user_id', $userId)
            ->where('absence_type_id', $typeId)
            ->where(function ($q) {
                $q->whereNull('approved')->orWhere('approved', true);
            })
            ->whereYear('start_date', $year)
            ->get();

        $type = \App\Models\AbsenceType::find($typeId);
        $isVacation = $type && $type->category === 'vacation';
        $holidays = $isVacation
            ? \App\Models\Holiday::pluck('date')->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())->all()
            : [];

        $total = $absences->sum(function ($a) use ($isVacation, $holidays) {
            return $isVacation
                ? Absence::vacationDays($a->start_date, $a->end_date, $holidays)
                : ($a->start_date->diffInDays($a->end_date) + 1);
        });

        return response()->json([
            'used_days' => $total,
            'max_days' => $type->max_days ?? null,
            'remaining' => $type && $type->max_days ? max(0, $type->max_days - $total) : null,
        ]);
    }
}
