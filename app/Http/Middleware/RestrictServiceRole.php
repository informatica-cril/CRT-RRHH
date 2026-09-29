<?php

namespace App\Http\Middleware;

use App\Support\DomiScopes;
use Closure;
use Illuminate\Http\Request;

class RestrictServiceRole
{
    /**
     * El compte de servei (role=service, usat per la app domi) està ACOTAT.
     *
     * Abans el guarda era un prefix -tot /api/v1/domi/* i /api/v1/servei/* obert-,
     * de manera que una ruta nova sota aquell prefix naixia abastable sense que
     * ningú ho decidís. Ara mana el mapa de DomiScopes: la ruta ha de sortir-hi I
     * el bitllet ha de portar el permís que hi consta. El que no surt al mapa, 403.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->role === 'service') {
            $permis = DomiScopes::permisDe($request->route()?->uri());

            if ($permis === null) {
                return response()->json([
                    'message' => 'Compte de servei: aquesta ruta no forma part del pont amb domi.',
                ], 403);
            }
            if (! $user->tokenCan($permis)) {
                return response()->json([
                    // Sense claus, «$permis» s'empassaria el guillemet dins del nom de la variable.
                    'message' => 'Compte de servei: el bitllet no porta el permís «' . $permis . '».',
                ], 403);
            }
        }

        return $next($request);
    }
}
