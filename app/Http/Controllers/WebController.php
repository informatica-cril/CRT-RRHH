<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class WebController extends Controller
{
    /**
     * Vista detallada de un fichaje con tramos segmentados.
     * GET /work-logs/{workLogId}/detail
     */
    public function workLogDetail(Request $request, $workLogId)
    {
        return Inertia::render('Administracion/ControlAsistencia/WorkLogDetail', [
            'workLogId' => $workLogId,
            'user' => $request->user(),
        ]);
    }

    /**
     * Configuración de pausa obligatoria (solo admin).
     * GET /break-settings
     */
    public function breakSettings(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'coordinator'])) {
            abort(403, 'No autoritzat');
        }

        return Inertia::render('Administracion/ControlAsistencia/BreakSettings', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Acreditacions de la formació obligatòria (admin i RRHH). La vista carrega les dades per
     * axios des de /api/v1/formacio/acreditacions (que fa de proxy a domi). GET /formacio-acreditacions
     */
    public function formacioAcreditacions(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'hr'])) {
            abort(403, 'No autoritzat');
        }
        return Inertia::render('Administracion/FormacioAcreditacions', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Panel de coordinación (admin + coordinator).
     * GET /coordinator-panel
     */
    public function coordinatorPanel(Request $request)
    {
        if (!in_array($request->user()->role, ['admin', 'coordinator'])) {
            abort(403, 'No autoritzat');
        }

        return Inertia::render('Administracion/ControlAsistencia/CoordinatorPanel', [
            'user' => $request->user(),
        ]);
    }
}
