<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Guardes d'autorització per a casos on la propietat es deriva del MODEL
 * (no d'un paràmetre de ruta) — p. ex. una nòmina o un tram pertanyen a un
 * user_id que cal mirar després de carregar el registre. Complementa el
 * middleware 'owner' (per a rutes amb {userId}) i 'role' (staff pur).
 *
 * Fail-closed: sense usuari → 403.
 */
trait AuthorizesOwnership
{
    /** Només personal de gestió (admin/coordinador/hr). */
    protected function ensureStaff(Request $request): void
    {
        abort_unless($request->user()?->isStaff(), 403, 'No autoritzat');
    }

    /** Només admin. */
    protected function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'No autoritzat');
    }

    /** Personal de gestió O el titular de les dades ($ownerId). */
    protected function ensureOwnerOrStaff(Request $request, $ownerId): void
    {
        $user = $request->user();
        abort_unless(
            $user && ($user->isStaff() || (int) $user->id === (int) $ownerId),
            403,
            'No autoritzat'
        );
    }
}
