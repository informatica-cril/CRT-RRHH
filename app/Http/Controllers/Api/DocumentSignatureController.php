<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentSignature;
use Illuminate\Http\Request;

class DocumentSignatureController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesOwnership;

    public function store(Request $request)
    {
        $data = $request->validate([
            'document_id' => 'required|exists:documents,id',
            'user_id' => 'required|exists:users,id',
            'viewed_at' => 'nullable|date',
            'signed_at' => 'nullable|date',
            'document_hash' => 'nullable|string',
            'signature_hash' => 'nullable|string',
            'timestamp_source' => 'nullable|string',
            'timestamp_token' => 'nullable|string',
            'signing_payload' => 'nullable|string',
            'legal_basis' => 'nullable|string',
            'ip_address' => 'nullable|string',
            'user_agent' => 'nullable|string',
        ]);

        // AUTORITZACIÓ: la firma és un acte personal — el signant és el propi usuari
        // (o gestió actuant en nom seu). No es pot firmar per un tercer.
        $this->ensureOwnerOrStaff($request, $data['user_id']);

        // Reforzar validez probatoria: la evidencia se captura EN EL SERVIDOR,
        // no se confía en lo que envía el cliente (IP falsificable, reloj manipulable).
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = substr((string) $request->userAgent(), 0, 500);
        if (!empty($data['signed_at'])) {
            // Sello temporal autoritativo del servidor en el momento de la firma.
            $data['signed_at'] = now();
        }

        $sig = DocumentSignature::updateOrCreate(
            ['document_id' => $data['document_id'], 'user_id' => $data['user_id']],
            $data
        );

        return response()->json($sig, 201);
    }

    public function byUser($userId)
    {
        return response()->json(
            DocumentSignature::where('user_id', $userId)
                ->with('document:id,title,category')
                ->get()
        );
    }

    public function update(Request $request, DocumentSignature $signature)
    {
        $this->ensureOwnerOrStaff($request, $signature->user_id);
        $data = $request->only([
            'viewed_at', 'signed_at', 'document_hash', 'signature_hash',
            'timestamp_source', 'timestamp_token', 'signing_payload',
            'legal_basis', 'ip_address', 'user_agent',
        ]);

        // Evidencia capturada en el servidor (ver store()).
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = substr((string) $request->userAgent(), 0, 500);
        if (!empty($data['signed_at'])) {
            $data['signed_at'] = now();
        }

        $signature->update($data);

        return response()->json($signature->fresh());
    }

    /**
     * GET /api/v1/document-signatures/urgent-unsigned/{userId}
     * Replicate db.js getUnsignedUrgentDocs
     */
    public function urgentUnsigned($userId)
    {
        $user = \App\Models\User::find($userId);
        if (!$user || $user->job_profile === 'Gerencia' || $user->role === 'admin') {
            return response()->json([]);
        }

        $urgentDocs = Document::urgentRequiringSignature()->get()
            ->filter(fn($d) => $d->targetsUser((int) $userId));

        $signedIds = DocumentSignature::where('user_id', $userId)
            ->whereNotNull('signed_at')
            ->pluck('document_id')
            ->toArray();

        $unsigned = $urgentDocs->filter(fn($d) => !in_array($d->id, $signedIds))->values();

        return response()->json($unsigned);
    }
}
