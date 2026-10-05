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
        $jo = $request->user();

        if ($jo->id === $user->id) {
            return response()->json(['message' => 'No podeu canviar el vostre propi estat.'], 422);
        }
        if ($jo->role !== 'admin' && in_array($user->role, ['admin', 'service'], true)) {
            return response()->json(['message' => "Els comptes d'administració només els pot activar o desactivar l'administració."], 403);
        }
        if ((bool) $user->active === (bool) $data['active']) {
            return response()->json(['id' => $user->id, 'active' => (bool) $user->active]);
        }

        $user->forceFill(['active' => $data['active']])->save();
        if (! $data['active']) {
            // Que deixi d'entrar ara, no quan li caduqui la sessió.
            $user->tokens()->delete();
        }

        try {
            AuditLog::create([
                'user_id' => $jo->id, 'action' => $data['active'] ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
                'entity_type' => 'user', 'entity_id' => $user->id,
                'description' => ($data['active'] ? 'Reactivada: ' : 'Desactivada: ') . $user->name . " (per {$jo->name})",
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['id' => $user->id, 'active' => (bool) $user->active]);
    }
}
