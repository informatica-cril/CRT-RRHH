<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinaryElement;
use App\Models\RendimentAllegacio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    /**
     * CATÀLEG DELS INDICADORS. És l'única font de veritat del que se li ensenya a la persona:
     * què mesura cada xifra, d'on surt la dada i què NO diu. Sense això, «compliment 82%» és una
     * xifra sense contingut i el dret a rebatre-la és nominal (art. 20.3 ET amb informació prèvia;
     * art. 15 RGPD; art. 64.4.d ET pel que fa als paràmetres).
     *
     * El càlcul viu a la Domiciliària (includes/rendiment.php); aquí només se'n descriu el
     * resultat. Si allà canvia una fórmula, aquest text s'ha d'actualitzar amb ella.
     */
    public const INDICADORS = [
        'sessions_firmades' => [
            'etiqueta' => 'Sessions de rehabilitació signades',
            'unitat' => 'recompte',
            'mesura' => 'Sessions que el pacient ha signat a la tauleta durant el període.',
            'origen' => 'CRT Domiciliària · registre de signatura de sessió.',
        ],
        'compliment_pct' => [
            'etiqueta' => 'Compliment de la programació',
            'unitat' => '%',
            'mesura' => 'Sessions signades sobre sessions programades al període. Per damunt del 100% vol dir '
                . 'que s\'han fet sessions no programades (reompliments, urgències), cosa que no és negativa.',
            'origen' => 'CRT Domiciliària · agenda planificada i signatures de sessió.',
        ],
        'documental_pct' => [
            'etiqueta' => 'Rigor documental',
            'unitat' => '%',
            'mesura' => 'Sessions signades sobre el total de visites iniciades (signades més iniciades sense '
                . 'signatura). Mesura visites que han quedat sense signar, no la qualitat de l\'atenció.',
            'origen' => 'CRT Domiciliària · marcatge d\'entrada a la visita i signatures de sessió.',
        ],
        'altes_sense_informe' => [
            'etiqueta' => 'Altes sense informe',
            'unitat' => 'recompte',
            'mesura' => 'Processos donats d\'alta que no tenen cap informe d\'alta vigent.',
            'origen' => 'CRT Domiciliària · derivacions en estat d\'alta i informes d\'alta.',
        ],
        'processos_tancats' => [
            'etiqueta' => 'Processos tancats',
            'unitat' => 'recompte',
            'mesura' => 'Processos assistencials amb data de fi de tractament dins del període.',
            'origen' => 'CRT Domiciliària · derivacions.',
        ],
        'adherencia_pct' => [
            'etiqueta' => 'Adherència al protocol',
            'unitat' => '%',
            'mesura' => 'Sessions realment fetes sobre les pautades pel protocol de la patologia, en els '
                . 'processos tancats al període. Una desviació pot ser la decisió clínica correcta.',
            'origen' => 'CRT Domiciliària · protocol per patologia i signatures de sessió.',
        ],
        'processos_sota_70pct' => [
            'etiqueta' => 'Processos sota el 70%',
            'unitat' => 'recompte',
            'mesura' => 'Processos tancats amb menys del 70% de les sessions pautades pel protocol.',
            'origen' => 'CRT Domiciliària · protocol per patologia i signatures de sessió.',
        ],
        'puntualitat_pct' => [
            'etiqueta' => 'Puntualitat',
            'unitat' => '%',
            'mesura' => 'Visites iniciades amb 15 minuts o menys de diferència respecte de l\'hora planificada, '
                . 'sobre el total de visites que tenien hora planificada.',
            'origen' => 'CRT Domiciliària · marcatge d\'entrada a la visita i agenda planificada.',
        ],
        'retard_mitja_min' => [
            'etiqueta' => 'Retard mitjà',
            'unitat' => 'minuts',
            'mesura' => 'Mitjana de minuts entre l\'hora planificada i l\'entrada marcada. Els avançaments '
                . 'compten com a zero, no compensen els retards.',
            'origen' => 'CRT Domiciliària · marcatge d\'entrada a la visita i agenda planificada.',
        ],
        'veu_pacient' => [
            'etiqueta' => 'Veu del pacient',
            'unitat' => 'recompte',
            'mesura' => 'Aportacions que els pacients atesos han deixat al canal del portal del pacient, '
                . 'separades en agraïments i queixes. Per sota de 5 aportacions al període no es desglossa, '
                . 'perquè una sola aportació diria més del pacient que del professional.',
            'origen' => 'CRT Domiciliària · canal d\'aportacions del portal del pacient.',
        ],
        'elements' => [
            'etiqueta' => 'Fets elevats a Recursos Humans',
            'unitat' => 'recompte',
            'mesura' => 'Fets que algú ha elevat des de la Domiciliària a la safata de Recursos Humans amb la '
                . 'seva motivació. Un fet elevat no és una sanció ni obre cap expedient: espera que Recursos '
                . 'Humans decideixi si escau instruir res.',
            'origen' => 'CRT RRHH · safata de procediment.',
        ],
    ];

    public function index(Request $request)
    {
        $periode = DB::table('domi_rendiment')
            ->select('periode_desde', 'periode_fins')
            ->orderByDesc('periode_fins')
            ->first();

        if (! $periode) {
            return response()->json(['periode' => null, 'files' => [], 'mitjana' => null,
                'avis' => 'Encara no ha arribat cap tancament de la Domiciliària.']);
        }

        $desde = $request->input('desde', $periode->periode_desde);
        $fins = $request->input('fins', $periode->periode_fins);

        $files = DB::table('domi_rendiment as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->where('r.periode_desde', $desde)
            ->where('r.periode_fins', $fins)
            ->orderBy('u.name')
            ->get([
                'r.user_id', 'u.name', 'u.dni', 'r.sessions_firmades', 'r.compliment_pct',
                'r.documental_pct', 'r.altes_sense_informe', 'r.processos_tancats', 'r.adherencia_pct',
                'r.processos_sota_70pct', 'r.puntualitat_pct', 'r.retard_mitja_min', 'r.aportacions',
                'r.prou_mostra', 'r.agraiments', 'r.queixes', 'r.updated_at',
            ]);

        $elements = DisciplinaryElement::whereIn('user_id', $files->pluck('user_id'))
            ->selectRaw('user_id, COUNT(*) n, SUM(estat = "inclos_en_cas") en_cas')
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $files = $files->map(function ($f) use ($elements) {
            $e = $elements->get($f->user_id);
            $f->elements = $e ? (int) $e->n : 0;
            $f->elements_en_cas = $e ? (int) $e->en_cas : 0;

            return $f;
        });

        $mitjana = DB::table('domi_rendiment_mitjana')
            ->where('periode_desde', $desde)->where('periode_fins', $fins)->first();

        $periodes = DB::table('domi_rendiment')
            ->select('periode_desde', 'periode_fins')
            ->distinct()->orderByDesc('periode_fins')->limit(24)->get();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'RENDIMENT_CONSULTA',
            'entity_type' => 'domi_rendiment',
            'description' => "Consulta del rendiment del periode {$desde} a {$fins} ({$files->count()} persones)",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json([
            'periode' => ['desde' => $desde, 'fins' => $fins],
            'periodes' => $periodes,
            'files' => $files,
            'mitjana' => $mitjana,
            // Escrits de disconformitat pendents de resposta: qui llegeix les xifres ha de veure
            // qui les discuteix, o el contradictori queda en una safata que ningú obre.
            'allegacions_pendents' => RendimentAllegacio::pendent()
                ->with('user:id,name')->orderBy('presentada_ts')->get(),
        ]);
    }

    /**
     * GET /v1/rendiment/persona/{userId} — el MATEIX que veu Direcció d'aquesta persona, sense
     * mitjana del servei, sense companys i sense rànquing. La comparació entre persones és útil per
     * a qui organitza el servei; per a la persona no aporta res i converteix una xifra en un judici.
     *
     * La ruta porta el middleware 'owner' (titular o staff). Aquí s'estreny a més: fora del titular,
     * només admin i hr, que són exactament els perfils que ja tenien el panell de Direcció. Amb
     * només 'owner', un coordinador hi entraria per una porta que el panell li té tancada.
     */
    public function meu(Request $request, $userId)
    {
        $userId = (int) $userId;
        $propi = $userId === (int) $request->user()->id;
        abort_unless($propi || in_array($request->user()->role, ['admin', 'hr'], true), 403, 'No autoritzat');

        $periodes = DB::table('domi_rendiment')
            ->where('user_id', $userId)
            ->select('periode_desde', 'periode_fins')
            ->distinct()->orderByDesc('periode_fins')->limit(24)->get();

        if ($periodes->isEmpty()) {
            return response()->json([
                'periode' => null, 'periodes' => [], 'fila' => null,
                'indicadors' => self::INDICADORS, 'allegacions' => [],
                'avis' => 'Encara no ha arribat cap tancament de la Domiciliària amb dades teves.',
            ]);
        }

        $desde = $request->input('desde', $periodes[0]->periode_desde);
        $fins = $request->input('fins', $periodes[0]->periode_fins);

        $fila = DB::table('domi_rendiment')
            ->where('user_id', $userId)
            ->where('periode_desde', $desde)->where('periode_fins', $fins)
            ->first();

        if ($fila) {
            // Mateix recompte que la columna "Elements" del panell de Direcció: és un fet sobre la
            // persona i, per tant, dada seva (art. 15 RGPD). El DETALL i la contradicció del fet
            // van pel canal disciplinari, no per aquí.
            $e = DisciplinaryElement::where('user_id', $userId)
                ->selectRaw('COUNT(*) n, SUM(estat = "inclos_en_cas") en_cas')->first();
            $fila->elements = (int) ($e->n ?? 0);
            $fila->elements_en_cas = (int) ($e->en_cas ?? 0);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $propi ? 'RENDIMENT_PROPI' : 'RENDIMENT_CONSULTA',
            'entity_type' => 'domi_rendiment',
            'description' => $propi
                ? "Consulta del rendiment propi del periode {$desde} a {$fins}"
                : "Consulta del rendiment de l'usuari {$userId} del periode {$desde} a {$fins}",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json([
            'periode' => ['desde' => $desde, 'fins' => $fins],
            'periodes' => $periodes,
            'fila' => $fila,
            'indicadors' => self::INDICADORS,
            'allegacions' => RendimentAllegacio::where('user_id', $userId)
                ->orderByDesc('presentada_ts')->get(),
        ]);
    }

    /**
     * POST /v1/rendiment/persona/{userId}/allegacio — rebatre UN indicador d'UN període.
     *
     * Només el titular. Ni Direcció ni RRHH poden al·legar en nom seu: un escrit de descàrrec
     * posat per un tercer no acredita res (mateix criteri que l'acusament de recepció disciplinari).
     */
    public function allegacio(Request $request, $userId)
    {
        $userId = (int) $userId;
        abort_unless($userId === (int) $request->user()->id, 403, 'Només la persona titular pot rebatre les seves xifres.');

        $data = $request->validate([
            'periode_desde' => 'required|date_format:Y-m-d',
            'periode_fins' => 'required|date_format:Y-m-d|after_or_equal:periode_desde',
            'indicador' => 'required|string|in:' . implode(',', array_keys(self::INDICADORS)),
            'text' => 'required|string|min:20|max:2000',
        ]);

        // No es rebat una xifra que no existeix: evita escrits orfes que després ningú pot contrastar.
        $existeix = DB::table('domi_rendiment')
            ->where('user_id', $userId)
            ->where('periode_desde', $data['periode_desde'])
            ->where('periode_fins', $data['periode_fins'])
            ->exists();
        abort_unless($existeix, 422, 'No hi ha cap tancament teu per a aquest període.');

        $obert = RendimentAllegacio::pendent()
            ->where('user_id', $userId)
            ->where('periode_desde', $data['periode_desde'])
            ->where('periode_fins', $data['periode_fins'])
            ->where('indicador', $data['indicador'])
            ->exists();
        if ($obert) {
            return response()->json([
                'message' => 'Ja tens un escrit pendent de resposta sobre aquest indicador i aquest període.',
            ], 422);
        }

        $a = RendimentAllegacio::create([
            'user_id' => $userId,
            'periode_desde' => $data['periode_desde'],
            'periode_fins' => $data['periode_fins'],
            'indicador' => $data['indicador'],
            'text' => $data['text'],
            'presentada_ts' => now(),
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'RENDIMENT_ALLEGACIO',
            'entity_type' => 'rendiment_allegacions',
            'entity_id' => $a->id,
            'description' => "Escrit de disconformitat amb l'indicador {$data['indicador']} del periode "
                . "{$data['periode_desde']} a {$data['periode_fins']}",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json($a, 201);
    }

    /**
     * POST /v1/rendiment/allegacions/{id}/resposta — resposta motivada de Direcció/RRHH.
     * Es respon una sola vegada: la resposta és la posició de l'empresa, no un fil de conversa.
     */
    public function resposta(Request $request, $id)
    {
        $a = RendimentAllegacio::findOrFail((int) $id);
        if ($a->resposta_ts) {
            return response()->json(['message' => 'Aquest escrit ja té resposta.'], 422);
        }

        $data = $request->validate(['resposta' => 'required|string|min:20|max:2000']);

        $a->update([
            'resposta' => $data['resposta'],
            'resposta_ts' => now(),
            'respost_per' => $request->user()->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'RENDIMENT_ALLEGACIO_RESPOSTA',
            'entity_type' => 'rendiment_allegacions',
            'entity_id' => $a->id,
            'description' => "Resposta a l'escrit de disconformitat #{$a->id} (indicador {$a->indicador})",
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        return response()->json($a->fresh(['user:id,name', 'responedor:id,name']));
    }
}
