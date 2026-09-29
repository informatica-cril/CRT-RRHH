<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceAcknowledgement;
use App\Models\ComplianceDocument;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Compliment / Governança. Gestió (admin/hr): crear, editar esborrany, publicar (immutable),
 * arxivar i veure l'estat d'acusaments. Treballador: consultar pendents i acusar-ne recepció.
 * Cap edició d'un document ja publicat: una versió nova és un document nou (traçabilitat).
 */
class ComplianceController extends Controller
{
    // ── Gestió (admin/hr) ─────────────────────────────────────────────────────
    public function index()
    {
        // Desempat per id: diverses versions d'un mateix document poden néixer al mateix segon
        // (una migració que en publica una de nova i n'arxiva l'anterior ho fa sempre). Sense
        // desempat, l'ordre entre elles el decideix el motor de la BD i la pantalla pot encapçalar
        // la llista amb la versió ARXIVADA en comptes de la vigent.
        return ComplianceDocument::withCount('acknowledgements')
            ->orderByDesc('created_at')->orderByDesc('id')->get();
    }

    public function show(ComplianceDocument $document)
    {
        $document->load(['acknowledgements.user:id,name,email', 'publisher:id,name']);
        return $document;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipus'          => 'required|in:politica_algoritmica,info_art90,ropa,eipd,altre',
            'titol'          => 'required|string|max:255',
            'versio'         => 'nullable|string|max:20',
            'contingut'      => 'required|string',
            'base_legal'     => 'nullable|string|max:255',
            'requereix_acus' => 'boolean',
        ]);
        $data['versio'] = $data['versio'] ?? '1.0';
        $data['estat']  = 'esborrany';

        return response()->json(ComplianceDocument::create($data), 201);
    }

    public function update(Request $request, ComplianceDocument $document)
    {
        if ($document->estat !== 'esborrany') {
            return response()->json(['message' => 'Només es pot editar un esborrany. Publica una versió nova.'], 422);
        }
        $data = $request->validate([
            'titol'          => 'sometimes|string|max:255',
            'versio'         => 'sometimes|string|max:20',
            'contingut'      => 'sometimes|string',
            'base_legal'     => 'nullable|string|max:255',
            'requereix_acus' => 'boolean',
        ]);
        $document->update($data);

        return $document;
    }

    public function publish(Request $request, ComplianceDocument $document)
    {
        if ($document->estat === 'arxivat') {
            return response()->json(['message' => 'Document arxivat: no es pot publicar.'], 422);
        }
        // La publicació és un acte únic: `published_at` és el que acredita que la informació va ser
        // PRÈVIA. Republicar la mouria endavant i podria deixar-la per darrere d'acusaments ja donats
        // (LOPDGDD art. 90). Per canviar el text cal una versió nova, no reobrir aquesta.
        if ($document->estat === 'publicat' && $document->published_at) {
            return response()->json([
                'message' => 'Document ja publicat el ' . $document->published_at->format('d/m/Y H:i')
                    . ': la data de publicació no es mou. Publiqueu una versió nova si cal.',
            ], 422);
        }
        $document->update([
            'estat'        => 'publicat',
            'published_at' => now(),
            'published_by' => $request->user()->id,
        ]);

        return $document;
    }

    public function archive(ComplianceDocument $document)
    {
        $document->update(['estat' => 'arxivat']);

        return $document;
    }

    /**
     * Comptes que NO són persones i, per tant, no acusen res: el compte de servei amb què domi
     * consumeix l'API de RRHH. Qualsevol rol de persona (worker, coordinator, hr, admin) SÍ entra
     * al seguiment.
     */
    private const ROLS_NO_PERSONA = ['service'];

    /**
     * Qui ha acusat i qui no (panell de compliment).
     *
     * ⚠️ CANVI DE COBERTURA: fins ara aquesta llista era `where('role','worker')`, o sigui que el
     * compliment de coordinadors, `hr` i admin era INVISIBLE — tot i que aquestes persones sí que
     * reben el document a /compliance/pending i el poden acusar. Si Direcció ha d'acreditar davant
     * d'un tercer que TOTA la plantilla ha rebut la informació (LOPDGDD art. 90), amb mitja
     * plantilla fora de la llista no ho pot fer. Ara hi surt tothom, amb el seu rol.
     *
     * Els comptes desactivats (baixes) hi segueixen sortint, com abans, però amb `active` a la
     * resposta: un acusament que mai es va donar no s'esborra de la vista, es marca.
     */
    public function acknowledgementsStatus(ComplianceDocument $document)
    {
        $ackByUser = $document->acknowledgements()->pluck('acknowledged_at', 'user_id');
        $persones  = User::whereNotIn('role', self::ROLS_NO_PERSONA)
            ->orderBy('name')->get(['id', 'name', 'email', 'role', 'active']);

        return $persones->map(fn ($u) => [
            'user_id'         => $u->id,
            'name'            => $u->name,
            'email'           => $u->email,
            'role'            => $u->role,
            'active'          => (bool) $u->active,
            'acknowledged_at' => $ackByUser[$u->id] ?? null,
        ])->values();
    }

    // ── Treballador ───────────────────────────────────────────────────────────
    public function pending(Request $request)
    {
        $uid   = $request->user()->id;
        $acked = ComplianceAcknowledgement::where('user_id', $uid)->pluck('compliance_document_id');

        return ComplianceDocument::where('estat', 'publicat')
            ->where('requereix_acus', true)
            ->whereNotIn('id', $acked)
            ->orderBy('published_at')
            ->get();
    }

    public function acknowledge(Request $request, ComplianceDocument $document)
    {
        if ($document->estat !== 'publicat' || ! $document->requereix_acus) {
            return response()->json(['message' => 'Aquest document no requereix acusament.'], 422);
        }
        $ack = ComplianceAcknowledgement::firstOrCreate(
            ['compliance_document_id' => $document->id, 'user_id' => $request->user()->id],
            ['document_versio' => $document->versio, 'acknowledged_at' => now(), 'ip' => $request->ip()]
        );

        return response()->json($ack, 201);
    }
}
