<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Excedencia;
use App\Models\ExcedenciaType;
use Illuminate\Http\Request;

class ExcedenciaController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesOwnership;
    use \App\Http\Controllers\Concerns\RegistraDecisions;

    public function indexTypes()
    {
        return response()->json(ExcedenciaType::all());
    }

    public function index()
    {
        return response()->json(
            Excedencia::with(['user:id,name', 'excedenciaType:id,name'])->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'excedencia_type_id' => 'required|exists:excedencia_types,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Un worker només pot demanar excedència per a si mateix; gestió, per a qualsevol.
        $this->ensureOwnerOrStaff($request, $data['user_id']);

        return response()->json(Excedencia::create($data), 201);
    }

    /**
     * Resolució d'una excedència. Mateix criteri que les absències: qui resol surt
     * del token (mai del cos), es desa la data, denegar exigeix motiu escrit, queda
     * a l'auditoria i el treballador rep l'avís.
     */
    public function update(Request $request, Excedencia $excedencia)
    {
        $data = $request->only([
            'start_date', 'end_date', 'reincorporation_date',
            'status', 'reason', 'notes',
        ]);

        $resol = isset($data['status']) && in_array($data['status'], ['approved', 'rejected'], true);
        if ($resol) {
            $motiu = trim((string) $request->input('denial_reason', ''));
            if ($data['status'] === 'rejected' && $motiu === '') {
                return response()->json([
                    'message' => 'Per denegar una excedència cal un motiu escrit.',
                    'errors' => ['denial_reason' => ['El motiu de la denegació és obligatori.']],
                ], 422);
            }
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
            $data['denial_reason'] = $data['status'] === 'rejected' ? $motiu : null;
        }

        $excedencia->update($data);
        $excedencia->refresh();

        if ($resol) {
            $aprovada = $data['status'] === 'approved';
            $this->registraDecisio($request, 'EXCEDENCIA_' . ($aprovada ? 'APPROVED' : 'DENIED'),
                'excedencia', $excedencia->id,
                sprintf('Excedència %s per %s%s', $aprovada ? 'APROVADA' : 'DENEGADA',
                    $request->user()->name, $aprovada ? '' : '. Motiu: ' . $data['denial_reason']));
            $this->avisaTreballador($excedencia->user_id, $aprovada
                ? 'La teva sol·licitud d\'excedència ha estat APROVADA.'
                : 'La teva sol·licitud d\'excedència ha estat DENEGADA. Motiu: ' . $data['denial_reason']);
        }

        return response()->json($excedencia);
    }

    public function byUser($userId)
    {
        return response()->json(
            Excedencia::where('user_id', $userId)
                ->with('excedenciaType:id,name')
                ->get()
        );
    }
}
