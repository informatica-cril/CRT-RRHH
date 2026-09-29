<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;

class WorkScheduleController extends Controller
{
    /**
     * Gestió veu tots els quadres horaris; el treballador, NOMÉS el seu.
     * Fins ara qualsevol persona autenticada se'ls baixava tots (els 64 de la
     * plantilla sencera), que és saber l'horari de tothom sense cap necessitat.
     */
    public function index(Request $request)
    {
        $u = $request->user();
        if ($u && $u->isStaff()) {
            return response()->json(WorkSchedule::all());
        }

        return response()->json(
            $u && $u->work_schedule_id
                ? WorkSchedule::where('id', $u->work_schedule_id)->get()
                : collect()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'total_hours_weekly' => 'required|numeric|min:0',
            'days' => 'required|array',
        ]);

        // Blindatge: normalitzar l'estructura de days (day/active/start/end) perquè
        // no entrin jornades malformades que un consumidor (domi) llegiria com "no treballa".
        $data['days'] = WorkSchedule::normalizeDays($data['days']);

        return response()->json(WorkSchedule::create($data), 201);
    }

    /** Mateix criteri que el llistat: el propi quadre o gestió. */
    public function show(Request $request, WorkSchedule $workSchedule)
    {
        $u = $request->user();
        abort_unless($u && ($u->isStaff() || (int) $u->work_schedule_id === (int) $workSchedule->id),
            403, 'No autoritzat');

        return response()->json($workSchedule);
    }

    public function update(Request $request, WorkSchedule $workSchedule)
    {
        $data = $request->validate([
            'name' => 'string|max:255',
            'total_hours_weekly' => 'numeric|min:0',
            'days' => 'array',
        ]);

        if (array_key_exists('days', $data)) {
            $data['days'] = WorkSchedule::normalizeDays($data['days']);
        }

        $workSchedule->update($data);
        return response()->json($workSchedule->fresh());
    }
}
