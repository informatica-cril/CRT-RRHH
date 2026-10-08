<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * PUT /api/v1/users/{user}/estat { active: bool }
 *
 * Activar o desactivar una persona des de la fitxa. Abans només es podia des de la base de dades
 * (RRHH, a les fitxes alienes, només pot tocar el contacte) i no hi havia manera de reactivar ningú.
 * Desactivar no esborra res: la persona deixa d'entrar però conserva fitxatges, nòmines i historial.
 */
class UserEstatController extends Controller
{
    public function update(Request $request, User $user)
    {
        $data = $request->validate(['active' => 'required|boolean']);
        $error = $this->aplica($request, $user, (bool) $data['active']);
        if ($error) {
            return response()->json(['message' => $error[1]], $error[0]);
        }

        return response()->json(['id' => $user->id, 'active' => (bool) $user->active]);
    }

    /**
     * POST /api/v1/users/estat-seleccio { ids: [..], active: bool }
     * El mateix que la fitxa, per a una selecció (p. ex. tota l'ambulatòria d'un cop). Les persones
     * que no es poden canviar (un mateix, administració) es retornen a «omesos» amb el motiu.
     */
    public function seleccio(Request $request)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'active' => 'required|boolean',
        ]);

        $canviats = 0; $omesos = [];
        foreach (User::whereIn('id', $data['ids'])->get() as $user) {
            $abans = (bool) $user->active;
            $error = $this->aplica($request, $user, (bool) $data['active']);
            if ($error) { $omesos[] = ['id' => $user->id, 'motiu' => $error[1]]; continue; }
            if ($abans !== (bool) $user->active) $canviats++;
        }

        return response()->json(['canviats' => $canviats, 'omesos' => $omesos]);
    }

    /** Canvia l'estat d'una persona. Retorna [codi, motiu] si no es pot, o null. */
    private function aplica(Request $request, User $user, bool $active): ?array
    {
        $jo = $request->user();
        if ($jo->id === $user->id) {
            return [422, 'No podeu canviar el vostre propi estat.'];
        }
        if ($jo->role !== 'admin' && in_array($user->role, ['admin', 'service'], true)) {
            return [403, "Els comptes d'administració només els pot activar o desactivar l'administració."];
        }
        if ((bool) $user->active === $active) {
            return null;
        }

        $user->forceFill(['active' => $active])->save();
        if (! $active) {
            // Que deixi d'entrar ara, no quan li caduqui la sessió.
            $user->tokens()->delete();
        }

        try {
            AuditLog::create([
                'user_id' => $jo->id, 'action' => $active ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
                'entity_type' => 'user', 'entity_id' => $user->id,
                'description' => ($active ? 'Reactivada: ' : 'Desactivada: ') . $user->name . " (per {$jo->name})",
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}
