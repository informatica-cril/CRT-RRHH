<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLogAlert;
use Illuminate\Http\Request;

class WorkLogAlertController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesOwnership;

    /**
     * GET /api/v1/work-log-alerts/pending/{userId}
     * Devuelve las alertas pendientes del usuario.
     */
    public function pending($userId)
    {
        $alerts = WorkLogAlert::where('user_id', $userId)
            ->pending()
            ->where(function ($q) {
                $q->whereNull('sent_at')
                  ->orWhere('sent_at', '<=', now());
            })
            ->orderBy('scheduled_at', 'asc')
            ->get();

        return response()->json($alerts);
    }

    /**
     * POST /api/v1/work-log-alerts/{alert}/dismiss
     * Descarta una alerta.
     */
    public function dismiss(Request $request, WorkLogAlert $alert)
    {
        $this->ensureOwnerOrStaff($request, $alert->user_id);
        $alert->update(['dismissed_at' => now()]);
        return response()->json(['success' => true]);
    }
}
