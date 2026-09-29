<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\OnboardingProfile;
use App\Models\OnboardingStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OnboardingController extends Controller
{
    // ── Profiles ──

    public function indexProfiles()
    {
        return response()->json(OnboardingProfile::all());
    }

    public function showProfile(OnboardingProfile $profile)
    {
        return response()->json($profile);
    }

    public function storeProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_ids' => 'required|array',
            'document_ids.*' => 'integer|exists:documents,id',
            'doc_modes' => 'nullable|array',
            'doc_modes.*' => 'in:signature,view_only',
            'is_default' => 'boolean',
        ]);

        return response()->json(OnboardingProfile::create($data), 201);
    }

    public function updateProfile(Request $request, OnboardingProfile $profile)
    {
        $request->validate([
            'document_ids' => 'sometimes|array',
            'document_ids.*' => 'integer|exists:documents,id',
            'doc_modes' => 'nullable|array',
            'doc_modes.*' => 'in:signature,view_only',
        ]);

        $profile->update($request->only(['name', 'description', 'document_ids', 'doc_modes', 'is_default']));
        return response()->json($profile->fresh());
    }

    public function destroyProfile(OnboardingProfile $profile)
    {
        $profile->delete();
        return response()->json(null, 204);
    }

    // ── Status ──

    /**
     * GET /api/v1/onboarding/me
     *
     * L'alta és bloquejant: fins que no es completa, el treballador no veu res més.
     * Abans l'assistent muntava la pantalla amb tres crides reservades a gestió
     * (el perfil, l'arrencada de l'estat i cada document), de manera que un
     * treballador nou rebia 403 a totes tres i es quedava davant d'un document en
     * blanc que no es podia signar ni deixar enrere. Aquesta crida li dona, en una
     * sola resposta i sense obrir-li res més, exactament el que ha de llegir: el seu
     * perfil, el seu estat i NOMÉS els documents que el perfil li demana.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        $profile = $user->onboarding_profile_id
            ? OnboardingProfile::find($user->onboarding_profile_id)
            : OnboardingProfile::where('is_default', true)->first();

        // Sense perfil no hi ha res a signar: cal dir-ho, no deixar-lo en un assistent buit.
        if (! $profile) {
            return response()->json([
                'profile' => null, 'status' => null,
                'documents' => [], 'missing_documents' => [],
            ]);
        }

        $status = OnboardingStatus::where('user_id', $user->id)->first();
        if (! $status) {
            $status = $this->crearEstat($user->id, $profile);
        }

        $ids = array_values($profile->document_ids ?? []);
        $docs = Document::whereIn('id', $ids)->get()->keyBy('id');

        $documents = collect($ids)->filter(fn($id) => $docs->has($id))->map(function ($id) use ($docs, $profile) {
            $d = $docs->get($id);
            return [
                'id' => $d->id,
                'title' => $d->title,
                'description' => $d->description,
                'content' => $d->content,
                'file_name' => $d->file_name,
                // Un document en PDF s'ha de poder llegir: sense enllaç, la pantalla surt buida.
                'file_url' => $d->file_path ? Storage::url($d->file_path) : null,
                'file_mime' => $d->file_mime,
                'mode' => $profile->modeFor((int) $d->id),
            ];
        })->values();

        // Un document esborrat del catàleg deixava un pas mut i sense sortida.
        $missing = collect($ids)->reject(fn($id) => $docs->has($id))->values();

        return response()->json([
            'profile' => $profile->only(['id', 'name', 'description']),
            'status' => $status,
            'documents' => $documents,
            'missing_documents' => $missing,
        ]);
    }

    /** Els passos surten del perfil, en el seu ordre; l'estat és sempre del titular. */
    private function crearEstat(int $userId, OnboardingProfile $profile): OnboardingStatus
    {
        return OnboardingStatus::create([
            'user_id' => $userId,
            'profile_id' => $profile->id,
            'current_step' => 0,
            'completed' => false,
            'started_at' => now(),
            'steps' => collect($profile->document_ids ?? [])->map(fn($docId) => [
                'document_id' => $docId,
                'viewed_at' => null,
                'signed_at' => null,
            ])->values()->toArray(),
        ]);
    }

    public function status($userId)
    {
        return response()->json(
            OnboardingStatus::where('user_id', $userId)->with('profile')->first()
        );
    }

    /**
     * POST /api/v1/onboarding/init
     * Replicate db.js initOnboardingStatus
     */
    public function init(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'profile_id' => 'required|exists:onboarding_profiles,id',
        ]);

        $profile = OnboardingProfile::findOrFail($data['profile_id']);

        $freshStatus = DB::transaction(function () use ($data, $profile) {
            OnboardingStatus::where('user_id', $data['user_id'])->delete();

            $status = OnboardingStatus::create([
                'user_id' => $data['user_id'],
                'profile_id' => $data['profile_id'],
                'current_step' => 0,
                'completed' => false,
                'started_at' => now(),
                'steps' => collect($profile->document_ids ?? [])->map(fn($docId) => [
                    'document_id' => $docId,
                    'viewed_at' => null,
                    'signed_at' => null,
                ])->values()->toArray(),
            ]);

            return OnboardingStatus::with('profile')->find($status->id);
        });

        return response()->json($freshStatus, 201);
    }

    public function updateStatus(Request $request, OnboardingStatus $status)
    {
        $status->update($request->only(['current_step', 'completed', 'completed_at', 'steps']));
        return response()->json($status->fresh());
    }

    public function updateStatusByUser(Request $request, $userId)
    {
        $status = OnboardingStatus::where('user_id', $userId)->first();
        if (!$status) {
            $user = User::find($userId);
            $profile = $user && $user->onboarding_profile_id
                ? OnboardingProfile::find($user->onboarding_profile_id)
                : OnboardingProfile::where('is_default', true)->first();
            // Sense perfil no hi ha passos: abans això petava amb un 500 sec.
            if (! $profile) {
                return response()->json(['message' => "Aquest treballador no té cap perfil d'alta assignat"], 422);
            }
            $status = $this->crearEstat((int) $userId, $profile);
        }
        $status->update($request->only(['current_step', 'completed', 'completed_at', 'steps']));
        return response()->json($status->fresh());
    }

    /**
     * POST /api/v1/onboarding/complete/{userId}
     */
    public function complete($userId)
    {
        $status = OnboardingStatus::where('user_id', $userId)->first();
        if ($status) {
            $status->markCompleted();
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/onboarding/all-statuses
     */
    public function allStatuses()
    {
        return response()->json(
            OnboardingStatus::with(['user:id,name', 'profile:id,name'])->get()
        );
    }
}
