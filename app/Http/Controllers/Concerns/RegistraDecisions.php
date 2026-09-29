<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AuditLog;
use App\Models\WorkLogAlert;
use Illuminate\Http\Request;

/**
 * Rastre de les decisions de gestió (absències, permisos, excedències, hores).
 *
 * Tres coses que abans no passaven i que una decisió laboral ha de deixar sempre:
 * qui decideix (SEMPRE del token, mai del cos de la petició), quan ho decideix, i
 * constància a l'auditoria. La denegació, a més, exigeix motiu escrit.
 *
 * L'avís al treballador reutilitza el mecanisme que ja existeix (work_log_alerts:
 * el mateix que el banner i la safata ja llegeixen); no se n'estrena cap altre.
 */
trait RegistraDecisions
{
    /** Tipus d'alerta amb què viatgen les resolucions de gestió cap al treballador. */
    protected const ALERTA_RESOLUCIO = 'resolucio_rrhh';

    /** Deixa constància a audit_logs. Mai pot fer caure l'operació que la genera. */
    protected function registraDecisio(Request $request, string $accio, string $entitat, $entitatId, string $detall): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()?->id,
                'action' => $accio,
                'entity_type' => $entitat,
                'entity_id' => $entitatId,
                'description' => $detall,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 250),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Audit de decisió fallit: ' . $e->getMessage());
        }
    }

    /**
     * Avisa el treballador de la resolució. Sense això s'havia de tornar a entrar
     * a l'aplicació i mirar-ho: la persona no s'assabentava mai de res.
     */
    protected function avisaTreballador(int $userId, string $missatge): void
    {
        try {
            WorkLogAlert::create([
                'user_id' => $userId,
                'type' => self::ALERTA_RESOLUCIO,
                'message' => $missatge,
                'scheduled_at' => now(),
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Avís de resolució fallit: ' . $e->getMessage());
        }
    }
}
