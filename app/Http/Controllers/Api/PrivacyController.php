<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ComplianceDocument;
use App\Models\DretsSollicitud;
use Illuminate\Http\Request;

/**
 * Pantalla de privadesa del treballador.
 *
 * Dues coses que abans no existien:
 *
 *  1. EL TEXT. La pantalla portava la informació escrita a mà dins del component (nom, correu,
 *     horari, hores, ubicació i IP) i havia quedat per darrere de la realitat: no deia res del
 *     seguiment del rendiment assistencial ni de la revisió automàtica del xat, que la política
 *     vigent sí que declara. Dues versions del mateix deure d'informació, i la que llegia la persona
 *     era la que es va quedar antiga. Ara la pantalla no té text propi: serveix el document vigent,
 *     de manera que informar bé passa a dependre de publicar la política, no de recordar-se de tocar
 *     un fitxer de Vue.
 *
 *  2. L'EXERCICI DE DRETS. Els botons deien «sol·licitud enviada» sense enviar ni desar res. Ara la
 *     sol·licitud es desa amb data i venciment de l'art. 12.3 RGPD i surt a la Safata de pendents
 *     fins que algú la contesta per escrit. No s'envia res cap enfora: la constància és interna i la
 *     resposta es dona per la mateixa via.
 */
class PrivacyController extends Controller
{
    /** Termini de resposta de l'art. 12.3 RGPD. */
    private const DIES_RESPOSTA = 30;

    /**
     * Text vigent del deure d'informació. És el document tipus `info_art90` publicat més recent:
     * un camp tipat, no una cerca pel títol, perquè un canvi de títol no ha de deixar la pantalla
     * sense text.
     */
    public function policy()
    {
        $doc = ComplianceDocument::where('tipus', 'info_art90')
            ->where('estat', 'publicat')
            ->orderByDesc('published_at')->orderByDesc('id')
            ->first(['id', 'titol', 'versio', 'contingut', 'base_legal', 'published_at']);

        return response()->json([
            'document' => $doc,
            'drets'    => DretsSollicitud::DRETS,
            // Si no hi ha document publicat, la pantalla ho ha de DIR, no omplir el buit amb text
            // propi: un avís de que falta la informació és millor que una informació inventada.
            'avis'     => $doc ? null : 'No hi ha cap versió publicada de la informació de protecció de dades. '
                . 'Poseu-vos en contacte amb el Delegat de Protecció de Dades.',
        ]);
    }

    /** Sol·licituds pròpies: la persona ha de poder veure què va demanar i si li han contestat. */
    public function myRequests(Request $request)
    {
        return DretsSollicitud::where('user_id', $request->user()->id)
            ->orderByDesc('presentada_ts')
            ->get();
    }

    public function storeRequest(Request $request)
    {
        $data = $request->validate([
            'dret'   => 'required|in:' . implode(',', array_keys(DretsSollicitud::DRETS)),
            'detall' => 'nullable|string|max:2000',
        ]);

        // Duplicat viu: la mateixa persona demanant el mateix dret amb una sol·licitud encara sense
        // resposta. No es crea una segona: reobrir el comptador del termini cada cop que algú torna
        // a clicar aniria en contra seva.
        $viva = DretsSollicitud::where('user_id', $request->user()->id)
            ->where('dret', $data['dret'])->whereNull('resposta_ts')->first();
        if ($viva) {
            return response()->json([
                'message'     => 'Ja teniu una sol·licitud d\'aquest dret pendent de resposta des del '
                    . $viva->presentada_ts->format('d/m/Y') . '. Es respondrà com a molt tard el '
                    . $viva->venciment->format('d/m/Y') . '.',
                'sollicitud'  => $viva,
            ], 200);
        }

        $ara = now();
        $s = DretsSollicitud::create([
            'user_id'       => $request->user()->id,
            'dret'          => $data['dret'],
            'detall'        => $data['detall'] ?? null,
            'presentada_ts' => $ara,
            'venciment'     => $ara->copy()->addDays(self::DIES_RESPOSTA)->toDateString(),
            'ip'            => $request->ip(),
        ]);

        $this->audita($request, 'DRETS_SOLLICITUD', $s->id,
            'Sol·licitud d\'exercici del dret de ' . DretsSollicitud::DRETS[$data['dret']]);

        return response()->json($s, 201);
    }

    // ── Gestió (admin/hr: qui dona suport al Delegat de Protecció de Dades) ───────────────
    public function index(Request $request)
    {
        $q = DretsSollicitud::with('user:id,name,email', 'responsable:id,name')
            ->orderByRaw('resposta_ts IS NOT NULL')
            ->orderBy('venciment');

        if ($request->boolean('pendents')) {
            $q->whereNull('resposta_ts');
        }

        return $q->limit(300)->get();
    }

    public function respond(Request $request, DretsSollicitud $sollicitud)
    {
        $data = $request->validate([
            'resposta' => 'required|string|min:10|max:5000',
        ], [
            'resposta.required' => 'Cal contestar per escrit: tancar la sol·licitud sense resposta la deixaria sense efecte.',
        ]);

        if ($sollicitud->resposta_ts) {
            return response()->json(['message' => 'Aquesta sol·licitud ja té resposta del '
                . $sollicitud->resposta_ts->format('d/m/Y') . '. Per rectificar-la, cal una sol·licitud nova.'], 422);
        }

        $sollicitud->forceFill([
            'resposta'    => $data['resposta'],
            'resposta_ts' => now(),
            'respost_per' => $request->user()->id,
        ])->save();

        $this->audita($request, 'DRETS_RESPOSTA', $sollicitud->id,
            'Resposta a la sol·licitud de ' . DretsSollicitud::DRETS[$sollicitud->dret]
            . " de l'usuari {$sollicitud->user_id}");

        return response()->json($sollicitud->fresh());
    }

    private function audita(Request $request, string $accio, ?int $id, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id'     => $request->user()?->id,
                'action'      => $accio,
                'entity_type' => 'drets_sollicituds',
                'entity_id'   => $id,
                'description' => mb_substr($descripcio, 0, 1000),
                'ip_address'  => $request->ip(),
                'user_agent'  => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
