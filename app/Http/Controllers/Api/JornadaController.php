<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JornadaContracte;
use App\Models\User;
use App\Support\ModulJornada;
use Illuminate\Http\Request;

/**
 * % de jornada del contracte (pestanya Jornada de la fitxa). Els càlculs són al servidor
 * perquè la pantalla no torni a escriure els números del conveni.
 */
class JornadaController extends Controller
{
    private const MISSATGES = [
        'pct.*' => 'El percentatge de jornada ha de ser entre 1 i 100.',
        'any.*' => "L'any no és vàlid.",
        'valid_from.required' => "Cal la data d'efecte del canvi.",
        'valid_from.date' => "La data d'efecte no és vàlida.",
        'motiu.max' => 'El motiu no pot tenir més de :max caràcters.',
    ];

    /** GET /api/v1/jornada/modul?pct=75&any=2026 — màxim anual, mòdul diari i resta d'un %. */
    public function modul(Request $request)
    {
        $data = $request->validate([
            'pct' => 'required|numeric|min:1|max:100',
            'any' => 'nullable|integer|min:2023|max:2100',
        ], self::MISSATGES);

        return response()->json(ModulJornada::calcula((float) $data['pct'], (int) ($data['any'] ?? now('Europe/Madrid')->year)));
    }

    /** GET /api/v1/users/{user}/jornada?any=2026 — historial, trams de l'any i saldo mensual. */
    public function show(Request $request, User $user)
    {
        $data = $request->validate(['any' => 'nullable|integer|min:2023|max:2100'], self::MISSATGES);
        $any = (int) ($data['any'] ?? now('Europe/Madrid')->year);

        $r = ModulJornada::perUsuari($user, $any);
        $r['modul'] = $r['vigent'] ? ModulJornada::calcula($r['vigent']['pct'], $any) : null;

        return response()->json($r);
    }

    /**
     * POST /api/v1/users/{user}/jornada — un canvi de % amb data d'efecte (ampliació o reducció).
     * No s'esborra mai l'anterior: l'any es calcula a trams. Si ja hi ha un canvi el mateix
     * dia, se substitueix (és la correcció d'un error, no un canvi nou).
     */
    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'pct' => 'required|numeric|min:1|max:100',
            'valid_from' => 'required|date',
            'motiu' => 'nullable|string|max:255',
        ], self::MISSATGES);

        $dia = \Carbon\Carbon::parse($data['valid_from'])->toDateString();
        $canvi = JornadaContracte::where('user_id', $user->id)->whereDate('valid_from', $dia)->first()
            ?? new JornadaContracte(['user_id' => $user->id, 'valid_from' => $dia]);
        $canvi->fill(['pct' => round((float) $data['pct'], 2), 'motiu' => $data['motiu'] ?? null,
            'created_by' => $request->user()->id])->save();

        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id, 'action' => 'JORNADA_CONTRACTE', 'entity_type' => 'user',
            'entity_id' => $user->id,
            'description' => "Jornada del {$canvi->pct}% des del {$canvi->valid_from->toDateString()}" . ($canvi->motiu ? " ({$canvi->motiu})" : ''),
        ]);

        return response()->json($canvi, 201);
    }

    /** DELETE /api/v1/users/{user}/jornada/{canvi} — treure un canvi entrat per error. */
    public function destroy(Request $request, User $user, JornadaContracte $canvi)
    {
        abort_unless((int) $canvi->user_id === (int) $user->id, 404);

        \App\Models\AuditLog::create([
            'user_id' => $request->user()->id, 'action' => 'JORNADA_CONTRACTE_ESBORRA', 'entity_type' => 'user',
            'entity_id' => $user->id,
            'description' => "Esborrat el canvi al {$canvi->pct}% des del {$canvi->valid_from->toDateString()}",
        ]);
        $canvi->delete();

        return response()->json(['ok' => true]);
    }
}
