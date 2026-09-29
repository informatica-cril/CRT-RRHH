<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkLog;
use Illuminate\Http\Request;

class WorkLogModificationController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesOwnership;

    /**
     * GET /api/v1/work-logs/{workLog}/modifications
     * Lista el historial de modificaciones de un fichaje (trazabilidad).
     */
    public function index(Request $request, WorkLog $workLog)
    {
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        return response()->json(
            $workLog->modifications()
                ->with('user:id,name,role')
                ->orderBy('created_at', 'desc')
                ->get()
        );
    }
}
