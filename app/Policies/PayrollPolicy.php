<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

/**
 * NÒMINES — regles que depenen de la FILA, no del rol sol.
 *
 * Criteri de producte (coherent amb blockHr al router de Vue):
 *   · La retribució és d'ADMINISTRACIÓ: només 'admin' hi té visió de conjunt.
 *   · El rol 'hr' (Responsable RRHH) NO hi entra, tot i ser casi-admin per a la resta.
 *   · 'coordinator' tampoc: gestiona operativa (fichatges, zones), no retribució.
 *   · El TITULAR sempre veu i signa la SEVA nòmina — i ningú signa per ell: l'acusament
 *     de recepció d'una nòmina és un acte personal (art. 29.1 ET), i una signatura
 *     posada per un tercer no acredita res.
 *
 * Fail-closed: qualsevol cas no contemplat retorna false.
 */
class PayrollPolicy
{
    /** Visió de conjunt de la retribució de la plantilla. */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /** Nòmines d'un tercer identificat per id (ruta payroll-files/worker/{userId}). */
    public function viewAnyOf(User $user, $targetUserId): bool
    {
        return $user->isAdmin() || (int) $user->id === (int) $targetUserId;
    }

    /** Una nòmina concreta: administració o el seu titular. */
    public function view(User $user, Payroll $payroll): bool
    {
        return $user->isAdmin() || (int) $user->id === (int) $payroll->user_id;
    }

    /** Marcar com a vista: mateix criteri que veure-la. */
    public function markViewed(User $user, Payroll $payroll): bool
    {
        return $this->view($user, $payroll);
    }

    /** Signar: NOMÉS el titular. Cap gestor ni l'admin poden signar per ell. */
    public function sign(User $user, Payroll $payroll): bool
    {
        return (int) $user->id === (int) $payroll->user_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Payroll $payroll): bool
    {
        return $user->isAdmin();
    }
}
