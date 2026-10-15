<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\IncidenciaMail;
use App\Models\Incidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Incidències: qualsevol persona amb accés n'envia (POST); RRHH i Superior les veuen i les resolen.
 * L'avís va a la Safata i per correu a l'adreça de RRHH (mail.reply_to). Si el correu falla,
 * la incidència queda desada igualment.
 */
class IncidenciaController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'tipus' => ['required', Rule::in(Incidencia::TIPUS)],
            'assumpte' => 'required|string|max:150',
            'descripcio' => 'required|string|min:10|max:5000',
        ]);
        $inc = Incidencia::create($data + ['user_id' => $request->user()->id, 'estat' => 'oberta']);

        try {
            $desti = config('mail.reply_to.address');
            if ($desti) Mail::to($desti)->send(new IncidenciaMail($inc->load('user')));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['id' => $inc->id, 'missatge' => "Incidència enviada. RRHH se n'encarregarà."], 201);
    }

    public function index(Request $request)
    {
        $q = Incidencia::with(['user:id,name,email', 'resolutor:id,name'])->latest();
        if (in_array($request->query('estat'), ['oberta', 'resolta'], true)) $q->where('estat', $request->query('estat'));

        return response()->json($q->limit(500)->get());
    }

    public function resol(Request $request, Incidencia $incidencia)
    {
        $data = $request->validate(['nota' => 'nullable|string|max:2000']);
        $incidencia->update(['estat' => 'resolta', 'resolta_per' => $request->user()->id, 'resolta_at' => now(),
            'nota_resolucio' => $data['nota'] ?? null]);

        return response()->json($incidencia->fresh(['user:id,name,email', 'resolutor:id,name']));
    }

    public function reobre(Incidencia $incidencia)
    {
        $incidencia->update(['estat' => 'oberta', 'resolta_per' => null, 'resolta_at' => null]);

        return response()->json($incidencia->fresh(['user:id,name,email', 'resolutor:id,name']));
    }
}
