<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Zone;
use App\Models\WorkLocation;
use Illuminate\Http\Request;

class GeolocationController extends Controller
{
    /**
     * Get active work locations (physical points) for a user.
     */
    /**
     * GET /api/v1/geolocation/pilot-radi — NOMÉS ADMIN.
     * Panell visual de l'informe empíric del pilot (EIPD §6.1.bis): el radi
     * aconsellat a fixar després del període de prova, amb l'evidència que el sustenta.
     */
    /**
     * POST /api/v1/device/enroll — canvia la CLAU d'aprovisionament (URL de Hexnode)
     * pel TOKEN HMAC del dispositiu corporatiu. En dispositiu corporatiu, la denegació
     * del GPS queda bloquejada (el flux manual és només per a dispositiu personal).
     */
    public function deviceEnroll(\Illuminate\Http\Request $request)
    {
        $key = (string) config('services.disp_corp.key');
        $clau = (string) $request->input('clau', '');
        if (strlen($key) < 32 || ! hash_equals($key, $clau)) {
            return response()->json(['ok' => false], 422);
        }

        return response()->json(['ok' => true, 'disp' => hash_hmac('sha256', 'dispositiu-corporatiu-crt', $key)]);
    }

    public function pilotRadi(\Illuminate\Http\Request $request)
    {
        $from = $request->query('from') ?: now()->subDays(30)->toDateString();
        $to = $request->query('to') ?: now()->toDateString();

        return response()->json(\App\Services\PilotRadiStats::compute($from, $to));
    }

    public function ambulatoryPoints($userId)
    {
        $user = User::findOrFail($userId);
        
        // 1. Puntos manuales (aislados) directamente asignados al usuario
        $manualLocations = $user->workLocations;

        // 2. Puntos heredados a través de los Centros Ambulatorios asignados al usuario
        $centerLocations = $user->ambulatoryCenters()->with('workLocations')->get()
            ->pluck('workLocations')
            ->flatten();

        // Mezclar ambos y evitar duplicados (por si un usuario tiene el mismo punto manual y de centro)
        $allLocations = $manualLocations->merge($centerLocations)->unique('id')->values();

        return response()->json($allLocations);
    }

    /**
     * Get active zones (postal codes/municipalities) for a user.
     */
    public function municipalAssignment($userId)
    {
        $user = User::findOrFail($userId);
        $today = now()->toDateString();

        // Only assignments valid today, most recent valid_from first.
        $zones = $user->zones->filter(function ($z) use ($today) {
            $from = $z->pivot->valid_from ?? null;
            $to = $z->pivot->valid_to ?? null;
            return (!$from || $from <= $today) && (!$to || $to >= $today);
        })->sortByDesc(fn ($z) => $z->pivot->valid_from)->values();

        $gv = \App\Models\WorkerGeofence::where('user_id', $user->id)->first();
        $domiGeovalla = $gv ? ['cps' => $gv->cps ?: [], 'poligons' => $gv->poligons ?: []] : null;

        // Frontend legacy expectation: an object with postal_codes/municipalities
        // of the most recent assignment that is active today.
        if (!$zones || $zones->isEmpty()) {
            return response()->json($domiGeovalla ? ['domi_geovalla' => $domiGeovalla] : null);
        }

        $out = $zones->first()->toArray();
        if ($domiGeovalla) $out['domi_geovalla'] = $domiGeovalla;
        return response()->json($out);
    }
}
