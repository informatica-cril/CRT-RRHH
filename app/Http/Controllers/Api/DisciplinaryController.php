<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryCaseEvent;
use App\Models\DisciplinaryDocument;
use App\Models\DisciplinaryElement;
use App\Models\DisciplinaryFaultType;
use App\Models\User;
use App\Services\DisciplinaryEngine;
use Illuminate\Http\Request;

/**
 * Procediment disciplinari (admin/hr). El motor ACREDITA i PROPOSA; la persona QUALIFICA i SIGNA.
 * Cap transició es fa sense passar per DisciplinaryEngine::potTransicio (les garanties com a porta).
 * Historial append-only a disciplinary_case_events (cadena de custòdia).
 *
 * Tot acte del procediment —i també la simple CONSULTA d'un expedient— deixa fila a audit_logs: la
 * pantalla promet al qui hi entra que el seu accés queda registrat, i aquesta promesa ha de ser certa.
 */
class DisciplinaryController extends Controller
{
    public function __construct(private DisciplinaryEngine $engine)
    {
    }

    /** Resol el treballador RRHH a partir de l'identificador operatiu de domi (users.domi_username,
     *  sembrat per DNI amb `php artisan domi:map-users`). El DNI mai viatja per l'API. */
    private function resolveUser(string $professional): ?int
    {
        return User::where('domi_username', $professional)->value('id');
    }

    // ── Catàleg ───────────────────────────────────────────────────────────────
    public function faultTypes()
    {
        return DisciplinaryFaultType::orderBy('grau_base')->orderBy('clau')->get();
    }

    // ── Elements ──────────────────────────────────────────────────────────────
    public function elements(Request $request)
    {
        $q = DisciplinaryElement::query()->orderByDesc('data_fet');
        if ($request->filled('professional')) {
            $q->where('professional', $request->query('professional'));
        }
        return $q->limit(300)->get();
    }

    public function storeElement(Request $request)
    {
        $data = $request->validate([
            'professional'     => 'required|string|max:64',
            'user_id'          => 'nullable|integer|exists:users,id',
            'tipus_falta'      => 'required|string|exists:disciplinary_fault_types,clau',
            // Cap fet passa DEMÀ i cap empresa el coneix abans que passi: una data futura fabrica
            // el còmput de la prescripció (ET 60.2) i pot ressuscitar un fet ja mort.
            'data_fet'         => 'required|date|before_or_equal:today',
            'data_coneixement' => 'required|date|after_or_equal:data_fet|before_or_equal:today',
            'font'             => 'nullable|string|max:80',
            'valor'            => 'nullable|string|max:120',
            'descripcio'       => 'nullable|string|max:255',
            'imputable'        => 'required|in:si,no,condicional',
        ], [
            // Missatges propis: l'app encara no té els fitxers d'idioma de validació publicats i
            // aquí un «validation.before_or_equal» no diria a ningú què acaba de blocar-se ni per què.
            'data_fet.before_or_equal'         => 'La data del fet no pot ser futura.',
            'data_coneixement.before_or_equal' => 'La data de coneixement no pot ser futura: el termini de prescripció (ET 60.2) es compta des d\'ella i una data endavant el fabrica.',
            'data_coneixement.after_or_equal'  => 'L\'empresa no pot conèixer un fet abans que passi: la data de coneixement ha de ser igual o posterior a la del fet.',
        ]);
        // L'espai «domi:» acredita que el fet ve del pont amb l'app assistencial. Si es pogués
        // teclejar, un indicador escrit a mà sortiria al plec amb l'aparença d'un registre automàtic.
        if (str_starts_with((string) ($data['font'] ?? ''), DisciplinaryElement::FONT_PONT)) {
            return response()->json(['message' => 'La font «' . DisciplinaryElement::FONT_PONT
                . '…» està reservada al pont amb l\'aplicació domiciliària i no es pot escriure a mà. '
                . 'Incorporeu el fet des d\'«Evidència objectiva (domi)» o poseu-hi la font real.'], 422);
        }
        $data['origen'] = 'manual';
        $data['estat'] = 'valorat';
        $data['created_by'] = $request->user()->id;

        $el = DisciplinaryElement::create($data);
        $this->audita($request, 'DISCIPLINARI_ELEMENT_ALTA', 'disciplinary_elements', $el->id,
            "Element de prova incorporat MANUALMENT sobre {$el->professional} ({$el->tipus_falta}, fet del {$el->data_fet->toDateString()})");

        return response()->json($el, 201);
    }

    /**
     * Rehabilita una prova que està fora del plec per origen no acreditat.
     *
     * No és un interruptor: és un acte motivat i signat. Qui la rehabilita assumeix per escrit que
     * imputa un fet que l'aplicació no pot acreditar d'on ve, i això ha de constar tant a l'element
     * com a l'auditoria, perquè si l'expedient s'impugna la primera pregunta serà exactament aquesta.
     * El camí invers (tornar a bloquejar) també hi cap: rectificar no ha de ser més difícil que errar.
     */
    public function reinstateElement(Request $request, DisciplinaryElement $element)
    {
        $data = $request->validate([
            'apte'  => 'required|boolean',
            'motiu' => 'required|string|min:20|max:500',
        ], [
            'motiu.required' => 'Cal motivar per escrit per què s\'imputa un fet d\'origen no acreditat.',
            'motiu.min'      => 'La motivació ha de dir QUÈ acredita l\'origen del fet, no només que s\'accepta.',
        ]);

        if ($element->estat === 'descartat') {
            return response()->json(['message' => 'Element descartat: no es rehabilita, es torna a incorporar.'], 422);
        }

        $element->forceFill([
            'apte_plec'       => (bool) $data['apte'],
            'apte_plec_motiu' => $data['motiu'],
            'apte_plec_per'   => $request->user()->id,
            'apte_plec_ts'    => now(),
        ])->save();

        $verb = $data['apte'] ? 'REHABILITADA' : 'BLOQUEJADA';
        $this->audita($request, 'DISCIPLINARI_ELEMENT_APTITUD', 'disciplinary_elements', $element->id,
            "Prova #{$element->id} de {$element->professional} (font {$element->font}, origen no acreditat) "
            . "{$verb} per al plec. Motivació: {$data['motiu']}");

        return response()->json($element->fresh());
    }

    /** Suggeriments d'obertura: la recollida proposa, la persona decideix. */
    public function suggestions()
    {
        return $this->engine->suggeriments();
    }

    /** Blocs de la recollida de domi (F1.bis). La font de l'element es construeix a partir d'aquí,
     *  mai del que arribi al cos de la petició. */
    private const BLOCS_DOMI = ['retards', 'durades', 'reprogramacions', 'tecniques_no'];

    /**
     * Incorporació MASSIVA d'evidència de domi com a elements (decisió humana fet a fet: la
     * imputabilitat i la qualificació les fixa qui incorpora; 'condicional' no computa per
     * reincidència).
     *
     * AQUEST és el pont, i per això és l'únic lloc que pot marcar una prova com d'origen automàtic.
     * El client ja no envia ni la font ni la magnitud: envia QUIN fet de la recollida vol incorporar
     * (bloc + posició) i el servidor va a buscar-lo a domi i el copia d'allà. Un fet que no consti a
     * la recollida no entra. Si domi no respon, no s'incorpora res: val més quedar-se sense evidència
     * que fabricar-ne l'origen.
     */
    public function bulkElements(Request $request)
    {
        $data = $request->validate([
            'professional'          => 'required|string|max:64',
            'user_id'               => 'nullable|integer|exists:users,id',
            'desde'                 => 'nullable|date',
            'fins'                  => 'nullable|date',
            'items'                 => 'required|array|min:1|max:100',
            'items.*.bloc'          => 'required|string|in:' . implode(',', self::BLOCS_DOMI),
            'items.*.idx'           => 'required|integer|min:0',
            'items.*.dia'           => 'required|date|before_or_equal:today',
            'items.*.tipus_falta'   => 'required|string|exists:disciplinary_fault_types,clau',
            'items.*.imputable'     => 'required|in:si,no,condicional',
        ]);

        [$recollida, $errRecollida] = $this->recollidaDomi($request, $data);
        if ($errRecollida) {
            return response()->json(['message' => $errRecollida], 502);
        }

        $creats = [];
        $rebutjats = [];
        foreach ($data['items'] as $it) {
            $fet = $recollida[$it['bloc']][$it['idx']] ?? null;
            // El fet ha d'existir a la recollida I ser del dia que diu el client: si no quadra,
            // el que s'estaria incorporant no és el que ha registrat l'aplicació assistencial.
            if (! is_array($fet) || ($fet['dia'] ?? null) !== $it['dia']) {
                $rebutjats[] = ['bloc' => $it['bloc'], 'idx' => $it['idx'], 'dia' => $it['dia'],
                    'motiu' => 'el fet no consta a la recollida de domi per a aquest professional i període'];
                continue;
            }
            $font = DisciplinaryElement::FONT_PONT . $it['bloc'];
            // Duplicats: mateix professional + falta + dia + font ja incorporat → s'omet.
            $dup = DisciplinaryElement::where('professional', $data['professional'])
                ->where('tipus_falta', $it['tipus_falta'])
                ->whereDate('data_fet', $it['dia'])
                ->where('font', $font)->exists();
            if ($dup) {
                continue;
            }
            $creats[] = DisciplinaryElement::create([
                'professional'     => $data['professional'],
                'user_id'          => $data['user_id'] ?? $this->resolveUser($data['professional']),
                'tipus_falta'      => $it['tipus_falta'],
                'data_fet'         => $it['dia'],
                // El coneixement de l'empresa arrenca quan RRHH incorpora el fet (avui).
                'data_coneixement' => now()->toDateString(),
                'font'             => $font,
                'origen'           => 'pont',
                'valor'            => mb_substr($this->magnitudDomi($fet), 0, 120),
                'descripcio'       => mb_substr($this->descripcioDomi($it['bloc'], $fet), 0, 255),
                'imputable'        => $it['imputable'],
                'estat'            => 'valorat',
                'created_by'       => $request->user()->id,
            ]);
        }
        if ($creats) {
            $this->audita($request, 'DISCIPLINARI_ELEMENT_PONT', 'disciplinary_elements', null,
                count($creats) . " fets de la recollida de domi incorporats com a evidència de {$data['professional']}");
        }

        return response()->json([
            'creats'            => count($creats),
            'omesos_duplicats'  => count($data['items']) - count($creats) - count($rebutjats),
            'rebutjats'         => $rebutjats,
            'elements'          => $creats,
        ], 201);
    }

    /** Recollida per-fet de domi per a aquest professional/període: [array per blocs, error]. */
    private function recollidaDomi(Request $request, array $data): array
    {
        $dies = array_column($data['items'], 'dia');
        $peticio = $request->duplicate(query: [
            'desde' => $data['desde'] ?? min($dies),
            'fins'  => $data['fins'] ?? max($dies),
        ]);
        $resp = app(ExpedientController::class)->detall($peticio, $data['professional']);
        $cos = json_decode($resp->getContent(), true);
        if ($resp->getStatusCode() !== 200 || ! ($cos['ok'] ?? false)) {
            return [null, 'No s\'ha pogut contrastar l\'evidència amb l\'aplicació domiciliària ('
                . ($cos['error'] ?? 'sense resposta') . '). No s\'incorpora cap fet: l\'origen automàtic només '
                . 'el pot acreditar el pont.'];
        }
        $out = [];
        foreach (self::BLOCS_DOMI as $b) {
            $out[$b] = array_values($cos['recollida'][$b] ?? []);
        }

        return [$out, null];
    }

    /** Magnitud del fet TAL COM la publica domi (mai la que hagi escrit ningú al formulari). */
    private function magnitudDomi(array $fet): string
    {
        foreach (['retard_min' => 'min de retard', 'minuts' => 'min'] as $k => $unitat) {
            if (isset($fet[$k]) && $fet[$k] !== '') {
                return $fet[$k] . ' ' . $unitat;
            }
        }

        return (string) ($fet['estat'] ?? $fet['motiu'] ?? '');
    }

    /** Descripció del fet a partir de la recollida (bloc + nota de domi). */
    private function descripcioDomi(string $bloc, array $fet): string
    {
        $etiquetes = ['retards' => 'Retard en el marcatge', 'durades' => 'Durada anòmala de la sessió',
            'reprogramacions' => 'Reprogramació', 'tecniques_no' => 'Tècnica no aplicada'];
        $detall = trim((string) ($fet['nota'] ?? $fet['motiu'] ?? $this->magnitudDomi($fet)));

        return trim(($etiquetes[$bloc] ?? $bloc) . ($detall !== '' ? ': ' . $detall : ''));
    }

    // ── Portal del treballador: notificacions i acusament (dins de l'app) ─────
    public function myNotifications(Request $request)
    {
        return DisciplinaryDocument::where('user_id', $request->user()->id)
            ->whereNotNull('notificat_ts')
            ->orderByDesc('notificat_ts')
            // L'empremta va amb l'escrit: el treballador ha de poder acreditar QUIN text se li va
            // lliurar, no fiar-se que ningú l'hagi tocat després.
            ->get(['id', 'case_id', 'tipus', 'contingut', 'estat', 'signat_per', 'signat_ts',
                'notificat_ts', 'acus_ts', 'contingut_hash']);
    }

    /**
     * Expedients del treballador que JA se li han comunicat, amb l'estat del seu tràmit d'audiència.
     * Abans de la comunicació no se'n diu res: el que encara s'instrueix no s'ha notificat.
     */
    public function myCases(Request $request)
    {
        return DisciplinaryCase::where('user_id', $request->user()->id)
            ->whereNotNull('comunicat_ts')
            ->orderByDesc('comunicat_ts')
            ->get(['id', 'tipus_falta', 'gravetat', 'estat', 'comunicat_ts', 'termini_alegacions',
                'alegacions_ts', 'alegacions_text', 'renuncia_termini_ts', 'resolucio_tipus',
                'resolucio_dies', 'resolt_ts'])
            ->map(function ($c) {
                $arr = $c->toArray();
                $arr['pot_alegar'] = $this->potAlegar($c) === null;
                $arr['dies_per_alegar'] = $c->termini_alegacions
                    ? (int) floor(now()->startOfDay()->diffInDays($c->termini_alegacions, false)) : null;

                return $arr;
            });
    }

    /** Motiu pel qual el treballador NO pot al·legar ara mateix, o null si pot. */
    private function potAlegar(DisciplinaryCase $case): ?string
    {
        if (! $case->comunicat_ts) {
            return 'Aquest expedient encara no se t\'ha comunicat.';
        }
        if (in_array($case->estat, ['resolt', 'executat', 'arxivat'], true)) {
            return 'L\'expedient ja està resolt: contra la resolució et queda la via judicial (20 dies hàbils).';
        }
        if ($case->alegacions_ts) {
            return 'Ja has presentat al·legacions en aquest expedient el ' . $case->alegacions_ts->format('d/m/Y') . '.';
        }
        if ($case->renuncia_termini_ts) {
            return 'Vas renunciar expressament al termini d\'al·legacions.';
        }
        if ($case->termini_alegacions && now()->startOfDay()->gt($case->termini_alegacions)) {
            return 'El termini d\'al·legacions va vèncer el ' . $case->termini_alegacions->format('d/m/Y') . '.';
        }

        return null;
    }

    /**
     * AL·LEGACIONS del treballador (tràmit d'audiència, ET 55.1 i art. 55.K.6 del conveni). Mateix
     * patró que l'audiència prèvia del rebuig d'un tram de fitxatge: només el titular, i el servidor
     * és qui hi posa la data. El plec diu a la persona que al·legui davant de RRHH; aquesta és la via.
     * Ni admin ni RRHH poden escriure aquí: unes al·legacions redactades per la part acusadora no
     * són defensa.
     */
    public function alegacions(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('alegar', $case);
        if ($motiu = $this->potAlegar($case)) {
            return response()->json(['message' => $motiu], 422);
        }
        $data = $request->validate([
            'text'        => 'required|string|min:10|max:20000',
            'adjunt'      => 'nullable|string|max:8000000',        // base64 del document que aporti
            'adjunt_nom'  => 'nullable|string|max:160',
        ]);

        $de = $case->estat;
        $case->update([
            'alegacions_text'       => $data['text'],
            'alegacions_ts'         => now(),                       // la data la posa el servidor
            'alegacions_per'        => $request->user()->id,
            'alegacions_adjunt'     => $data['adjunt'] ?? null,
            'alegacions_adjunt_nom' => $data['adjunt_nom'] ?? null,
            'estat'                 => $de === 'comunicat' ? 'alegacions' : $de,
        ]);
        $this->logEvent($case, $de, $case->estat, $request->user()->name,
            'AL·LEGACIONS presentades pel treballador dins del tràmit d\'audiència'
            . ($data['adjunt_nom'] ?? null ? " (amb adjunt: {$data['adjunt_nom']})" : ''));
        $this->audita($request, 'DISCIPLINARI_ALEGACIONS', 'disciplinary_cases', $case->id,
            "El treballador ha presentat al·legacions a l'expedient {$case->id}");

        return $case->fresh(['events']);
    }

    /**
     * RENÚNCIA al termini d'al·legacions. És un acte SEU i queda amb el seu id i la seva data: la
     * pressa de l'empresa no pot escurçar el termini de defensa de ningú.
     */
    public function renunciaTermini(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('alegar', $case);
        if ($motiu = $this->potAlegar($case)) {
            return response()->json(['message' => $motiu], 422);
        }
        $request->validate(['confirmo' => 'required|accepted']);

        $case->update(['renuncia_termini_ts' => now(), 'renuncia_termini_per' => $request->user()->id]);
        $this->logEvent($case, $case->estat, $case->estat, $request->user()->name,
            'RENÚNCIA EXPRESSA del treballador al termini d\'al·legacions (acte propi)');
        $this->audita($request, 'DISCIPLINARI_RENUNCIA_TERMINI', 'disciplinary_cases', $case->id,
            "El treballador renuncia al termini d'al·legacions de l'expedient {$case->id}");

        return $case->fresh(['events']);
    }

    public function acknowledgeDocument(Request $request, DisciplinaryDocument $document)
    {
        // Acte personal i exclusiu del destinatari (DisciplinaryDocumentPolicy).
        $this->authorize('acknowledge', $document);
        if (! $document->notificat_ts) {
            return response()->json(['message' => 'Document no notificat.'], 422);
        }
        if ($document->acus_ts) {
            return response()->json(['message' => 'Ja heu acusat recepció.'], 422);
        }
        $document->update(['acus_ts' => now(), 'acus_ip' => $request->ip()]);
        if ($document->case_id && ($cas = DisciplinaryCase::find($document->case_id))) {
            $this->logEvent($cas, $cas->estat, $cas->estat, $request->user()->name,
                "ACUSAMENT de recepció del «{$document->tipus}» dins l'app (acredita recepció, no conformitat)", $document->id);
        }
        $this->audita($request, 'DISCIPLINARI_ACUSAMENT', 'disciplinary_documents', $document->id,
            "El treballador acusa recepció de l'escrit «{$document->tipus}»");

        return $document->fresh();
    }

    // ── Casos ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $q = DisciplinaryCase::query()->orderByDesc('created_at');
        if ($request->filled('estat')) {
            $q->where('estat', $request->query('estat'));
        }
        return $q->limit(200)->get()->map(fn ($c) => $this->withComputed($c));
    }

    public function show(Request $request, DisciplinaryCase $case)
    {
        // La pantalla diu al qui hi entra que el seu accés queda registrat: aquí és on això es
        // compleix. Consultar un expedient disciplinari és, per si sol, un accés a dada sensible.
        $this->audita($request, 'DISCIPLINARI_CONSULTA', 'disciplinary_cases', $case->id,
            "Consulta de l'expedient {$case->id} de {$case->professional} (estat: {$case->estat})");
        $case->load(['events', 'documents', 'elements']);
        $out = $this->withComputed($case);
        $out['garanties'] = $this->engine->garanties($case);
        $out['reincidencia'] = $this->engine->reincidencia($case->professional, $case->tipus_falta);
        $out['transicions'] = $this->transicionsPossibles($case, $request->user()?->name);
        $out['raonament'] = \App\Support\RaonamentDisciplinari::per($case);

        return $out;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'professional'     => 'required|string|max:64',
            'user_id'          => 'nullable|integer|exists:users,id',
            'vincle'           => 'required|in:laboral,autonom',
            'tipus_falta'      => 'required|string|exists:disciplinary_fault_types,clau',
            'gravetat'         => 'nullable|in:lleu,menys_greu,greu,molt_greu',
            'data_coneixement' => 'required|date',
            'data_fet'         => 'nullable|date',
            'es_representant'  => 'boolean',
            'element_ids'      => 'array',
            'element_ids.*'    => 'integer|exists:disciplinary_elements,id',
        ]);

        // Autònom: es pot documentar l'incompliment mercantil, però MAI per la via disciplinària
        // laboral. El veto no es fia del camp del formulari: si la FITXA del titular a RRHH diu
        // autònom, es bloqueja encara que s'hagi marcat 'laboral' a l'alta del cas.
        $userId  = $data['user_id'] ?? $this->resolveUser($data['professional']);
        $titular = $userId ? User::find($userId) : null;
        if ($data['vincle'] === 'autonom' || $titular?->esAutonom()) {
            return response()->json(['message' => 'Vincle mercantil: la via disciplinària laboral està vetada. Gestioneu-ho com a incompliment contractual (mercantil), fora d\'aquest mòdul.'], 422);
        }

        $tipus = DisciplinaryFaultType::where('clau', $data['tipus_falta'])->first();
        $gravetat = $data['gravetat'] ?? $tipus->grau_base;

        // Les proves han de ser DEL TITULAR i han d'estar VIVES abans de fundar-hi res. Incorporar
        // el fet d'un altre treballador (que a més sortia silenciosament del seu propi expedient) o
        // un fet ja prescrit (ET 60.2) contamina el plec sencer, no només aquella línia.
        if (! empty($data['element_ids'])) {
            $elements = DisciplinaryElement::whereIn('id', $data['element_ids'])->get();
            $aliens = $elements->filter(fn ($el) => ! $this->elementDelProfessional($data['professional'], $userId, $el));
            if ($aliens->isNotEmpty()) {
                return response()->json(['message' => 'Els fets ' . $aliens->pluck('id')->implode(', ')
                    . ' no són d\'aquest treballador: una prova només imputa a qui l\'ha protagonitzat.'], 422);
            }
            $prescrits = $elements->filter(fn ($el) => $this->engine->elementPrescrit($el));
            if ($prescrits->isNotEmpty()) {
                return response()->json(['message' => 'Els fets ' . $prescrits->pluck('id')->implode(', ')
                    . ' estan PRESCRITS (ET 60.2) i no poden fonamentar cap càrrec.'], 422);
            }
        }

        $cas = DisciplinaryCase::create([
            'professional'     => $data['professional'],
            'user_id'          => $userId,
            'vincle'           => 'laboral',
            'tipus_falta'      => $data['tipus_falta'],
            'gravetat'         => $gravetat,
            'estat'            => 'esborrany',
            'data_coneixement' => $data['data_coneixement'],
            // La data del FET es DESA: és el que fixa el topall dur dels 6 mesos (ET 60.2) i sense
            // ella qualsevol recàlcul posterior de la prescripció el perdria.
            'data_fet'         => $data['data_fet'] ?? null,
            'data_prescripcio' => $this->engine->prescripcio($gravetat, $data['data_coneixement'], $data['data_fet'] ?? null),
            'es_representant'  => (bool) ($data['es_representant'] ?? false),
            'reincidencia_acreditada' => $this->engine->reincidencia($data['professional'], $data['tipus_falta'])['acreditada'],
            'obert_per'        => $request->user()->name,
            'obert_ts'         => now(),
        ]);

        if (! empty($data['element_ids'])) {
            DisciplinaryElement::whereIn('id', $data['element_ids'])
                ->update(['case_id' => $cas->id, 'estat' => 'inclos_en_cas']);
        }

        $this->logEvent($cas, null, 'esborrany', $request->user()->name, 'Cas obert');
        $this->audita($request, 'DISCIPLINARI_OBERTURA', 'disciplinary_cases', $cas->id,
            "Expedient obert a {$cas->professional} per «{$cas->tipus_falta}» ({$cas->gravetat})");

        return response()->json($this->withComputed($cas->fresh()), 201);
    }

    /** El fet imputa aquest treballador? Mateix criteri que el motor, aplicat abans de vincular-lo. */
    private function elementDelProfessional(string $professional, ?int $userId, DisciplinaryElement $el): bool
    {
        if ($userId && $el->user_id) {
            return (int) $userId === (int) $el->user_id;
        }

        return $el->professional === $professional;
    }

    /** Actualitza camps del procediment (checkboxes de garanties, audiència RLT, al·legacions…). */
    public function update(Request $request, DisciplinaryCase $case)
    {
        if (in_array($case->estat, ['resolt', 'executat', 'arxivat'], true)) {
            return response()->json(['message' => 'Cas tancat: no es modifica (historial immutable).'], 422);
        }
        $data = $request->validate([
            'gravetat'                => 'sometimes|in:lleu,menys_greu,greu,molt_greu',
            'te_evidencia_licita'     => 'boolean',
            'afectacio_servei_acreditada' => 'boolean',
            'afectacio_confirmada_per'    => 'nullable|string|max:64',
            'apercebiment_previ'      => 'boolean',
            'es_representant'         => 'boolean',
            'expedient_contradictori' => 'boolean',
            'audiencia_rlt_ts'        => 'nullable|date',
            // 'alegacions_ts' NO hi és i no hi pot tornar: les al·legacions són un acte del
            // treballador i les data el servidor quan ell les presenta (mètode alegacions()).
            // Mentre les podia escriure la part acusadora, el tràmit d'audiència era una casella.
            'termini_alegacions'      => 'nullable|date|after_or_equal:today',
            'sancio_proposada'        => 'nullable|string|max:120',
        ], [
            // Endarrerir el termini a una data passada el faria vèncer a l'instant i tornaria a
            // obrir la porta de resoldre sense escoltar ningú.
            'termini_alegacions.after_or_equal' => 'El termini d\'al·legacions no es pot posar en una data ja passada: escurçar-lo a posteriori buida el tràmit d\'audiència.',
        ]);
        if ($data === []) {
            // Res del que s'ha enviat és modificable des d'aquí (p. ex. les al·legacions, que són
            // del treballador). Sense canvis no s'escriu al llibre de custòdia: un event buit hi fa
            // soroll i el llibre s'ha de poder llegir.
            return $this->withComputed($case);
        }
        // Si canvia la gravetat, recalcula la prescripció amb la MATEIXA data de coneixement i el
        // MATEIX fet: requalificar mai pot allargar la falta més enllà del topall dels 6 mesos des
        // de la comissió (ET 60.2).
        if (isset($data['gravetat']) && $data['gravetat'] !== $case->gravetat) {
            $data['data_prescripcio'] = $this->engine->prescripcio(
                $data['gravetat'],
                $case->data_coneixement?->toDateString(),
                $case->data_fet?->toDateString()
            );
        }
        $case->update($data);
        $this->logEvent($case, $case->estat, $case->estat, $request->user()->name, 'Actualització d\'instrucció: ' . implode(', ', array_keys($data)));
        $this->audita($request, 'DISCIPLINARI_INSTRUCCIO', 'disciplinary_cases', $case->id,
            "Instrucció de l'expedient {$case->id}: " . implode(', ', array_keys($data)));

        return $this->withComputed($case->fresh());
    }

    /** Transició d'estat, SEMPRE passant per la guarda del motor. */
    public function transition(Request $request, DisciplinaryCase $case)
    {
        $data = $request->validate([
            'estat'               => 'required|in:instruccio,comunicat,alegacions,resolt,executat,arxivat',
            'nota'                => 'nullable|string|max:500',
            'resolucio_tipus'     => 'nullable|in:amonestacio,suspensio,trasllat,inhabilitacio,acomiadament,arxiu',
            'resolucio_dies'      => 'nullable|integer|min:1|max:1095',
            'resolucio_motivacio' => 'nullable|string',
        ]);
        $desti = $data['estat'];

        // La resolució és HUMANA: el signant és l'usuari autenticat que prem el botó, mai el sistema.
        if ($desti === 'resolt') {
            $case->resolt_per = $request->user()->name;
            $case->resolucio_tipus = $data['resolucio_tipus'] ?? null;
            $case->resolucio_motivacio = $data['resolucio_motivacio'] ?? null;
            if (! $case->resolucio_tipus) {
                return response()->json(['message' => 'Cal indicar el tipus de resolució (o arxiu).'], 422);
            }
            if (! trim((string) $case->resolucio_motivacio)) {
                return response()->json(['message' => 'La resolució ha d\'estar MOTIVADA per la persona que la signa.'], 422);
            }
            $case->resolucio_dies = $data['resolucio_dies'] ?? null;
            [$capsSancio, $motiuSancio] = \App\Support\ConveniSancions::valida(
                $case->gravetat, $case->resolucio_tipus, $case->resolucio_dies
            );
            if (! $capsSancio) {
                return response()->json(['message' => $motiuSancio], 422);
            }
            if ($case->gravetat === 'molt_greu' && $case->resolucio_tipus !== 'arxiu') {
                $case->rlt_informat_ts = null;
            }
        }

        [$ok, $motiu] = $this->engine->potTransicio($case, $desti);
        if (! $ok) {
            return response()->json(['message' => "Transició bloquejada: {$motiu}"], 422);
        }

        $de = $case->estat;
        $case->estat = $desti;
        if ($desti === 'comunicat') {
            $case->comunicat_ts = now();
            $case->termini_alegacions = $case->termini_alegacions ?? now()->addWeekdays(5)->toDateString();
        }
        // 'alegacions' obre la finestra; NO la dona per feta. Qui data les al·legacions és el
        // treballador en presentar-les: un alegacions_ts posat per l'empresa tancava el tràmit
        // d'audiència sense que ningú hagués escoltat res.
        if ($desti === 'resolt') {
            $case->resolt_ts = now();
        }
        $case->save();

        // NOTIFICACIÓ DINS L'APP en els dos moments en què hi ha res a lliurar: la comunicació del
        // plec i la RESOLUCIÓ. Abans això només passava a 'comunicat', de manera que la resolució
        // —l'escrit que imposa la sanció i obre els 20 dies hàbils per impugnar-la— es quedava
        // signada al calaix i no arribava mai al treballador.
        if (in_array($desti, ['comunicat', 'resolt'], true)) {
            $this->notificaEscritsSignats($request, $case, $de, $desti);
        }

        // El llibre de custòdia ha de dir QUÈ es va imposar: un event de resolució amb la nota
        // buida no acredita la sanció, i és l'única constància interna del seu contingut.
        $nota = $data['nota'] ?? null;
        if ($desti === 'resolt') {
            $nota = trim('SANCIÓ IMPOSADA: ' . $this->engine->sancioLegible($case) . '. ' . (string) $nota);
        }
        $this->logEvent($case, $de, $desti, $request->user()->name, $nota ? mb_substr($nota, 0, 500) : null);
        $this->audita($request, 'DISCIPLINARI_TRANSICIO_' . mb_strtoupper($desti), 'disciplinary_cases', $case->id,
            "Expedient {$case->id} de {$case->professional}: «{$de}» → «{$desti}»"
            . ($desti === 'resolt' ? ' · ' . $this->engine->sancioLegible($case) : ''));

        return $this->withComputed($case->fresh(['events', 'documents']));
    }

    /** Lliura al portal del treballador els escrits SIGNATS que encara no se li han notificat. */
    private function notificaEscritsSignats(Request $request, DisciplinaryCase $case, ?string $de, string $desti): void
    {
        $pendents = DisciplinaryDocument::where('case_id', $case->id)
            ->where('estat', 'signat')->whereNull('notificat_ts')->get();
        foreach ($pendents as $doc) {
            $doc->update(['estat' => 'notificat', 'notificat_ts' => now(), 'user_id' => $case->user_id]);
            $this->logEvent($case, $de, $desti, $request->user()->name,
                "Escrit «{$doc->tipus}» NOTIFICAT al portal del treballador (pendent d'acusament)", $doc->id);
            $this->audita($request, 'DISCIPLINARI_NOTIFICACIO', 'disciplinary_documents', $doc->id,
                "Escrit «{$doc->tipus}» de l'expedient {$case->id} notificat al treballador");
        }
    }

    // ── Documents ─────────────────────────────────────────────────────────────
    public function generateDocument(Request $request, DisciplinaryCase $case)
    {
        $data = $request->validate([
            'tipus' => 'required|in:apercebiment,amonestacio,plec_carrecs,resolucio,carta_acomiadament',
        ]);
        $text = $this->engine->generaEscrit($case, $data['tipus']);
        $doc = DisciplinaryDocument::create([
            'case_id'      => $case->id,
            'professional' => $case->professional,
            'tipus'        => $data['tipus'],
            'contingut'    => $text,
            // Empremta del text que va PRODUIR EL MOTOR. És l'única referència contra la qual es pot
            // dir després si el que es va signar era l'escrit generat o un altre.
            'motor_hash'   => DisciplinaryDocument::hashContingut($text),
            'estat'        => 'esborrany',
            'generat_per'  => $request->user()->name,
        ]);
        $this->logEvent($case, $case->estat, $case->estat, $request->user()->name,
            "Generat esborrany «{$data['tipus']}» (pendent de revisió i signatura humana)", $doc->id);
        $this->audita($request, 'DISCIPLINARI_DOC_GENERAT', 'disciplinary_documents', $doc->id,
            "Esborrany «{$data['tipus']}» generat per a l'expedient {$case->id}");

        return response()->json($doc, 201);
    }

    public function updateDocument(Request $request, DisciplinaryDocument $document)
    {
        if ($document->estat !== 'esborrany') {
            return response()->json(['message' => 'Document signat/notificat: no s\'edita.'], 422);
        }
        $data = $request->validate(['contingut' => 'required|string']);
        $abans = DisciplinaryDocument::hashContingut($document->contingut);
        $document->update($data);
        $despres = DisciplinaryDocument::hashContingut($document->contingut);
        if ($abans === $despres) {
            return $document;   // desar sense canviar res no és una reescriptura
        }

        // Reescriure l'esborrany és legítim —el signa una persona i pot completar-lo—, però ha de
        // DEIXAR RASTRE: substituir el text del motor per un altre i signar-lo sense constància
        // convertia el llibre de custòdia en una coartada.
        $apartat = $document->motor_hash && $despres !== $document->motor_hash;
        if ($document->case_id && ($cas = DisciplinaryCase::find($document->case_id))) {
            $this->logEvent($cas, $cas->estat, $cas->estat, $request->user()->name,
                "Esborrany «{$document->tipus}» EDITAT a mà"
                . ($apartat ? " — el text S'HA APARTAT del que va generar el motor (empremta {$despres})"
                            : ' — el text torna a coincidir amb el del motor'), $document->id);
        }
        $this->audita($request, 'DISCIPLINARI_DOC_EDITAT', 'disciplinary_documents', $document->id,
            "Esborrany «{$document->tipus}» reescrit" . ($apartat ? " (apartat del text del motor)" : ''));

        return $document;
    }

    /** Signatura HUMANA del document: qui prem és qui signa. */
    public function signDocument(Request $request, DisciplinaryDocument $document)
    {
        $this->authorize('sign', $document);
        if ($document->estat !== 'esborrany') {
            return response()->json(['message' => 'Ja signat o notificat.'], 422);
        }
        // Es desa l'empremta del text EXACTE que se signa: és el que permet demostrar després que
        // el treballador va rebre aquest escrit i no un altre.
        $hash = DisciplinaryDocument::hashContingut($document->contingut);
        $apartat = $document->motor_hash !== null && $hash !== $document->motor_hash;
        $document->update([
            'estat' => 'signat', 'signat_per' => $request->user()->name, 'signat_ts' => now(),
            'contingut_hash' => $hash, 'apartat_del_motor' => $apartat,
        ]);
        if ($document->case_id && ($cas = DisciplinaryCase::find($document->case_id))) {
            $this->logEvent($cas, $cas->estat, $cas->estat, $request->user()->name,
                "Document «{$document->tipus}» SIGNAT (empremta {$hash})"
                . ($apartat ? ' — ATENCIÓ: el text signat NO és el que va generar el motor' : ''), $document->id);
            // Un escrit signat sobre un expedient ja comunicat s'ha de lliurar immediatament: si
            // esperés una transició, la resolució (que se signa amb el cas ja resolt) no sortiria mai.
            if (in_array($cas->estat, ['comunicat', 'alegacions', 'resolt', 'executat'], true)) {
                $this->notificaEscritsSignats($request, $cas, $cas->estat, $cas->estat);
            }
        }
        $this->audita($request, 'DISCIPLINARI_DOC_SIGNAT', 'disciplinary_documents', $document->id,
            "Escrit «{$document->tipus}» signat" . ($apartat ? ' amb text apartat del motor' : '') . " · empremta {$hash}");

        return $document->fresh();
    }

    /**
     * VERIFICACIÓ del document signat: es recalcula l'empremta del text que hi ha desat ara i es
     * compara amb la que es va segellar en signar-lo, i amb la del text del motor. Diu tres coses:
     * si el text s'ha tocat després de signar, si el que es va signar era el text del motor, i quina
     * empremta va rebre el treballador.
     */
    public function verifyDocument(Request $request, DisciplinaryDocument $document)
    {
        $actual = DisciplinaryDocument::hashContingut($document->contingut);
        $this->audita($request, 'DISCIPLINARI_DOC_VERIFICAT', 'disciplinary_documents', $document->id,
            "Verificació d'integritat de l'escrit «{$document->tipus}»");

        return [
            'document_id'        => $document->id,
            'tipus'              => $document->tipus,
            'estat'              => $document->estat,
            'signat_per'         => $document->signat_per,
            'signat_ts'          => $document->signat_ts,
            'hash_signat'        => $document->contingut_hash,
            'hash_actual'        => $actual,
            'hash_motor'         => $document->motor_hash,
            'integre'            => $document->contingut_hash !== null && hash_equals((string) $document->contingut_hash, $actual),
            'apartat_del_motor'  => (bool) $document->apartat_del_motor,
            'motiu'              => match (true) {
                $document->contingut_hash === null => 'Escrit sense signar: encara no té empremta segellada.',
                ! hash_equals((string) $document->contingut_hash, $actual) => 'EL TEXT S\'HA MODIFICAT DESPRÉS DE SIGNAR-LO: no es correspon amb l\'empremta de la signatura.',
                $document->apartat_del_motor => 'Íntegre des de la signatura, però el text signat NO és el que va generar el motor: el va reescriure una persona.',
                default => 'Íntegre: el text signat és el que va generar el motor i no s\'ha tocat des de la signatura.',
            },
        ];
    }

    // ── Evidència des de domi (F1) → elements proposats ───────────────────────
    public function importFromDomi(Request $request)
    {
        $data = $request->validate([
            'professional' => 'required|string|max:64',
            'desde'        => 'nullable|date',
            'fins'         => 'nullable|date',
        ]);
        // Reutilitza el proxy F1 existent: el client (Vue) mostra la vista prèvia i l'admin tria què
        // materialitzar com a element via storeElement (decisió humana element a element).
        return app(ExpedientController::class)->show($request, $data['professional']);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function withComputed(DisciplinaryCase $c): array
    {
        $arr = $c->toArray();
        $arr['dies_prescripcio'] = $this->engine->diesRestants($c->data_prescripcio?->toDateString());

        return $arr;
    }

    /**
     * Estat de cada porta del procediment, per a la vista: quines transicions s'ofereixen i, quan
     * no, per què (en llenguatge natural). El frontend s'hi ha d'ajustar: un botó que sempre falla
     * és pitjor que no tenir-lo.
     *
     * Matís de 'resolt': la guarda exigeix un signant (RGPD 22) i una motivació, i totes dues les
     * aporta la PRÒPIA petició (el formulari de resolució). Avaluada en fred sobre un cas encara
     * sense signar, 'resolt' sortia sempre bloquejat i el botó no s'oferia MAI, ni tan sols des de
     * «comunicat». Per previsualitzar, la guarda s'avalua sobre una còpia en memòria amb el signant
     * ja posat. No es relaxa res: la còpia no es desa i transition() torna a passar la guarda amb
     * les dades reals; la resta de portes (comunicació prèvia, cas tancat, audiència a la RLT,
     * expedient contradictori, autònom) segueixen manant igual aquí.
     */
    private function transicionsPossibles(DisciplinaryCase $case, ?string $actor = null): array
    {
        $out = [];
        foreach (['instruccio', 'comunicat', 'alegacions', 'resolt', 'executat', 'arxivat'] as $e) {
            $subjecte = $case;
            if ($e === 'resolt' && ! $case->resolt_per) {
                $subjecte = clone $case;                       // còpia en memòria: mai es desa
                $subjecte->resolt_per = $actor ?: '(qui signi)';
            }
            [$ok, $motiu] = $this->engine->potTransicio($subjecte, $e);
            $out[] = ['estat' => $e, 'ok' => $ok, 'motiu' => $motiu];
        }

        return $out;
    }

    /**
     * Registre d'AUDITORIA d'un acte del procediment. El llibre de custòdia (disciplinary_case_events)
     * explica l'expedient; audit_logs respon una altra pregunta —qui hi va accedir i des d'on— i és
     * el que la pantalla promet a l'usuari. Mai pot fer caure l'acte que registra.
     */
    private function audita(Request $request, string $accio, string $entitat, ?int $id, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id'     => $request->user()?->id,
                'action'      => $accio,
                'entity_type' => $entitat,
                'entity_id'   => $id,
                'description' => mb_substr($descripcio, 0, 1000),
                'ip_address'  => $request->ip(),
                'user_agent'  => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function logEvent(DisciplinaryCase $cas, ?string $de, ?string $a, string $actor, ?string $nota, ?int $docId = null): void
    {
        DisciplinaryCaseEvent::create([
            'case_id' => $cas->id, 'estat_de' => $de, 'estat_a' => $a,
            'actor' => $actor, 'nota' => $nota, 'document_id' => $docId, 'created_at' => now(),
        ]);
    }
}
