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

    /**
     * POST /api/v1/payroll-files/importa-portal-antic
     * Body: { nomines: [{ dni, data, pdf, nom_fitxer? }] }  (una tanda; el navegador les envia en diverses)
     *
     * Importa les nòmines del portal antic que FALTEN: la persona es troba per DNI (amb o sense
     * zeros al davant) i una nòmina es dona per ja pujada si aquella persona ja té el MATEIX PDF.
     * Les noves entren sense signar: la signatura és un acte de la persona titular.
     * Retorna per a cada element: importada | ja_hi_era | sense_persona | pdf_no_valid.
     */
    public function importaPortalAntic(Request $request)
    {
        $this->authorize('create', Payroll::class);
        $data = $request->validate([
            'nomines' => 'required|array|max:50',
            'nomines.*.dni' => 'required|string|max:30',
            'nomines.*.data' => 'required|date',
            'nomines.*.pdf' => 'required|string',
            'nomines.*.nom_fitxer' => 'nullable|string|max:200',
        ]);

        $mesos = [1 => 'Gener', 2 => 'Febrer', 3 => 'Març', 4 => 'Abril', 5 => 'Maig', 6 => 'Juny',
            7 => 'Juliol', 8 => 'Agost', 9 => 'Setembre', 10 => 'Octubre', 11 => 'Novembre', 12 => 'Desembre'];
        $normDni = fn ($d) => ltrim(strtoupper(preg_replace('/[\s\-.]/', '', (string) $d)), '0');
        $nomesBase64 = fn ($b) => preg_replace('/^data:[^,]*,/', '', (string) $b);
        $empremta = function ($b) use ($nomesBase64) {
            $bin = base64_decode($nomesBase64($b), true);

            return $bin === false || $bin === '' ? null : hash('sha256', $bin);
        };

        $persones = \App\Models\User::whereNotNull('dni')->where('dni', '!=', '')->pluck('id', 'dni')
            ->mapWithKeys(fn ($id, $dni) => [$normDni($dni) => $id]);
        $jaPujades = []; // user_id => [empremta => true]

        $resultats = [];
        $compte = ['importada' => 0, 'ja_hi_era' => 0, 'sense_persona' => 0, 'pdf_no_valid' => 0];
        foreach ($data['nomines'] as $n) {
            $userId = $persones[$normDni($n['dni'])] ?? null;
            $hash = $empremta($n['pdf']);
            $estat = match (true) {
                ! $userId => 'sense_persona',
                ! $hash || ! str_starts_with(base64_decode($nomesBase64($n['pdf'])), '%PDF') => 'pdf_no_valid',
                default => null,
            };

            if (! $estat) {
                if (! isset($jaPujades[$userId])) {
                    $jaPujades[$userId] = [];
                    foreach (Payroll::where('user_id', $userId)->pluck('payroll_base64') as $b) {
                        if ($h = $empremta($b)) $jaPujades[$userId][$h] = true;
                    }
                }
                if (isset($jaPujades[$userId][$hash])) {
                    $estat = 'ja_hi_era';
                } else {
                    $quan = \Carbon\Carbon::parse($n['data']);
                    $mes = str_pad((string) $quan->month, 2, '0', STR_PAD_LEFT);
                    $title = 'Nòmina ' . $mesos[$quan->month] . ' ' . $quan->year;
                    // Segon document del mateix mes (p. ex. la paga extra): que no tingui el mateix títol.
                    if (Payroll::where('user_id', $userId)->where('year', $quan->year)->where('month', $mes)->exists()) {
                        $title .= ' (2n document)';
                    }
                    Payroll::create([
                        'user_id' => $userId,
                        'title' => $title,
                        'month' => $mes,
                        'year' => $quan->year,
                        'payroll_base64' => $nomesBase64($n['pdf']),
                        'file_name' => $n['nom_fitxer'] ?? null,
                    ]);
                    $jaPujades[$userId][$hash] = true;
                    $estat = 'importada';
                }
            }

            $compte[$estat]++;
            $resultats[] = $estat;
        }

        return response()->json(['compte' => $compte, 'resultats' => $resultats]);
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
