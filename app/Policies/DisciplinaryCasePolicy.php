<?php

namespace App\Policies;

use App\Models\DisciplinaryCase;
use App\Models\User;

/**
 * EXPEDIENT DISCIPLINARI — la regla depèn de la FILA: qui és la persona expedientada.
 *
 * AL·LEGAR i RENUNCIAR al termini són actes de DEFENSA i, per tant, exclusius de la persona
 * expedientada. Ni admin ni RRHH poden fer-los en nom seu: unes al·legacions escrites per la part
 * acusadora, o una renúncia al termini decidida per l'empresa, buiden el tràmit d'audiència
 * (art. 55.1 ET i art. 55.K.6 del XII Conveni de la sanitat concertada) i fan nul·la la sanció.
 *
 * Fail-closed: sense titular vinculat o sense coincidència, false.
 */
class DisciplinaryCasePolicy
{
    /** Presentar al·legacions o renunciar al termini: només la persona expedientada. */
    public function alegar(User $user, DisciplinaryCase $case): bool
    {
        return $case->user_id !== null && (int) $user->id === (int) $case->user_id;
    }
}
