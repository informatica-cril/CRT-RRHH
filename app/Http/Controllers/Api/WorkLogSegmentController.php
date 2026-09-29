<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLog;
use App\Models\WorkLogSegment;
use App\Models\WorkLogModification;
use Illuminate\Http\Request;

class WorkLogSegmentController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesOwnership;

    /**
     * GET /api/v1/work-logs/{workLog}/segments
     * Lista los tramos segmentados de un fichaje.
     */
    public function index(Request $request, WorkLog $workLog)
    {
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        return response()->json(
            $workLog->segments()->orderBy('segment_number')->get()
        );
    }

    /**
     * POST /api/v1/work-logs/{workLog}/segments
     * Crea o reemplaza los tramos de un fichaje (segmentación manual o automática).
     */
    public function store(Request $request, WorkLog $workLog)
    {
        $request->validate([
            'segments' => 'required|array',
            'segments.*.start_time' => 'required|date',
            'segments.*.end_time' => 'required|date|after:segments.*.start_time',
            'segments.*.start_lat' => 'nullable|numeric',
            'segments.*.start_lng' => 'nullable|numeric',
            'segments.*.end_lat' => 'nullable|numeric',
            'segments.*.end_lng' => 'nullable|numeric',
            'segments.*.in_zone' => 'boolean',
            'segments.*.in_schedule' => 'boolean',
            'segments.*.duration_minutes' => 'integer|min:0',
        ]);

        // Eliminar tramos existentes si se está re-segmentando
        $workLog->segments()->delete();

        $created = [];
        foreach ($request->segments as $i => $segData) {
            $segData['work_log_id'] = $workLog->id;
            $segData['segment_number'] = $i + 1;
            $segData['status'] = $segData['status'] ?? 'pending';
            $created[] = WorkLogSegment::create($segData);
        }

        $workLog->update(['segmented' => true]);

        // Recalcular tiempo efectivo (solo tramos aprobados)
        $workLog->refresh()->recalcularEfectivo();

        // Registrar trazabilidad
        WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'segmented',
            'new_values' => ['segment_count' => count($created)],
            'comment' => 'Fichaje segmentado en ' . count($created) . ' tramos',
        ]);

        return response()->json($created, 201);
    }

    /**
     * PUT /api/v1/work-logs/{workLog}/segments/{segment}
     * Modifica un tramo (el trabajador puede modificar tiempos o añadir código de autorización).
     */
    public function update(Request $request, WorkLog $workLog, WorkLogSegment $segment)
    {
        $data = $request->validate([
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'start_lat' => 'nullable|numeric',
            'start_lng' => 'nullable|numeric',
            'end_lat' => 'nullable|numeric',
            'end_lng' => 'nullable|numeric',
            'in_zone' => 'sometimes|boolean',
            'in_schedule' => 'sometimes|boolean',
            'duration_minutes' => 'sometimes|integer|min:0',
            'authorization_code_id' => 'nullable|integer',
            'status' => 'sometimes|in:pending,approved,rejected,modified',
        ]);

        $oldValues = $segment->only(array_keys($data));

        $data['modified_by'] = $request->user()->id;
        $data['modified_at'] = now();
        $data['status'] = $data['status'] ?? 'modified';

        $segment->update($data);

        // Recalcular tiempo efectivo (el cambio de status/duración afecta al total aprobado)
        $workLog->recalcularEfectivo();

        WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'modified',
            'old_values' => $oldValues,
            'new_values' => $data,
            'comment' => 'Tramo ' . $segment->segment_number . ' modificado',
        ]);

        return response()->json($segment->fresh());
    }

    /**
     * POST /api/v1/work-logs/{workLog}/segments/{segment}/reject
     * Rechaza un tramo con motivo (coordinator/admin).
     */
    /** Termini d'al·legacions de l'audiència prèvia (dies naturals). El rellotge el
     *  porta l'EMPRESA: passat el termini sense al·legació, coordinació pot resoldre
     *  documentant que l'audiència es va oferir. */
    const AUDIENCIA_DIES = 7;

    /**
     * Obre l'audiència prèvia sobre un tram: fixa termini i notifica el treballador
     * (alerta a la seva app). Idempotent.
     */
    public static function obrirAudiencia(WorkLogSegment $segment, ?string $allegacioInicial = null): void
    {
        if ($segment->audiencia_requested_at) {
            return;
        }
        $deadline = now()->addDays(self::AUDIENCIA_DIES)->toDateString();
        $upd = ['audiencia_requested_at' => now(), 'audiencia_deadline' => $deadline];
        // La justificació del registre manual, si n'hi ha, JA és l'explicació del
        // treballador: entra com a al·legació inicial (pot ampliar-la). MAI es pisa
        // una al·legació ja existent.
        if ($allegacioInicial && ! $segment->allegation) {
            $upd['allegation'] = $allegacioInicial;
            $upd['allegation_at'] = now();
        }
        $segment->update($upd);
        try {
            \App\Models\WorkLogAlert::create([
                'work_log_id' => $segment->work_log_id,
                'user_id' => $segment->workLog->user_id,
                'type' => 'audiencia',
                'message' => "Tens un marcatge pendent de revisió (tram {$segment->segment_number}, "
                    . ($segment->home_verification ?: 'fora de zona') . "). Pots presentar la teva explicació "
                    . "fins al {$deadline} des del detall del fichatge. Cap decisió es prendrà sense escoltar-te.",
                'scheduled_at' => now(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // la notificació no pot trencar el flux
        }
    }

    public function reject(Request $request, WorkLog $workLog, WorkLogSegment $segment)
    {
        // En rebutjar un tram DESPRÉS de l'audiència prèvia s'ha de QUALIFICAR el fet segons
        // el conveni (art. 64): la tipificació és obligatòria i alimenta l'expedient.
        $request->validate([
            'rejection_reason' => 'required|string',
            'fault_type_clau'  => 'required|string|exists:disciplinary_fault_types,clau',
        ]);

        // ── AUDIÈNCIA PRÈVIA OBLIGATÒRIA (EIPD C6) — garantia TÈCNICA, no organitzativa ──
        // Un tram només es pot rebutjar si (a) el treballador ha estat escoltat
        // (al·legació presentada), o (b) l'audiència es va oferir i el termini ha vençut
        // sense resposta. Si encara no hi ha audiència, aquest intent LA OBRE i no rebutja.
        if (! $segment->allegation) {
            if (! $segment->audiencia_requested_at) {
                self::obrirAudiencia($segment);

                return response()->json([
                    'message' => 'Audiència prèvia OBERTA i treballador notificat. El tram no es pot '
                        . 'rebutjar fins que presenti al·legació o venci el termini ('
                        . $segment->fresh()->audiencia_deadline->format('d/m/Y') . ').',
                    'audiencia_oberta' => true,
                ], 422);
            }
            if (now()->toDateString() <= $segment->audiencia_deadline->toDateString()) {
                return response()->json([
                    'message' => 'En termini d\'al·legacions fins al '
                        . $segment->audiencia_deadline->format('d/m/Y')
                        . '. Espera l\'al·legació del treballador o el venciment del termini.',
                ], 422);
            }
            // Termini vençut sense al·legació: es pot resoldre documentant-ho.
        }

        $oldStatus = $segment->status;
        $segment->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'modified_by' => $request->user()->id,
            'modified_at' => now(),
        ]);

        // Recalcular tiempo efectivo: el tramo rechazado deja de contar
        $workLog->recalcularEfectivo();

        WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'rejected',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'rejected', 'reason' => $request->rejection_reason],
            'comment' => 'Tramo ' . $segment->segment_number . ' rechazado: ' . $request->rejection_reason
                . ($segment->allegation
                    ? ' [al·legació del treballador valorada]'
                    : ' [audiència oferta el ' . optional($segment->audiencia_requested_at)->format('d/m/Y') . '; termini vençut sense al·legacions]'),
        ]);

        // Tancar l'alerta d'audiència si seguia oberta
        \App\Models\WorkLogAlert::where('work_log_id', $workLog->id)
            ->where('type', 'audiencia')->whereNull('dismissed_at')
            ->update(['dismissed_at' => now()]);

        // EVIDÈNCIA per a l'expedient: el rebuig qualificat (amb audiència prèvia complerta) entra
        // a la safata de RRHH com a CONDICIONAL. NO obre cap expedient: el motor l'agrupa i suggereix,
        // i RRHH decideix instruir amb la seva pròpia motivació (proporcionalitat, patró de la casa).
        $tram = $segment->kind ?: 'tram';
        $motivVerif = $segment->home_verification && $segment->home_verification !== 'verificat'
            ? " · verificació de domicili: {$segment->home_verification}"
            : (! $segment->in_zone ? ' · fora de zona' : '');
        $element = \App\Models\DisciplinaryElement::create([
            'professional'     => optional($workLog->user)->name ?? ('user#' . $workLog->user_id),
            'user_id'          => $workLog->user_id,
            'tipus_falta'      => $request->fault_type_clau,
            'data_fet'         => optional($segment->start_time)->toDateString() ?? $workLog->date,
            'data_coneixement' => now()->toDateString(),
            'font'             => 'marcatge:tram_rebutjat',
            'valor'            => trim("{$segment->duration_minutes} min · {$tram}{$motivVerif}"),
            'descripcio'       => mb_substr("Tram {$segment->segment_number} del fichatge del "
                . optional($segment->start_time)->format('d/m/Y') . " rebutjat: {$request->rejection_reason}. "
                . ($segment->allegation
                    ? 'Audiència prèvia: al·legació del treballador valorada.'
                    : 'Audiència prèvia oferta; termini vençut sense al·legacions.'), 0, 255),
            'imputable'        => 'condicional',
            'estat'            => 'valorat',
            'created_by'       => $request->user()->id,
        ]);

        return response()->json(['segment' => $segment->fresh(), 'evidencia_id' => $element->id]);
    }

    /**
     * POST /api/v1/work-logs/{workLog}/segments/{segment}/allegation
     * AL·LEGACIÓ del treballador (audiència prèvia): només el titular del fichatge,
     * només sobre trams pendents. Queda traçada i visible per a coordinació.
     */
    public function allegation(Request $request, WorkLog $workLog, WorkLogSegment $segment)
    {
        abort_if($request->user()->id !== $workLog->user_id, 403, 'Només el titular del fichatge pot al·legar');
        abort_if($segment->work_log_id !== $workLog->id, 404);
        abort_if($segment->status !== 'pending', 422, 'El tram ja està resolt');

        $data = $request->validate(['allegation' => 'required|string|min:20|max:2000']);

        if (! $segment->audiencia_requested_at) {
            self::obrirAudiencia($segment);
            $segment->refresh();
        }
        $segment->update(['allegation' => $data['allegation'], 'allegation_at' => now()]);

        WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'allegacio',
            'new_values' => ['segment' => $segment->segment_number, 'allegation' => $data['allegation']],
            'comment' => 'Al·legació presentada sobre el tram ' . $segment->segment_number,
        ]);

        \App\Models\WorkLogAlert::where('work_log_id', $workLog->id)
            ->where('type', 'audiencia')->whereNull('dismissed_at')
            ->update(['dismissed_at' => now()]);

        return response()->json($segment->fresh());
    }

    /**
     * POST /api/v1/work-logs/{workLog}/segments/{segment}/approve
     * Aprueba un tramo (coordinator/admin).
     */
    public function approve(Request $request, WorkLog $workLog, WorkLogSegment $segment)
    {
        $oldStatus = $segment->status;
        $segment->update([
            'status' => 'approved',
            'modified_by' => $request->user()->id,
            'modified_at' => now(),
        ]);

        // Recalcular tiempo efectivo: el tramo aprobado ya cuenta
        $workLog->recalcularEfectivo();

        WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'approved',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'approved'],
            'comment' => 'Tramo ' . $segment->segment_number . ' aprobado',
        ]);

        return response()->json($segment->fresh());
    }
}
