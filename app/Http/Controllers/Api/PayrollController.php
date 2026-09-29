<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Columnes del LLISTAT: tot menys payroll_base64 (el PDF).
     * Amb 1.598 nòmines a producció el base64 són ~24 MB a la BD que el JSON
     * duplicava (payroll_base64 + l'accessor pdf_data) fins a ~50 MB: el procés
     * petava el memory_limit de 128 MB i l'endpoint responia 500.
     * El PDF es demana per fila a GET /payroll-files/{payroll} quan cal obrir-lo.
     */
    private const LIST_COLUMNS = [
        'id', 'user_id', 'title', 'month', 'year', 'amount', 'file_name',
        'viewed_at', 'signed_at', 'signature_hash', 'expires_at',
        'created_at', 'updated_at',
    ];

    public function index()
    {
        $this->authorize('viewAny', Payroll::class);

        return response()->json(
            Payroll::select(self::LIST_COLUMNS)
                ->with('user:id,name,email')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->get()
                // Doble tanca: encara que algú torni a seleccionar la columna,
                // l'accessor appended no ha de serialitzar el PDF al llistat.
                ->makeHidden('pdf_data')
        );
    }

    public function workerPayrolls($userId)
    {
        // Administració o el titular; 'hr'/'coordinator' NO (vegeu PayrollPolicy).
        $this->authorize('viewAnyOf', [Payroll::class, $userId]);

        $payrolls = Payroll::where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json($payrolls);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Payroll::class);
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required|string', // Expects "YYYY-MM"
            'pdf_data' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'file_name' => 'nullable|string'
        ]);

        // Parse YYYY-MM
        if (str_contains($data['month'], '-')) {
            [$year, $month] = explode('-', $data['month']);
            $data['year'] = (int)$year;
            $data['month'] = (int)$month;
        }

        // Map pdf_data to payroll_base64 if needed by the model
        $data['payroll_base64'] = $data['pdf_data'];
        $data['title'] = $data['file_name'] ?? "Nòmina {$data['year']}-{$data['month']}";
        unset($data['pdf_data']);

        $payroll = Payroll::create($data);
        return response()->json($payroll->load('user'), 201);
    }

    public function bulkStore(Request $request)
    {
        $this->authorize('create', Payroll::class);
        $request->validate([
            'payrolls' => 'required|array',
            'payrolls.*.user_id' => 'required|exists:users,id',
            'payrolls.*.month' => 'required|string',
            'payrolls.*.pdf_data' => 'required|string',
            'payrolls.*.amount' => 'nullable|numeric|min:0',
            'payrolls.*.notes' => 'nullable|string',
        ]);

        $createdCount = 0;
        foreach ($request->payrolls as $p) {
            // Parse YYYY-MM
            if (str_contains($p['month'], '-')) {
                [$year, $month] = explode('-', $p['month']);
                $p['year'] = (int)$year;
                $p['month'] = (int)$month;
            }
            
            $p['payroll_base64'] = $p['pdf_data'];
            $p['title'] = $p['file_name'] ?? "Nòmina {$p['year']}-{$p['month']}";
            unset($p['pdf_data']);
            
            Payroll::create($p);
            $createdCount++;
        }

        return response()->json(['message' => "$createdCount nòmines pujades correctament"]);
    }

    public function show(Request $request, Payroll $payroll)
    {
        $this->authorize('view', $payroll);
        return response()->json($payroll);
    }

    public function markAsViewed(Request $request, Payroll $payroll)
    {
        $this->authorize('markViewed', $payroll);
        if (!$payroll->viewed_at) {
            $payroll->update(['viewed_at' => now()]);
        }
        return response()->json($payroll);
    }

    public function sign(Request $request, Payroll $payroll)
    {
        // Acte personal del titular: ningú signa la nòmina d'un altre (ni l'admin).
        $this->authorize('sign', $payroll);
        $request->validate([
            'signature_hash' => 'required|string',
        ]);

        $payroll->update([
            'signed_at' => now(),
            'signature_hash' => $request->signature_hash,
            'viewed_at' => $payroll->viewed_at ?? now(),
        ]);

        return response()->json($payroll);
    }

    public function destroy(Payroll $payroll)
    {
        $this->authorize('delete', $payroll);
        $payroll->delete();
        return response()->json(['message' => 'Nómina eliminada']);
    }
}
