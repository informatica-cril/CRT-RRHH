<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\QuadrantDia;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Neteja de plantilles d'horari (RRHH, 19-10-2026): n'hi havia 138, la meitat sense ningú i moltes
 * repetides amb noms diferents. RRHH i admin veuen qui fa servir cada plantilla, ajunten les que fan
 * el mateix horari, esborren les que no fa servir ningú i corregeixen les hores que no quadren.
 * Ajuntar no canvia l'horari de ningú (les plantilles són idèntiques): només el número de plantilla.
 */
class PlantillesHorariController extends Controller
{
    /** GET /api/v1/plantilles-horari */
    public function index()
    {
        $persones = User::whereNotNull('work_schedule_id')
            ->selectRaw('work_schedule_id, SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as actius, SUM(CASE WHEN active = 1 THEN 0 ELSE 1 END) as inactius')
            ->groupBy('work_schedule_id')->get()->keyBy('work_schedule_id');
        $quadrant = QuadrantDia::whereNotNull('work_schedule_id')
            ->selectRaw('work_schedule_id, COUNT(*) as n')->groupBy('work_schedule_id')->pluck('n', 'work_schedule_id');

        return response()->json(WorkSchedule::orderBy('id')->get()->map(fn (WorkSchedule $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'total_hours_weekly' => (float) $s->total_hours_weekly,
            'minuts_reals' => WorkSchedule::minutsSetmanals($s->days),
            'trams' => WorkSchedule::tramsPerDia($s->days),
            'signatura' => WorkSchedule::signatura($s->days),
            'persones_actives' => (int) ($persones[$s->id]->actius ?? 0),
            'persones_inactives' => (int) ($persones[$s->id]->inactius ?? 0),
            'dies_quadrant' => (int) ($quadrant[$s->id] ?? 0),
        ]));
    }

    /** DELETE /api/v1/plantilles-horari/{workSchedule} — només si no la fa servir ningú. */
    public function destroy(Request $request, WorkSchedule $workSchedule)
    {
        $this->noEsFaServir($workSchedule);
        $nom = $workSchedule->name;
        $workSchedule->delete();
        $this->audita($request, "Plantilla d'horari esborrada: «{$nom}» (#{$workSchedule->id}), no la feia servir ningú");

        return response()->json(['esborrades' => 1]);
    }

    /** POST /api/v1/plantilles-horari/esborra-sense-us — totes les que no fa servir ningú. */
    public function esborraSenseUs(Request $request)
    {
        $usades = User::whereNotNull('work_schedule_id')->distinct()->pluck('work_schedule_id')
            ->merge(QuadrantDia::whereNotNull('work_schedule_id')->distinct()->pluck('work_schedule_id'))
            ->unique()->all();
        $ids = WorkSchedule::whereNotIn('id', $usades)->pluck('id');
        WorkSchedule::whereIn('id', $ids)->delete();
        $this->audita($request, "Plantilles d'horari esborrades perquè no les feia servir ningú: {$ids->count()} (#" . $ids->implode(', #') . ')');

        return response()->json(['esborrades' => $ids->count()]);
    }

    /**
     * POST /api/v1/plantilles-horari/{workSchedule}/ajunta { ids: [...] }
     * Les plantilles «ids» (que han de fer el mateix horari) passen a ser aquesta: la gent i els
     * dies del quadrant que les feien servir passen a aquesta, i les altres s'esborren.
     */
    public function ajunta(Request $request, WorkSchedule $workSchedule)
    {
        $data = $request->validate(['ids' => 'required|array|min:1|max:200', 'ids.*' => 'integer|exists:work_schedules,id']);
        $ids = collect($data['ids'])->map(fn ($i) => (int) $i)->reject(fn ($i) => $i === $workSchedule->id)->unique()->values();
        $signatura = WorkSchedule::signatura($workSchedule->days);
        $diferents = WorkSchedule::whereIn('id', $ids)->get()->filter(fn ($s) => WorkSchedule::signatura($s->days) !== $signatura);
        if ($signatura === '' || $diferents->isNotEmpty()) {
            throw ValidationException::withMessages(['ids' => 'Només es poden ajuntar plantilles que fan exactament el mateix horari.']);
        }

        $persones = 0;
        DB::transaction(function () use ($ids, $workSchedule, &$persones) {
            $persones = User::whereIn('work_schedule_id', $ids)->update(['work_schedule_id' => $workSchedule->id]);
            QuadrantDia::whereIn('work_schedule_id', $ids)->update(['work_schedule_id' => $workSchedule->id]);
            WorkSchedule::whereIn('id', $ids)->delete();
        });
        $this->audita($request, "Plantilles d'horari ajuntades a «{$workSchedule->name}» (#{$workSchedule->id}): #"
            . $ids->implode(', #') . " ({$persones} persona(es) canviades de plantilla, mateix horari)");

        return response()->json(['ajuntades' => $ids->count(), 'persones' => $persones]);
    }

    private function noEsFaServir(WorkSchedule $s): void
    {
        if (User::where('work_schedule_id', $s->id)->exists() || QuadrantDia::where('work_schedule_id', $s->id)->exists()) {
            throw ValidationException::withMessages(['plantilla' => 'Aquesta plantilla la fa servir algú: no es pot esborrar (es pot ajuntar amb una d\'igual).']);
        }
    }

    private function audita(Request $request, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'NETEJA_PLANTILLES', 'entity_type' => 'work_schedule',
                'description' => mb_substr($descripcio, 0, 1000),
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
