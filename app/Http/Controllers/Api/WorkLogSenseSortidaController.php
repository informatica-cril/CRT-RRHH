<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLog;
use App\Models\WorkLogModification;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Jornades sense sortida: fitxatges d'un dia anterior que segueixen oberts perquè la persona
 * no va fitxar la sortida.
 *
 * El registre NO es modifica mai des d'aquí: la jornada continua sense sortida (i compta 0 h).
 * La persona pot DECLARAR a quina hora va sortir i per què; la declaració queda com un document
 * a part (WorkLogModification 'allegacio', signat per ella amb data) perquè RRHH decideixi.
 */
class WorkLogSenseSortidaController extends Controller
{
    /** Marca de les declaracions dins de work_log_modifications. */
    public const TIPUS = 'declaracio_sortida';

    /** Fitxatges oberts de dies anteriors a avui (hora de Madrid). */
    public static function oberts()
    {
        return WorkLog::whereNull('end_time')
            ->whereDate('date', '<', Carbon::now('Europe/Madrid')->toDateString());
    }

    /** Declaració de sortida d'un fitxatge, si n'hi ha. */
    public static function declaracio(WorkLog $log): ?array
    {
        $m = WorkLogModification::where('work_log_id', $log->id)->where('action', 'allegacio')
            ->orderByDesc('id')->get()
            ->first(fn ($m) => ($m->new_values['tipus'] ?? null) === self::TIPUS);

        return $m ? [
            'sortida' => $m->new_values['sortida_declarada'] ?? null,
            'explicacio' => $m->new_values['explicacio'] ?? null,
            'data' => $m->created_at?->format('Y-m-d H:i'),
        ] : null;
    }

    private function fila(WorkLog $log): array
    {
        return [
            'id' => $log->id,
            'user_id' => $log->user_id,
            'nom' => $log->user?->name,
            'date' => Carbon::parse($log->date)->toDateString(),
            'start_time' => $log->getRawOriginal('start_time'),
            'declaracio' => self::declaracio($log),
        ];
    }

    /** GET /api/v1/work-logs/sense-sortida — les meves jornades sense sortida. */
    public function meves(Request $request)
    {
        return response()->json(
            self::oberts()->where('user_id', $request->user()->id)->orderBy('date')->get()
                ->map(fn ($l) => $this->fila($l))->values()
        );
    }

    /** GET /api/v1/work-logs/sense-sortida/equip — totes (admin, RRHH i coordinació). */
    public function equip()
    {
        return response()->json(
            self::oberts()->with('user:id,name')->orderByDesc('date')->get()
                ->map(fn ($l) => $this->fila($l))->values()
        );
    }

    /** POST /api/v1/work-logs/{workLog}/declara-sortida   Body: { sortida: 'Y-m-d H:i', explicacio } */
    public function declara(Request $request, WorkLog $workLog)
    {
        abort_if($request->user()->id !== $workLog->user_id, 403, 'Només la persona titular pot declarar la seva sortida.');
        $data = $request->validate([
            'sortida' => 'required|date_format:Y-m-d H:i',
            'explicacio' => 'required|string|min:10|max:1000',
        ]);
        abort_if($workLog->end_time !== null, 422, 'Aquesta jornada ja té la sortida fitxada.');
        abort_if(Carbon::parse($workLog->date)->toDateString() >= Carbon::now('Europe/Madrid')->toDateString(), 422,
            'La jornada d\'avui encara es pot tancar normalment des del Tauler.');
        abort_if(self::declaracio($workLog) !== null, 422, 'Ja has declarat la sortida d\'aquesta jornada.');

        $inici = Carbon::parse($workLog->getRawOriginal('start_time'), 'Europe/Madrid');
        $sortida = Carbon::parse($data['sortida'], 'Europe/Madrid');
        abort_if($sortida->lte($inici), 422, 'La sortida ha de ser posterior a l\'entrada.');
        abort_if($inici->diffInHours($sortida) > 24, 422, 'Una jornada no pot durar més de 24 hores.');
        abort_if($sortida->gt(Carbon::now('Europe/Madrid')), 422, 'La sortida no pot ser en el futur.');

        WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'allegacio',
            'new_values' => [
                'tipus' => self::TIPUS,
                'sortida_declarada' => $sortida->format('Y-m-d H:i'),
                'explicacio' => $data['explicacio'],
            ],
            'comment' => 'Declaració de sortida no fitxada: ' . $sortida->format('d/m/Y H:i') . '. ' . $data['explicacio']
                . ' (el registre no es modifica; ho revisa RRHH)',
        ]);

        return response()->json($this->fila($workLog->fresh()), 201);
    }
}
