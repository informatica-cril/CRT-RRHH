<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Http\Request;

/**
 * Pràctiques: el conveni (segons la modalitat), les hores pactades i quantes en queden.
 *
 * Les hores fetes són la suma dels fitxatges APROVATS dins del període del conveni: quan RRHH valida
 * un fitxatge, es resta sol. Els pendents es donen a part perquè la persona sàpiga què li falta validar.
 *
 * Cada modalitat (FP, universitat, no laborals…) té les seves dades i els seus avisos. El catàleg viu
 * aquí i la pantalla el rep tal qual: les regles d'una modalitat no s'han d'escriure en dos llocs.
 * Els avisos són recordatoris per a qui omple la fitxa, no validacions: el que mana és el conveni signat.
 */
class PractiquesController extends Controller
{
    /** Hores per crèdit ECTS que es proposen per defecte (RD 1125/2003: entre 25 i 30). */
    private const HORES_ECTS = 25;

    private const TIPUS = [
        'fp_mitja' => [
            'nom' => 'FP · Grau mitjà (formació en empresa)',
            'camps' => ['estudis', 'curs', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'alta_ss'],
            'estudis' => 'Cicle formatiu',
            'avisos' => ["Amb la Llei 3/2022 d'FP, la formació en empresa és com a mínim el 25% de les hores del cicle: les hores exactes són les del conveni amb el centre."],
        ],
        'fp_superior' => [
            'nom' => 'FP · Grau superior (formació en empresa)',
            'camps' => ['estudis', 'curs', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'alta_ss'],
            'estudis' => 'Cicle formatiu',
            'avisos' => ["Amb la Llei 3/2022 d'FP, la formació en empresa és com a mínim el 25% de les hores del cicle: les hores exactes són les del conveni amb el centre."],
        ],
        'fp_dual' => [
            'nom' => 'FP dual / en alternança',
            'camps' => ['estudis', 'curs', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'remunerada', 'beca_mensual', 'alta_ss'],
            'estudis' => 'Cicle formatiu',
            'avisos' => ["Si és FP intensiva amb contracte de formació en alternança, és una relació LABORAL: s'ha de tractar com a contracte, no com a pràctiques."],
        ],
        'uni_curriculars' => [
            'nom' => 'Universitat · Pràctiques curriculars',
            'camps' => ['estudis', 'curs', 'ects', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'remunerada', 'beca_mensual', 'alta_ss'],
            'estudis' => 'Grau',
            'ects' => true,
            'avisos' => ["Formen part del pla d'estudis: les hores surten dels crèdits ECTS de l'assignatura de pràctiques."],
        ],
        'uni_extracurriculars' => [
            'nom' => 'Universitat · Pràctiques extracurriculars',
            'camps' => ['estudis', 'curs', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'remunerada', 'beca_mensual', 'alta_ss'],
            'estudis' => 'Grau',
            'avisos' => ['RD 592/2014: preferentment no més del 50% del curs acadèmic.'],
        ],
        'master' => [
            'nom' => 'Màster · Pràctiques',
            'camps' => ['estudis', 'ects', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'remunerada', 'beca_mensual', 'alta_ss'],
            'estudis' => 'Màster',
            'ects' => true,
            'avisos' => [],
        ],
        'no_laborals' => [
            'nom' => 'Pràctiques no laborals (RD 1543/2011)',
            'camps' => ['estudis', 'tutor_empresa', 'num_conveni', 'remunerada', 'beca_mensual', 'alta_ss'],
            'estudis' => 'Titulació',
            'beca_obligatoria' => true,
            'avisos' => ['Per a joves titulats sense experiència: durada de 3 a 9 mesos i beca obligatòria d\'almenys el 80% de l\'IPREM mensual.'],
        ],
        'altres' => [
            'nom' => 'Altres',
            'camps' => ['estudis', 'tutor_centre_nom', 'tutor_centre_email', 'tutor_empresa', 'num_conveni', 'remunerada', 'beca_mensual', 'alta_ss', 'observacions'],
            'estudis' => 'Estudis',
            'avisos' => [],
        ],
    ];

    /** Comú a totes: des de 2024 també cotitzen les pràctiques no remunerades (DA 52a LGSS). */
    private const AVIS_SS = "Cal donar d'alta l'alumne/a a la Seguretat Social abans que comenci, també si les pràctiques no són remunerades.";

    /** GET /v1/practiques/{user}: la mateixa persona o el personal de gestió. */
    public function show(Request $request, User $user)
    {
        $jo = $request->user();
        abort_unless($jo->id === $user->id || in_array($jo->role, ['admin', 'hr', 'coordinator'], true), 403);

        return response()->json($this->estat($user) + [
            'cataleg' => self::TIPUS, 'avis_ss' => self::AVIS_SS, 'hores_ects' => self::HORES_ECTS,
        ]);
    }

    /** PUT /v1/practiques/{user}: només admin i RRHH, que són qui signa el conveni amb el centre. */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'practiques'        => 'required|boolean',
            'practiques_tipus'  => 'nullable|required_if:practiques,true|in:' . implode(',', array_keys(self::TIPUS)),
            'practiques_hores'  => 'nullable|required_if:practiques,true|numeric|min:1|max:3000',
            'practiques_inici'  => 'nullable|required_if:practiques,true|date',
            'practiques_fi'     => 'nullable|date|after_or_equal:practiques_inici',
            'practiques_centre' => 'nullable|string|max:150',
            'detall'                    => 'nullable|array',
            'detall.estudis'            => 'nullable|string|max:150',
            'detall.curs'               => 'nullable|string|max:30',
            'detall.ects'               => 'nullable|numeric|min:0|max:120',
            'detall.tutor_centre_nom'   => 'nullable|string|max:120',
            'detall.tutor_centre_email' => 'nullable|email|max:150',
            'detall.tutor_empresa'      => 'nullable|string|max:120',
            'detall.num_conveni'        => 'nullable|string|max:60',
            'detall.remunerada'         => 'nullable|boolean',
            'detall.beca_mensual'       => 'nullable|numeric|min:0|max:10000',
            'detall.alta_ss'            => 'nullable|date',
            'detall.observacions'       => 'nullable|string|max:1000',
        ], [
            'practiques_tipus.required_if' => 'Cal triar el tipus de conveni de pràctiques.',
            'practiques_hores.required_if' => 'Cal indicar les hores del conveni de pràctiques.',
            'practiques_inici.required_if' => "Cal indicar quan comencen les pràctiques: les hores es compten des d'aquest dia.",
            'practiques_fi.after_or_equal' => "La data de fi no pot ser anterior a la d'inici.",
            'detall.tutor_centre_email.email' => 'El correu del tutor del centre no és vàlid.',
        ]);

        if (! $data['practiques']) {
            $desa = ['practiques' => false, 'practiques_tipus' => null, 'practiques_hores' => null, 'practiques_inici' => null,
                'practiques_fi' => null, 'practiques_centre' => null, 'practiques_detall' => null];
        } else {
            $tipus = self::TIPUS[$data['practiques_tipus']];
            if (! empty($tipus['beca_obligatoria']) && empty($data['detall']['beca_mensual'])) {
                return response()->json(['message' => "En aquesta modalitat la beca és obligatòria: cal indicar l'import mensual."], 422);
            }
            // Només es desen les dades que té la modalitat: canviar de tipus no deixa restes de l'anterior.
            $detall = array_intersect_key($data['detall'] ?? [], array_flip($tipus['camps']));
            $desa = [
                'practiques' => true, 'practiques_tipus' => $data['practiques_tipus'], 'practiques_hores' => $data['practiques_hores'],
                'practiques_inici' => $data['practiques_inici'], 'practiques_fi' => $data['practiques_fi'] ?? null,
                'practiques_centre' => $data['practiques_centre'] ?? null,
                'practiques_detall' => $detall ? json_encode($detall, JSON_UNESCAPED_UNICODE) : null,
            ];
        }
        $user->forceFill($desa)->save();

        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'UPDATE_USER', 'entity_type' => 'user', 'entity_id' => $user->id,
                'description' => $desa['practiques']
                    ? 'Pràctiques (' . self::TIPUS[$desa['practiques_tipus']]['nom'] . "): {$desa['practiques_hores']} h des del {$desa['practiques_inici']}"
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
        $detall = $user->practiques_detall;
        if (is_string($detall)) {
            $detall = json_decode($detall, true);
        }
        $tipus = $user->practiques_tipus;
        $base = [
            'practiques' => (bool) $user->practiques,
            'tipus' => $tipus,
            'tipus_nom' => $tipus ? (self::TIPUS[$tipus]['nom'] ?? $tipus) : null,
            'hores_conveni' => $user->practiques_hores !== null ? (float) $user->practiques_hores : null,
            'inici' => $user->practiques_inici ? substr((string) $user->practiques_inici, 0, 10) : null,
            'fi' => $user->practiques_fi ? substr((string) $user->practiques_fi, 0, 10) : null,
            'centre' => $user->practiques_centre,
            'detall' => $detall ?: (object) [],
        ];
        if (! $user->practiques || ! $user->practiques_inici) {
            return $base + ['fetes' => 0, 'pendents_validar' => 0, 'restants' => $base['hores_conveni'], 'percentatge' => 0, 'excedides' => 0];
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
            // Hores per sobre del conveni: el conveni no les cobreix i cal revisar-ho.
            'excedides' => round(max(0, $fetes - $conveni), 2),
        ];
    }
}
