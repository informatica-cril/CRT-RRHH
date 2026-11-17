<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentSignature;
use App\Models\OnboardingProfile;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index()
    {
        return response()->json(Document::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'file_name' => 'nullable|string',
            'category' => 'string',
            'requires_signature' => 'boolean',
            'is_urgent' => 'boolean',
            'target_users' => 'nullable',
            'created_by' => 'nullable|exists:users,id',
            // Només PDF, màxim 10 MB. Sense això, s'acceptava qualsevol tipus/mida.
            'file' => 'nullable|file|mimetypes:application/pdf|max:10240',
        ]);

        // Handle PDF file upload
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('documents', 'public');
            $data['file_path'] = $path;
            $data['file_mime'] = $file->getMimeType();
            $data['file_name'] = $data['file_name'] ?? $file->getClientOriginalName();
        }
        unset($data['file']); // no és columna del model

        return response()->json(Document::create($data), 201);
    }

    public function show(Document $document)
    {
        return response()->json($document);
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'nullable|string',
            'file_name' => 'nullable|string',
            'category' => 'sometimes|string',
            'requires_signature' => 'sometimes|boolean',
            'is_urgent' => 'sometimes|boolean',
            'ocult' => 'sometimes|boolean',
            'target_users' => 'nullable',
        ]);
        // Un document urgent bloqueja l'accés fins que es firma: sense firma no té sentit.
        if (! empty($data['is_urgent'])) $data['requires_signature'] = true;
        // Si es treu la firma, deixa també de ser urgent.
        if (array_key_exists('requires_signature', $data) && ! $data['requires_signature']) $data['is_urgent'] = false;
        $document->update($data);

        return response()->json($document->fresh());
    }

    public function destroy(Document $document)
    {
        // Les firmes són constància legal i s'esborrarien en cascada amb el document: si n'hi ha, no es pot esborrar.
        if ($document->signatures()->exists()) {
            return response()->json(['message' => 'Aquest document ja té firmes o lectures registrades i no es pot esborrar. Si ja no s\'ha de mostrar als treballadors, oculta\'l.'], 422);
        }
        // Els perfils d'onboarding el guarden per id: se'l treu per no deixar-hi un forat.
        foreach (OnboardingProfile::all() as $p) {
            $ids = array_values(array_filter($p->document_ids ?? [], fn ($i) => (int) $i !== $document->id));
            if (count($ids) !== count($p->document_ids ?? [])) {
                $modes = $p->doc_modes ?? [];
                unset($modes[$document->id]);
                $p->update(['document_ids' => $ids, 'doc_modes' => $modes]);
            }
        }
        $document->delete();

        return response()->json(null, 204);
    }

    /**
     * GET /api/v1/documents/user/{userId}
     * Documents targeting a specific user
     *
     * Optimizado: filtra en la BD en lugar de cargar todos los documentos en memoria.
     * Un documento "afecta" a un usuario cuando target_users es 'all'/null/vacío
     * o cuando el array JSON contiene el userId.
     */
    public function forUser($userId)
    {
        $userId = (int) $userId;

        $docs = Document::where('ocult', false)->where(function ($q) use ($userId) {
            // Documentos dirigidos a "todos" (sin target_users específico)
            $q->whereNull('target_users')
              ->orWhere('target_users', '')
              ->orWhere('target_users', 'all')
              ->orWhere('target_users', 'null')
              // Documentos dirigidos a un conjunto que incluye al usuario
              ->orWhereJsonContains('target_users', $userId);
        })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($docs);
    }

    /**
     * GET /api/v1/documents/{id}/pending-signatures
     * Replicate db.js getPendingSignatures
     */
    public function pendingSignatures(Document $document)
    {
        $workers = User::workers()->active()->get();
        $targetWorkers = $workers->filter(fn($w) => $document->targetsUser($w->id));

        $signatures = DocumentSignature::where('document_id', $document->id)->get();

        $result = $targetWorkers->map(function ($w) use ($signatures) {
            $sig = $signatures->firstWhere('user_id', $w->id);
            return [
                'user_id' => $w->id,
                'name' => $w->name,
                'email' => $w->email,
                'viewed_at' => $sig?->viewed_at,
                'signed_at' => $sig?->signed_at,
                'signature_hash' => $sig?->signature_hash,
            ];
        })->values();

        return response()->json($result);
    }
}
