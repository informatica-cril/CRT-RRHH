<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Http\Request;

/**
 * Pràctiques: hores del conveni i quantes en queden.
 *
 * Les hores fetes són la suma dels fitxatges APROVATS dins del període del conveni: quan RRHH valida
 * un fitxatge, es resta sol. Els pendents es donen a part perquè la persona sàpiga què li falta validar.
 */
class PractiquesController extends Controller
{
    /** GET /v1/practiques/{user}: la mateixa persona o el personal de gestió. */
    public function show(Request $request, User $user)
    {
        $jo = $request->user();
        abort_unless($jo->id === $user->id || in_array($jo->role, ['admin', 'hr', 'coordinator'], true), 403);

        return response()->json($this->estat($user));
    }

    /** PUT /v1/practiques/{user}: només admin i RRHH, que són qui signa el conveni amb el centre. */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'practiques'        => 'required|boolean',
            'practiques_hores'  => 'nullable|required_if:practiques,true|numeric|min:1|max:3000',
            'practiques_inici'  => 'nullable|required_if:practiques,true|date',
            'practiques_fi'     => 'nullable|date|after_or_equal:practiques_inici',
            'practiques_centre' => 'nullable|string|max:150',
        ], [
            'practiques_hores.required_if' => 'Cal indicar les hores del conveni de pràctiques.',
            'practiques_inici.required_if' => "Cal indicar quan comencen les pràctiques: les hores es compten des d'aquest dia.",
            'practiques_fi.after_or_equal' => "La data de fi no pot ser anterior a la d'inici.",
        ]);

        if (! $data['practiques']) {
            $data = ['practiques' => false, 'practiques_hores' => null, 'practiques_inici' => null,
                'practiques_fi' => null, 'practiques_centre' => null];
        }
        $user->forceFill($data)->save();

        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'UPDATE_USER', 'entity_type' => 'user', 'entity_id' => $user->id,
                'description' => $data['practiques']
                    ? "Pràctiques: {$data['practiques_hores']} h des del {$data['practiques_inici']}"
                    : 'Pràctiques desactivades',
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json($this->estat($user->fresh()));
    }

    private function estat(User $user): array
    {
        $base = [
            'practiques' => (bool) $user->practiques,
            'hores_conveni' => $user->practiques_hores !== null ? (float) $user->practiques_hores : null,
            'inici' => $user->practiques_inici ? substr((string) $user->practiques_inici, 0, 10) : null,
            'fi' => $user->practiques_fi ? substr((string) $user->practiques_fi, 0, 10) : null,
            'centre' => $user->practiques_centre,
        ];
        if (! $user->practiques || ! $user->practiques_inici) {
            return $base + ['fetes' => 0, 'pendents_validar' => 0, 'restants' => $base['hores_conveni'], 'percentatge' => 0];
        }

        $q = WorkLog::where('user_id', $user->id)->whereNotNull('end_time')
            ->where('date', '>=', $base['inici'])
            ->when($base['fi'], fn ($q, $fi) => $q->where('date', '<=', $fi));
        $hores = fn ($estat) => round((float) (clone $q)->where('status', $estat)
            ->sum(\DB::raw('COALESCE(effective_hours, total_hours_worked, 0)')), 2);

        $fetes = $hores('approved');
        $conveni = (float) $user->practiques_hores;

        return $base + [
            'fetes' => $fetes,
            'pendents_validar' => $hores('pending'),
            'restants' => round(max(0, $conveni - $fetes), 2),
            'percentatge' => $conveni > 0 ? min(100, round($fetes / $conveni * 100)) : 0,
        ];
    }
}
