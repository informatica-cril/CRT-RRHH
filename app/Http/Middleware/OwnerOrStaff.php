<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Autorització de recursos "per usuari": deixa passar el personal de gestió
 * (isStaff) o el propi titular de les dades. Per a rutes amb un paràmetre que
 * identifica l'usuari propietari.
 *
 *   ->middleware('owner')          // paràmetre 'userId' (per defecte)
 *   ->middleware('owner:user')     // paràmetre de ruta 'user'
 *
 * Fail-closed: sense usuari autenticat, o si el paràmetre no coincideix amb
 * l'id propi i no és staff → 403.
 */
class OwnerOrStaff
{
    public function handle(Request $request, Closure $next, string $param = 'userId')
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autoritzat'], 403);
        }
        if ($user->isStaff()) {
            return $next($request);
        }

        $target = $request->route($param);
        // El paràmetre pot ser un id escalar o un model amb ->id (route-model binding).
        if (is_object($target)) {
            $target = $target->id ?? $target->getKey();
        }
        if ($target !== null && (string) $target === (string) $user->id) {
            return $next($request);
        }

        return response()->json(['message' => 'No autoritzat'], 403);
    }
}
