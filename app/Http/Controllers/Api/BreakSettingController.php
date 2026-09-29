<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BreakSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class BreakSettingController extends Controller
{
    /** Rols que veuen la configuració de pausa SENCERA (gestió del sistema). */
    private const ROLS_GESTIO = ['admin', 'coordinator'];

    /**
     * Camps que el TREBALLADOR necessita per armar el seu temporitzador de pausa
     * (src/views/WorkerDashboard.vue::startBreakCheckTimer i triggerBreak).
     *
     * És una ALLOWLIST a consciència, no una denylist: un camp nou al model no
     * s'exposa fins que algú l'afegeixi aquí. 'auto_start' i
     * 'grace_period_minutes' no hi són perquè el client no els llegeix.
     */
    private const CAMPS_TREBALLADOR = [
        'enabled',
        'threshold_hours',
        'break_duration_minutes',
        'break_start_mode',
        'break_start_offset_minutes',
        'break_start_fixed_time',
    ];

    /**
     * GET /api/v1/break-settings
     * Configuració de la pausa obligatòria.
     *
     * Gestió (admin/coordinator) → configuració sencera.
     * La resta (worker, hr) → només CAMPS_TREBALLADOR. No es pot negar del tot:
     * sense aquesta lectura el temporitzador de la pausa obligatòria de conveni
     * no es programa mai al portal del treballador.
     */
    public function show(Request $request)
    {
        $settings = BreakSetting::getSettings();

        // toArray() i no only(): el cast 'datetime:H:i:s' de break_start_fixed_time
        // només s'aplica al serialitzar. Amb only() arribaria un Carbon en ISO i el
        // client, que fa substring(0,5) sobre l'hora, calcularia una hora inválida.
        $payload = $settings->toArray();

        if (in_array($request->user()->role, self::ROLS_GESTIO, true)) {
            return response()->json($payload);
        }

        return response()->json(Arr::only($payload, self::CAMPS_TREBALLADOR));
    }

    /**
     * PUT /api/v1/break-settings
     * Actualiza la configuración de pausa (solo admin).
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'boolean',
            'threshold_hours' => 'integer|min:1|max:12',
            'break_duration_minutes' => 'integer|min:5|max:60',
            'auto_start' => 'boolean',
            'grace_period_minutes' => 'integer|min:0|max:30',
            'break_start_mode' => 'in:offset,fixed,auto',
            'break_start_offset_minutes' => 'integer|min:1|max:720',
            'break_start_fixed_time' => 'date_format:H:i',
        ]);

        $settings = BreakSetting::getSettings();
        $settings->update($data);

        return response()->json($settings->fresh());
    }

    /**
     * GET /api/v1/break-settings/workers
     * Llista de treballadors amb el seu override de pausa i horari.
     */
    public function workers()
    {
        $workers = \App\Models\User::where('role', 'worker')
            ->where('active', true)
            ->with('workSchedule:id,name,days,total_hours_weekly')
            ->select(['id', 'name', 'work_schedule_id', 'break_override', 'break_override_time', 'job_profile', 'work_type'])
            ->orderBy('name')
            ->get();

        return response()->json($workers);
    }

    /**
     * PUT /api/v1/break-settings/workers/{user}
     * Actualitza l'override de pausa per a un treballador concret.
     * break_override: null = global, true = força activada, false = desactivada
     * break_override_time: null = global, "HH:MM" = hora personalitzada
     */
    public function updateWorker(\Illuminate\Http\Request $request, \App\Models\User $user)
    {
        $data = $request->validate([
            'break_override' => 'nullable|boolean',
            'break_override_time' => 'nullable|date_format:H:i',
        ]);

        $update = [];
        if (array_key_exists('break_override', $data)) {
            $update['break_override'] = $data['break_override'];
        }
        if (array_key_exists('break_override_time', $data)) {
            $update['break_override_time'] = $data['break_override_time'];
        }

        $user->update($update);

        return response()->json($user->fresh()->only(['id', 'name', 'break_override', 'break_override_time']));
    }
}
