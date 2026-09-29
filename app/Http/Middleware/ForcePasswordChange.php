<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * ENS: si l'usuari autenticat té must_change_password=true (una temporal del reset o de
 * l'alta), BLOQUEJA qualsevol operació fins que la canviï. Equivalent al pwd_guard de domi.
 *
 * Rutes SEMPRE permeses (per poder complir l'obligació i sortir): el canvi propi, el
 * logout i el "me" (perquè el frontend sàpiga que ha de redirigir al canvi).
 * FAIL-SAFE: sense usuari, o si l'atribut no existeix, deixa passar (no és feina d'aquest
 * middleware autenticar; només força el canvi quan el flag hi és).
 */
class ForcePasswordChange
{
    private const PERMESES = [
        'api/v1/auth/change-password',
        'api/v1/auth/logout',
        'api/v1/users/me',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && (bool) ($user->must_change_password ?? false)
            && ! in_array($request->path(), self::PERMESES, true)) {
            return response()->json([
                'error' => 'password_change_required',
                'message' => 'Has de canviar la contrasenya abans de continuar.',
            ], 423);   // 423 Locked: la sessió és vàlida, però bloquejada fins al canvi
        }
        return $next($request);
    }
}
