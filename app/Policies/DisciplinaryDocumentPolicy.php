<?php

namespace App\Policies;

use App\Models\DisciplinaryDocument;
use App\Models\User;

/**
 * ESCRITS DISCIPLINARIS — la regla depèn de la FILA: qui és el destinatari.
 *
 *   · Redactar, signar i notificar és de l'empresa: 'admin' i 'hr' (les rutes de
 *     /disciplinary ja porten role:admin,hr; això és la segona barrera i la que
 *     decideix quan la regla mira el registre).
 *   · ACUSAR RECEPCIÓ és un acte PERSONAL i EXCLUSIU del destinatari: un acusament
 *     posat per un tercer (o per la mateixa empresa) no acredita la notificació i
 *     contamina la garantia del procediment (convenio XII sanitari art. 55.K.6).
 *     Per això ni admin ni hr poden acusar en nom del treballador.
 *
 * Fail-closed: sense titular o sense coincidència, false.
 */
class DisciplinaryDocumentPolicy
{
    /** Signar l'escrit (l'empresa). */
    public function sign(User $user, DisciplinaryDocument $document): bool
    {
        return in_array($user->role, ['admin', 'hr'], true);
    }

    /** Acusar recepció: només el destinatari de l'escrit. */
    public function acknowledge(User $user, DisciplinaryDocument $document): bool
    {
        return $document->user_id !== null && (int) $user->id === (int) $document->user_id;
    }
}
