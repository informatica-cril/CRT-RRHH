<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuthorizationCode;
use App\Models\BreakSetting;
use App\Models\User;
use App\Models\WorkLog;
use App\Models\Zone;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Interoperabilitat BIDIRECCIONAL amb la app de domi.
 *
 * Permet que el treballador operi la jornada des de domi (iniciar/tancar jornada,
 * pausa obligatòria) mentre RRHH continua sent la FONT DE VERITAT del registre.
 * Domi identifica el treballador pel DNI; aquí es resol a l'usuari de RRHH i es
 * DELEGA a WorkLogController (mateixa lògica, sense divergència: segmentació,
 * validació de zona, hores efectives, pausa...). Provenència registrada a AuditLog.
 *
 * Autonomia preservada: el treballador pot seguir fent tot directament a RRHH; si
 * domi cau, RRHH funciona igual. Aquests endpoints són una via d'entrada addicional.
 *
 * Accés: NOMÉS el compte de servei (role=service). RestrictServiceRole autoritza
 * aquestes rutes /domi/* concretes; qualsevol altra escriptura segueix bloquejada.
 */
class DomiIntegrationController extends Controller
{
    /**
     * DEFENSA EN PROFUNDITAT: el pont de domi ja talla els autònoms abans d'arribar
     * aquí (api/rrhh_jornada.php), però RRHH és la font de veritat i no es refia del
     * client. Un registre de jornada d'un AUTÒNOM és indici de laboralitat (vegeu
     * domi_crt.gt/docs/PROPOSTA-COLABORADOR-AUTONOM.md §3.2): cap endpoint de
     * jornada no n'hi crea. FAIL-SAFE: esAutonom() només és true amb el valor
     * explícit 'autonom'; NULL = laboral = tot funciona com sempre.
     */
    private function abortIfAutonom(User $worker, string $accio): void
    {
        abort_if($worker->esAutonom(), 409,
            "{$worker->name} és col·laborador autònom: no li aplica el registre de jornada ({$accio}). "
            . 'Les sessions i les firmes es registren a domi igualment.');
    }

    /**
     * Resol el treballador de RRHH. Clau PREFERENT: `user_id` de RRHH (el que domi rep
     * via bulk-index i emmagatzema) → cap dependència de la clau interna de domi
     * (UserName/DNI). Alternativa: `dni`. Així domi enganxa sense fricció.
     */
    private function resolveWorker(?int $userId, ?string $dni): User
    {
        $q = User::where('role', 'worker');
        if ($userId) {
            $worker = (clone $q)->find($userId);
            abort_if(! $worker, 404, "Treballador amb id {$userId} no trobat a RRHH");

            return $worker;
        }
        abort_if(! $dni, 422, 'Cal user_id o dni per identificar el treballador');
        $worker = (clone $q)->where('dni', $dni)->first();
        abort_if(! $worker, 404, "Treballador amb DNI {$dni} no trobat a RRHH");

        return $worker;
    }

    /** Resol per l'identificador de la URL: numèric → id de RRHH; si no → DNI. */
    private function resolveWorkerByIdent(string $ident): User
    {
        return ctype_digit($ident)
            ? $this->resolveWorker((int) $ident, null)
            : $this->resolveWorker(null, $ident);
    }

    private function openLog(User $worker): ?WorkLog
    {
        return WorkLog::where('user_id', $worker->id)
            ->whereNull('end_time')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Construeix una sub-request actuant EN NOM del treballador (per delegar a
     * WorkLogController). Si $as és un FormRequest (store/update esperen
     * Store/UpdateWorkLogRequest), es construeix d'aquesta classe i es VALIDA aquí:
     * en una crida directa (fora del cicle d'injecció) el framework no ho fa sol.
     */
    private function actingAs(array $payload, User $worker, string $as = Request::class): Request
    {
        $sub = $as::create('/', 'POST', $payload);
        $sub->setUserResolver(fn () => $worker);
        if ($sub instanceof \Illuminate\Foundation\Http\FormRequest) {
            $sub->setContainer(app())->setRedirector(app('redirect'));
            $sub->validateResolved();
        }

        return $sub;
    }

    /**
     * REGISTRE MANUAL AMB JUSTIFICACIÓ: quan el treballador decideix no activar el
     * GPS (o no n'hi ha), el registre es fa igualment — la feina assistencial MAI
     * es bloqueja — i la justificació queda traçada com a modificació del fichatge,
     * visible a la traçabilitat de l'app (mètode alternatiu, EIPD §6.4).
     */
    private function logManualJustification(?WorkLog $log, User $worker, string $justificacio, string $context): void
    {
        if (! $log || trim($justificacio) === '') {
            return;
        }
        try {
            \App\Models\WorkLogModification::create([
                'work_log_id' => $log->id,
                'user_id' => $worker->id,
                'action' => 'registre_manual',
                'new_values' => ['context' => $context, 'justificacio' => $justificacio],
                'comment' => "Registre manual sense GPS ({$context}): {$justificacio}",
            ]);
        } catch (\Throwable $e) {
            // la traça no pot bloquejar el registre
        }
    }

    private function logProvenance(Request $request, User $worker, string $action, ?int $workLogId): void
    {
        try {
            \App\Models\AuditLog::create([
                'user_id' => $request->user()?->id, // compte de servei domi
                'action' => $action,
                'entity_type' => 'work_log',
                'description' => "Via domi per treballador DNI {$worker->dni} (#{$worker->id})"
                    . ($workLogId ? " work_log #{$workLogId}" : ''),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // no bloquejar l'operació si falla el log
        }
    }

    /**
     * POST /api/v1/domi/jornada/start
     * Body: { dni, start_location_lat?, start_location_lng?, location_match?, start_location_match?, start_location_distance? }
     */
    public function clockIn(Request $request, WorkLogController $ctrl)
    {
        $data = $request->validate([
            'user_id' => 'required_without:dni|integer',
            'dni' => 'required_without:user_id|string',
            'start_location_lat' => 'nullable|numeric',
            'start_location_lng' => 'nullable|numeric',
            'location_match' => 'nullable|boolean',
            'start_location_match' => 'nullable|boolean',
            'start_location_distance' => 'nullable|numeric',
            'justificacio' => 'nullable|string|min:15|max:500',
        ]);
        $worker = $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
        $this->abortIfAutonom($worker, 'inici de jornada');
        $justificacio = $data['justificacio'] ?? '';
        unset($data['dni'], $data['user_id'], $data['justificacio']);

        $payload = array_merge($data, [
            'user_id' => $worker->id,
            'via_domi' => 1,   // el pont ja ha fet complir la justificació si no hi havia GPS
            'date' => now()->toDateString(),
            'start_time' => now()->toDateTimeString(),
        ]);
        $resp = $ctrl->store($this->actingAs($payload, $worker, \App\Http\Requests\StoreWorkLogRequest::class));
        $id = $resp->getData()->id ?? null;
        $this->logProvenance($request, $worker, 'DOMI_CLOCK_IN', $id);
        if ($id) { $this->logManualJustification(WorkLog::find($id), $worker, $justificacio, 'inici de jornada'); }

        return $resp;
    }

    /**
     * POST /api/v1/domi/jornada/stop
     * Body: { dni, end_location_lat?, end_location_lng?, hours_out_of_area? }
     */
    public function clockOut(Request $request, WorkLogController $ctrl)
    {
        $data = $request->validate([
            'user_id' => 'required_without:dni|integer',
            'dni' => 'required_without:user_id|string',
            'end_location_lat' => 'nullable|numeric',
            'end_location_lng' => 'nullable|numeric',
            'hours_out_of_area' => 'nullable|numeric',
            'justificacio' => 'nullable|string|min:15|max:500',
        ]);
        $worker = $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
        $this->abortIfAutonom($worker, 'fi de jornada');
        $justificacio = $data['justificacio'] ?? '';
        unset($data['dni'], $data['user_id'], $data['justificacio']);

        $log = $this->openLog($worker);
        abort_if(! $log, 409, 'No hi ha cap jornada oberta per tancar');

        $payload = array_merge($data, ['end_time' => now()->toIso8601String()]);
        $resp = $ctrl->update($this->actingAs($payload, $worker, \App\Http\Requests\UpdateWorkLogRequest::class), $log);
        $this->logProvenance($request, $worker, 'DOMI_CLOCK_OUT', $log->id);
        $this->logManualJustification($log, $worker, $justificacio, 'final de jornada');

        return $resp;
    }

    /**
     * GET /api/v1/domi/break-status/{dni}
     * Perquè domi sàpiga si ha de fer saltar el modal de pausa a l'app que el treballador té oberta.
     */
    /**
     * Perfil del treballador per a l'atribució i enrutament de pacients a domi:
     * especialitats clíniques, zones geogràfiques actives (CP/municipi) i horari.
     * Les absències/vacances (disponibilitat per data) es consulten a part.
     */
    public function perfil(string $ident)
    {
        $worker = $this->resolveWorkerByIdent($ident);
        $today = now()->toDateString();

        $zones = $worker->zones()
            ->where(fn ($q) => $q->whereNull('zone_worker.valid_from')->orWhere('zone_worker.valid_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('zone_worker.valid_to')->orWhere('zone_worker.valid_to', '>=', $today))
            ->get()
            ->map(fn ($z) => [
                'type' => $z->type,
                'postal_codes' => $z->postal_codes,
                'municipalities' => $z->municipalities,
                'province' => $z->province,
            ])->values();

        // Disponibilitat: absències/vacances vigents o futures (no rebutjades),
        // excedències actives i festius -> domi en calcula els forats i la sobrecàrrega.
        $absencies = \App\Models\Absence::where('user_id', $worker->id)
            ->where('approved', '!=', false)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->get(['start_date', 'end_date', 'approved'])
            ->map(fn ($a) => [
                'start' => (string) $a->start_date,
                'end' => (string) $a->end_date,
                'aprovat' => $a->approved !== null ? (bool) $a->approved : null,
            ])->values();
        $excedencies = \App\Models\Excedencia::where('user_id', $worker->id)
            ->whereIn('status', ['approved', 'active'])
            ->get(['start_date', 'reincorporation_date', 'end_date'])
            ->map(fn ($e) => [
                'start' => (string) $e->start_date,
                'end' => (string) ($e->reincorporation_date ?: $e->end_date),
            ])->values();
        $festius = \App\Models\Holiday::where('year', '>=', now()->year)->orderBy('date')->pluck('date')->values();

        return response()->json([
            'user_id' => $worker->id,
            'dni' => $worker->dni,
            'device_phone' => $worker->device_phone,   // SIM de la tablet (empresa) -> wa.me del fisio a domi
            'work_type' => $worker->work_type,
            // Tipus de relació: NULL = no classificat = domi el tracta com a LABORAL
            // (mateix fail-safe que crt_relacio()). L'autònom no fitxa i la seva
            // disponibilitat setmanal substitueix el work_schedule.
            'relacio' => $worker->relacio,
            'disponibilitat_setmanal' => $worker->disponibilitat_setmanal !== null
                ? (float) $worker->disponibilitat_setmanal : null,
            'specialties' => $worker->specialties()->orderBy('sort')->pluck('code')->values(),
            'zones' => $zones,
            'work_schedule' => $worker->workSchedule?->days,
            'disponibilitat' => [
                'absencies' => $absencies,
                'excedencies' => $excedencies,
                'festius' => $festius,
            ],
            // Hores COMPLEMENTÀRIES (art. 12.5 ET): pacte + saldo anual, per a la
            // planificació de franges i extensions de sessions a domi.
            // PAUSA (art. 34.4 ET). RRHH n'es la font: durada, llindar i COL.LOCACIO.
            // domi la necessita en temps de planificacio -no de fitxatge- per reservar
            // el bloc al mig de la jornada, i per aixo va al perfil i no a breakStatus.
            'pausa' => $this->pausaPerPlanificacio($worker),
            // Jornada completa: cap complementària (només extraordinàries, que domi
            // gestiona per la bossa); el pacte no té efecte encara que consti.
            'complementaries' => $worker->isFullTime()
                ? ['pacte' => false, 'jornada_completa' => true, 'nomes_extraordinaries' => true,
                   'max_anual_h' => 0, 'cap_planificacio_h' => 0, 'consumides_h' => 0]
                : (['pacte' => (bool) $worker->pacte_complementaries, 'jornada_completa' => false]
                   + $worker->complementaryStats()),
        ]);
    }

    public function breakStatus(Request $request, string $ident)
    {
        $worker = $this->resolveWorkerByIdent($ident);
        $this->abortIfAutonom($worker, 'estat de pausa');
        $log = $this->openLog($worker);
        $settings = BreakSetting::getSettings();

        $out = [
            'has_open_jornada' => (bool) $log,
            'break_status' => $log?->break_status,
            'break_required' => (bool) ($log?->break_required),
            'break_duration_minutes' => $settings->break_duration_minutes,
            'break_due' => false,
            'trigger_time' => null,
        ];

        if (! $log || ! $settings->enabled
            || in_array($log->break_status, ['active', 'completed', 'skipped'], true)) {
            return response()->json($out);
        }

        // Llindar: jornada prevista avui > threshold (excepte override del treballador)
        $scheduledHours = $this->scheduledHoursToday($worker);
        if ($worker->break_override !== true && $scheduledHours <= $settings->threshold_hours) {
            return response()->json($out);
        }

        $trigger = $this->breakTriggerTime($log, $worker, $settings);
        $out['trigger_time'] = $trigger?->toIso8601String();
        $out['break_due'] = $trigger && now()->gte($trigger);

        return response()->json($out);
    }

    /** POST /api/v1/domi/break/start  Body: { user_id | dni } */
    public function startBreak(Request $request, WorkLogController $ctrl)
    {
        $worker = $this->resolveWorkerFromBody($request);
        $this->abortIfAutonom($worker, 'inici de pausa');
        $log = $this->openLog($worker);
        abort_if(! $log, 409, 'No hi ha cap jornada oberta');

        return $ctrl->startBreak($this->actingAs([], $worker), $log);
    }

    /** POST /api/v1/domi/break/complete  Body: { user_id | dni } */
    public function completeBreak(Request $request, WorkLogController $ctrl)
    {
        $worker = $this->resolveWorkerFromBody($request);
        $this->abortIfAutonom($worker, 'fi de pausa');
        $log = $this->openLog($worker);
        abort_if(! $log, 409, 'No hi ha cap jornada oberta');

        return $ctrl->completeBreak($this->actingAs([], $worker), $log);
    }

    private function resolveWorkerFromBody(Request $request): User
    {
        $data = $request->validate([
            'user_id' => 'required_without:dni|integer',
            'dni' => 'required_without:user_id|string',
        ]);

        return $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
    }

    /**
     * POST /api/v1/domi/hito — HITO DE SERVEI en temps real (arribada/sortida de visita).
     *
     * domi alimenta el registre; RRHH CATEGORITZA. Les coordenades del PACIENT mai
     * surten de domi: domi calcula la distància amb el seu OSRM local i envia només
     * el veredicte (radi_ok / dist_domicili_m). Els hitos PARTICIONEN la jornada en
     * segments 'visita' (arribada→sortida) i 'desplacament' (entre visites), de manera
     * que el temps de desplaçament també compta com a temps efectiu aprovable.
     *
     * Anti-retroactivitat: el hito ha de ser en viu (±15 min del servidor). El registre
     * retroactiu de firmes d'assistència es queda a domi (facturació); un hito tardà
     * no és prova de presència i es rebutja amb 422.
     */
    /**
     * POST /api/v1/domi/zones — upsert d'una zona/microzona DECIDIDA A DOMI.
     *
     * Coordinació (o Administració/Direcció) crea la sectorització a domi — CP
     * sencers o subzones dibuixades — i es publica aquí com a zona ASSIGNABLE:
     * RRHH hi penja treballadors amb zone_worker i el bulk-index la retorna a domi,
     * que és qui pinta cobertura. Clau d'upsert: domi_microzona_id. La geometria
     * fina no viatja; els postal_codes sí, perquè el càlcul genèric per CP i la
     * derivació de territori per treballador segueixin funcionant igual.
     */
    public function upsertZone(Request $request)
    {
        $data = $request->validate([
            'domi_microzona_id' => 'required|integer|min:1',
            'name' => 'required|string|max:255',
            'postal_codes' => 'array',
            'postal_codes.*' => 'string|max:5',
            'municipalities' => 'array',
            'municipalities.*' => 'string|max:100',
            'province' => 'nullable|string|max:100',
            'subzona' => 'boolean',
            'active' => 'boolean',
        ]);
        $zone = Zone::withTrashed()->firstOrNew(['domi_microzona_id' => $data['domi_microzona_id']]);
        if ($zone->trashed()) {
            $zone->restore();
        }
        $cps = array_values($data['postal_codes'] ?? []);
        $munis = array_values($data['municipalities'] ?? []);
        $zone->fill([
            'name' => $data['name'],
            'type' => ! empty($data['subzona']) ? 'SUBZONA' : ($cps ? 'CP' : 'MUNICIPALITY'),
            'province' => $data['province'] ?? ($zone->province ?: 'Barcelona'),
            'postal_codes' => $cps,
            'municipalities' => $munis,
            'active' => (bool) ($data['active'] ?? true),
        ])->save();

        return response()->json(['ok' => true, 'zone_id' => $zone->id, 'active' => $zone->active]);
    }

    /**
     * GET /api/v1/domi/vacances — peticions de vacances vigents o futures.
     *
     * Per al pla de vacances de cobertura (només el llegeixen Direcció/Administració
     * a domi): identificador, nom, finestra i estat d'aprovació. Cap altre motiu
     * d'absència viatja per aquí — el motiu és dada privada i domi no l'ha de veure
     * (regla existent: a domi només arriba QUE no hi és, mai per què).
     */
    public function vacancesPendents()
    {
        $files = \App\Models\Absence::with('user:id,name')
            ->where('absence_type_id', 1)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->get()
            ->map(fn ($a) => [
                'user_id' => $a->user_id,
                'nom' => $a->user?->name,
                'inici' => $a->start_date->toDateString(),
                'fi' => $a->end_date->toDateString(),
                'aprovada' => $a->approved,
            ])->values();

        return response()->json(['vacances' => $files]);
    }

    /**
     * POST /api/v1/domi/geovalla — la geovalla de fitxatge del fisio domiciliari
     * LA DECIDEIX Coordinació a domi (microzones i CP del seu territori) i es
     * trasllada aquí. Si existeix, mana sobre el càlcul per CP genèric.
     */
    public function geovalla(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required_without:dni|integer',
            'dni' => 'required_without:user_id|string',
            'cps' => 'array',
            'cps.*' => 'string|max:5',
            'poligons' => 'array',
        ]);
        $worker = $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
        foreach (($data['poligons'] ?? []) as $poly) {
            if (! is_array($poly) || count($poly) < 3) {
                return response()->json(['message' => 'Polígon invàlid'], 422);
            }
        }
        \App\Models\WorkerGeofence::updateOrCreate(
            ['user_id' => $worker->id],
            ['cps' => array_values($data['cps'] ?? []), 'poligons' => array_values($data['poligons'] ?? []), 'font' => 'domi']
        );
        return response()->json(['ok' => true, 'cps' => count($data['cps'] ?? []), 'poligons' => count($data['poligons'] ?? [])]);
    }

    private const HITO_MAX_SKEW_MIN = 15;

    public function hito(Request $request, WorkLogController $ctrl)
    {
        $data = $request->validate([
            'user_id' => 'required_without:dni|integer',
            'dni' => 'required_without:user_id|string',
            'tipus' => 'required|in:arribada,sortida',
            'moment' => 'required|date',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
            'dist_domicili_m' => 'nullable|numeric',
            'radi_efectiu_m' => 'nullable|numeric',
            'radi_ok' => 'nullable|boolean',
            'ref' => 'nullable|string|max:100',
            'justificacio' => 'nullable|string|min:15|max:500',
        ]);
        $worker = $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
        $this->abortIfAutonom($worker, 'hito de servei');

        $moment = Carbon::parse($data['moment'])->utc();
        if (abs(now()->diffInMinutes($moment, false)) > self::HITO_MAX_SKEW_MIN) {
            return response()->json([
                'message' => 'Hito fora de temps real (±' . self::HITO_MAX_SKEW_MIN . ' min). '
                    . 'El registre retroactiu no és vàlid com a verificació de presència.',
            ], 422);
        }

        $verif = match ($data['radi_ok'] ?? null) {
            true => 'verificat',
            false => 'fora_radi',
            default => 'no_disponible',
        };

        $log = $this->openLog($worker);
        if (! $log) {
            if ($data['tipus'] === 'sortida') {
                abort(409, 'No hi ha cap jornada oberta: sortida sense arribada prèvia');
            }
            // Primera arribada del dia → obre jornada automàticament (Modo A per hitos)
            // C2 EIPD: obertura de jornada sense coordenades — només el veredicte.
            // via_domi=1: el pont (domi) ja ha fet complir la justificació si no hi
            // havia GPS, igual que a clockIn; sense això store() exigiria justificació
            // i el primer hito no podria obrir la jornada (validation.required_without_all).
            $ctrl->store($this->actingAs([
                'user_id' => $worker->id,
                'via_domi' => 1,
                'date' => now()->toDateString(),
                'start_time' => now()->toDateTimeString(),
                'start_location_match' => $data['radi_ok'] ?? null,
            ], $worker, \App\Http\Requests\StoreWorkLogRequest::class));
            $log = $this->openLog($worker);
            abort_if(! $log, 500, 'No s\'ha pogut obrir la jornada');
        }

        // Hitos arribant = domi online → Mode A; segmented=true evita que el tancament
        // faci la segmentació automàtica per sobre dels segments d'hitos.
        $log->forceFill(['verification_mode' => 'A', 'segmented' => true])->save();

        $inSchedule = $this->isScheduledDayPublic($worker, $moment->copy())
            || $this->hitoHasAuthCode($worker, $moment->copy());

        // CONVENCIÓ BD: les hores es guarden com a strings UTC. Els casts d'Eloquent les
        // llegeixen en tz d'app (Madrid) — per a DIFERÈNCIES cal parsejar el RAW com UTC,
        // si no cada durada surt inflada exactament amb l'offset (bug de 120 min).
        $segments = \App\Models\WorkLogSegment::where('work_log_id', $log->id)
            ->orderBy('segment_number')->get();
        $rawLastEnd = $segments->map(fn ($s) => $s->getRawOriginal('end_time'))->filter()->max();
        $lastBoundary = $rawLastEnd
            ? Carbon::parse($rawLastEnd, 'UTC')
            : Carbon::parse($log->getRawOriginal('start_time'), 'UTC');
        $nextNum = ((int) $segments->max('segment_number')) + 1;

        // Visita "provisional" oberta = kind visita amb end_time == start_time
        $openVisit = $segments->last(fn ($s) => $s->kind === 'visita' && $s->status === 'pending' && $s->end_time->eq($s->start_time));

        if ($data['tipus'] === 'arribada') {
            abort_if($openVisit, 409, 'Ja hi ha una visita oberta (falta la sortida anterior)');
            // Tancar el desplaçament previ (del final anterior fins ara)
            if ($lastBoundary->lt($moment)) {
                \App\Models\WorkLogSegment::create([
                    'work_log_id' => $log->id, 'segment_number' => $nextNum++,
                    'kind' => 'desplacament',
                    'start_time' => $lastBoundary, 'end_time' => $moment,
                    'in_zone' => true, 'in_schedule' => $inSchedule,
                    'duration_minutes' => (int) $lastBoundary->diffInMinutes($moment),
                    'status' => $inSchedule ? 'approved' : 'pending',
                ]);
            }
            // Obrir la visita (provisional: end=start; la sortida la tanca)
            // C2 EIPD: sense coordenades — la posició resideix a Domiciliària
            $seg = \App\Models\WorkLogSegment::create([
                'work_log_id' => $log->id, 'segment_number' => $nextNum,
                'kind' => 'visita', 'ref' => $data['ref'] ?? null,
                'start_time' => $moment, 'end_time' => $moment,
                'in_zone' => (bool) ($data['radi_ok'] ?? true), 'in_schedule' => $inSchedule,
                'home_verification' => $verif,
                'home_distance_m' => isset($data['dist_domicili_m']) ? (int) round($data['dist_domicili_m']) : null,
                'home_radius_m' => isset($data['radi_efectiu_m']) ? (int) round($data['radi_efectiu_m']) : null,
                // La justificació del registre manual de l'ARRIBADA es conserva al tram:
                // si la visita acaba pendent, entrarà com a al·legació inicial de l'audiència
                'allegation' => ! empty($data['justificacio']) ? $data['justificacio'] : null,
                'allegation_at' => ! empty($data['justificacio']) ? now() : null,
                'duration_minutes' => 0, 'status' => 'pending',
            ]);
        } else { // sortida
            if ($openVisit) {
                // El pitjor veredicte dels dos hitos mana (arribada verificada però
                // sortida fora de radi → fora_radi)
                $rank = ['verificat' => 0, 'no_disponible' => 1, 'fora_radi' => 2];
                $worst = ($rank[$verif] ?? 1) > ($rank[$openVisit->home_verification] ?? 1)
                    ? $verif : $openVisit->home_verification;
                $inZone = $openVisit->in_zone && (bool) ($data['radi_ok'] ?? true);
                $visitStart = Carbon::parse($openVisit->getRawOriginal('start_time'), 'UTC');
                // Evidència per a l'audiència: es conserva la PITJOR distància dels dos hitos
                $newDist = isset($data['dist_domicili_m']) ? (int) round($data['dist_domicili_m']) : null;
                $keepWorst = $newDist !== null && ($openVisit->home_distance_m === null || $newDist > $openVisit->home_distance_m);
                $openVisit->update([
                    'end_time' => $moment,
                    // C2 EIPD: cap coordenada bruta al sistema laboral, encara que arribés
                    'home_verification' => $worst,
                    'home_distance_m' => $keepWorst ? $newDist : $openVisit->home_distance_m,
                    'home_radius_m' => $keepWorst && isset($data['radi_efectiu_m'])
                        ? (int) round($data['radi_efectiu_m']) : $openVisit->home_radius_m,
                    'in_zone' => $inZone,
                    'duration_minutes' => (int) $visitStart->diffInMinutes($moment),
                    // EIPD VI.B.5: només 'verificat' s'aprova automàticament; fora_radi i
                    // no_disponible queden pendents i activen el protocol d'audiència
                    'status' => ($worst === 'verificat' && $inZone && $openVisit->in_schedule) ? 'approved' : 'pending',
                ]);
                $seg = $openVisit->fresh();
            } else {
                // Sortida sense arribada (oblit): visita des de l'últim límit — lenient
                // però marcada pendent perquè un humà la revisi.
                $seg = \App\Models\WorkLogSegment::create([
                    'work_log_id' => $log->id, 'segment_number' => $nextNum,
                    'kind' => 'visita', 'ref' => $data['ref'] ?? null,
                    'start_time' => $lastBoundary, 'end_time' => $moment,
                    'in_zone' => (bool) ($data['radi_ok'] ?? true), 'in_schedule' => $inSchedule,
                    'home_verification' => $verif,
                    'home_distance_m' => isset($data['dist_domicili_m']) ? (int) round($data['dist_domicili_m']) : null,
                    'home_radius_m' => isset($data['radi_efectiu_m']) ? (int) round($data['radi_efectiu_m']) : null,
                    'duration_minutes' => (int) $lastBoundary->diffInMinutes($moment),
                    'status' => 'pending',
                ]);
            }

            // PROTOCOL D'AUDIÈNCIA (EIPD C6): tota visita que queda pendent obre
            // audiència immediatament — termini fixat per l'empresa i treballador
            // notificat. La justificació del registre manual, si n'hi ha, entra com
            // a al·legació inicial.
            if ($seg->status === 'pending') {
                \App\Http\Controllers\Api\WorkLogSegmentController::obrirAudiencia($seg, $data['justificacio'] ?? null);
                $seg = $seg->fresh();
            }
        }

        // Categorització agregada del work_log: el pitjor hito del dia mana
        $rank = ['verificat' => 0, 'pendent' => 1, 'no_disponible' => 2, 'fora_radi' => 3];
        $current = $log->home_verification ?? 'verificat';
        if (($rank[$verif] ?? 1) > ($rank[$current] ?? 0)) {
            $log->forceFill(['home_verification' => $verif])->save();
        } elseif ($log->home_verification === null) {
            $log->forceFill(['home_verification' => $verif])->save();
        }

        // Registre d'ubicació del treballador (xifrat) + traça de provinença
        // Estadística del pilot (Annex V): NOMÉS la precisió del sensor — cap coordenada
        // bruta entra al sistema laboral (C2 EIPD).
        if (isset($data['accuracy'])) {
            try {
                \App\Models\LocationTracking::create([
                    'user_id' => $worker->id,
                    'accuracy' => $data['accuracy'],
                    'event_type' => 'hito_' . $data['tipus'],
                ]);
            } catch (\Throwable $e) {
            }
        }
        $this->logProvenance($request, $worker, 'DOMI_HITO_' . strtoupper($data['tipus']), $log->id);
        $this->logManualJustification($log, $worker, $data['justificacio'] ?? '', 'hito ' . $data['tipus']);

        $log->refresh()->recalcularEfectivo();

        return response()->json([
            'work_log_id' => $log->id,
            'segment' => $seg->fresh(),
            'home_verification' => $verif,
            'verification_mode' => 'A',
        ], 201);
    }

    /**
     * GET /api/v1/domi/incidencies-obertes — refs de visita amb incidència OBERTA
     * (tram pendent d'audiència o fichatge amb alerta no resolta). La purga de
     * coordenades de Domiciliària EXCLOU aquests refs: la dada es manté mentre
     * s'estudia la incidència (EIPD Annex V), i es purga quan es resol.
     */
    /**
     * GET /api/v1/domi/hores?desde=Y-m-d&fins=Y-m-d
     * Hores efectives treballades per dia i treballador, perquè el planificador
     * de la Domiciliària porti la bossa anual d'hores contra el registre real.
     * Minimització: només DNI (per conciliar, com la resta de syncs), dia i hores;
     * mai el detall de fitxatges ni el motiu de res.
     */
    public function hores(Request $request)
    {
        $data = $request->validate([
            'desde' => 'required|date_format:Y-m-d',
            'fins' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);
        if (Carbon::parse($data['desde'])->diffInDays(Carbon::parse($data['fins'])) > 400) {
            return response()->json(['error' => 'rang massa gran'], 422);
        }

        $files = WorkLog::query()
            ->whereBetween('date', [$data['desde'], $data['fins']])
            ->whereNotNull('effective_hours')
            ->join('users', 'users.id', '=', 'work_logs.user_id')
            ->whereNotNull('users.dni')
            ->groupBy('users.dni', 'work_logs.date')
            ->selectRaw('users.dni, work_logs.date dia, SUM(work_logs.effective_hours) hores')
            ->get()
            ->map(fn ($r) => [
                'dni' => $r->dni,
                'dia' => Carbon::parse($r->dia)->toDateString(),
                'hores' => round((float) $r->hores, 2),
            ]);

        return response()->json(['hores' => $files]);
    }

    /**
     * POST /api/v1/domi/extraordinaries
     * Domi (Coordinació) força hores extraordinàries per a un fisio i les envia a RRHH
     * per autoritzar ABANS de programar-les. Es crea una sol·licitud PENDENT: fins que
     * RRHH no l'autoritzi (compromís de pagament), domi no programa res.
     */
    public function overtimeCreate(Request $request)
    {
        $data = $request->validate([
            'dni' => 'required_without:user_id|string',
            'user_id' => 'required_without:dni|integer',
            'hours' => 'required|numeric|min:0.25|max:80',
            'period_from' => 'required|date_format:Y-m-d',
            'period_to' => 'required|date_format:Y-m-d|after_or_equal:period_from',
            'requested_by' => 'required|string|max:120',
            'reason' => 'nullable|string|max:500',
            'domi_ref' => 'nullable|string|max:120',
        ]);
        $worker = $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
        $this->abortIfAutonom($worker, 'hores extraordinàries');

        // Idempotència: si domi reenvia el mateix domi_ref, no dupliquem.
        $req = null;
        if (! empty($data['domi_ref'])) {
            $req = \App\Models\OvertimeRequest::where('domi_ref', $data['domi_ref'])->first();
        }
        if (! $req) {
            $req = \App\Models\OvertimeRequest::create([
                'user_id' => $worker->id,
                'hours' => $data['hours'],
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'requested_by' => $data['requested_by'],
                'reason' => $data['reason'] ?? null,
                'domi_ref' => $data['domi_ref'] ?? null,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'ok' => true, 'request_id' => $req->id, 'status' => $req->status,
        ], 201);
    }

    /**
     * POST /api/v1/domi/complementaries
     * El fisio parcial ha ACCEPTAT hores complementàries a domi. El pacte ja les
     * autoritza: aquí es crea directament el codi (factor 1,00) perquè el comptador
     * les reculli a l'acte i el fitxatge pugui classificar els minuts treballats.
     */
    public function complementaryCreate(Request $request)
    {
        $data = $request->validate([
            'dni' => 'required_without:user_id|string',
            'user_id' => 'required_without:dni|integer',
            'hours' => 'required|numeric|min:0.25|max:500',
            'period_from' => 'required|date_format:Y-m-d',
            'period_to' => 'required|date_format:Y-m-d|after_or_equal:period_from',
            'accepted_by' => 'required|string|max:120',
            'reason' => 'nullable|string|max:500',
            'domi_ref' => 'nullable|string|max:120',
        ]);
        $worker = $this->resolveWorker($data['user_id'] ?? null, $data['dni'] ?? null);
        $this->abortIfAutonom($worker, 'hores complementàries');
        if ($worker->isFullTime()) {
            return response()->json(['error' => 'Jornada completa: no fa complementàries (només extraordinàries).'], 422);
        }
        if (! (bool) $worker->pacte_complementaries) {
            return response()->json(['error' => 'Sense pacte de complementàries vigent.'], 422);
        }

        if (! empty($data['domi_ref'])) {
            $ja = AuthorizationCode::where('domi_origen', $data['domi_ref'])->first();
            if ($ja) {
                return response()->json(['ok' => true, 'code' => $ja->code, 'code_id' => $ja->id], 200);
            }
        }

        // Tope del pacte: mai per sobre del màxim anual usable. Sense topall calculable
        // (jornada no sincronitzada) no es valida cap hora: compromís de pagament.
        $stats = $worker->complementaryStats((int) substr($data['period_from'], 0, 4));
        $capH = (float) ($stats['max_anual_h'] ?? 0);
        if ($capH <= 0) {
            return response()->json(['error' => 'Topall de complementàries no calculable (jornada sense sincronitzar): no es poden validar hores.'], 422);
        }
        $jaCompl = round((float) AuthorizationCode::where('user_id', $worker->id)
            ->where('type', '!=', 'extraordinaria')->where('revoked', false)
            ->whereYear('valid_from', (int) substr($data['period_from'], 0, 4))
            ->sum('authorized_hours'), 2);
        if ($jaCompl + (float) $data['hours'] > $capH + 0.001) {
            return response()->json(['error' => sprintf(
                'Superaria el màxim anual de complementàries: ja n\'hi ha %.2f h de %.2f, i se\'n demanen %.2f més.',
                $jaCompl, $capH, (float) $data['hours'])], 422);
        }

        $code = AuthorizationCode::create([
            'code' => strtoupper(bin2hex(random_bytes(4))),
            'concept' => 'Hores complementàries (pacte art. 12.5) · acceptades pel treballador a domi: ' . $data['accepted_by'],
            'authorized_hours' => $data['hours'],
            'type' => 'complementaria',
            'factor' => 1.00,
            'domi_origen' => $data['domi_ref'] ?? null,
            'generated_by' => $request->user()->id,
            'user_id' => $worker->id,
            'valid_from' => $data['period_from'],
            'valid_to' => $data['period_to'],
        ]);
        return response()->json(['ok' => true, 'code' => $code->code, 'code_id' => $code->id], 201);
    }

    /**
     * GET /api/v1/domi/complementaries/pacte/{ident}
     * El codi de PACTE vigent del treballador (complementàries, art. 12.5): si existeix,
     * el pacte signat ja és l'acceptació i el motor de domi pot programar-hi directament,
     * sense tornar a demanar res al fisio. Torna el codi i el marge que hi queda segons
     * el registre horari.
     */
    public function complementaryPacte(string $ident)
    {
        $worker = $this->resolveWorkerByIdent($ident);
        if ($worker->isFullTime() || ! (bool) $worker->pacte_complementaries) {
            return response()->json(['error' => 'Sense pacte de complementàries vigent.'], 404);
        }
        $code = AuthorizationCode::where('user_id', $worker->id)
            ->where('type', '!=', 'extraordinaria')
            ->where('revoked', false)
            ->whereDate('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now()))
            ->orderByDesc('authorized_hours')
            ->first();
        if (! $code) {
            return response()->json(['error' => 'Cap codi de pacte donat d\'alta.'], 404);
        }
        $usat = round((float) \Illuminate\Support\Facades\DB::table('work_logs')
            ->where('authorized_extra_code', $code->code)
            ->selectRaw('SUM(COALESCE(NULLIF(extra_hours_authorized,0), complementary_minutes/60, 0)) h')
            ->value('h'), 2);
        return response()->json([
            'code' => $code->code,
            'hours' => (float) $code->authorized_hours,
            'usat_h' => $usat,
            'restant_h' => round(max(0, (float) $code->authorized_hours - $usat), 2),
            'valid_to' => optional($code->valid_to)->toDateString(),
        ]);
    }

    /**
     * GET /api/v1/domi/extraordinaries/{ref}
     * Domi consulta l'estat d'una sol·licitud (per domi_ref o id) per saber si ja pot programar.
     */
    public function overtimeStatus(string $ref)
    {
        $req = ctype_digit($ref)
            ? \App\Models\OvertimeRequest::find((int) $ref)
            : \App\Models\OvertimeRequest::where('domi_ref', $ref)->latest()->first();
        abort_if(! $req, 404, 'Sol·licitud no trobada');

        return response()->json([
            'ok' => true, 'request_id' => $req->id, 'status' => $req->status,
            'hours' => (float) $req->hours,
            'authorized_at' => optional($req->authorized_at)->toIso8601String(),
            'authorization_code_id' => $req->authorization_code_id,
            'denial_reason' => $req->denial_reason,
        ]);
    }

    /**
     * GET /api/v1/domi/festius
     * Calendari laboral vigent (any actual i següent) perquè domi el repliqui a
     * DIAS_FESTIVOS i el planificador no hi programi visites.
     */
    public function festius()
    {
        return response()->json([
            'festius' => \App\Models\Holiday::whereIn('year', [now()->year, now()->year + 1])
                ->orderBy('date')->get(['date', 'name'])
                ->map(fn ($h) => ['data' => substr((string) $h->date, 0, 10), 'nom' => $h->name]),
        ]);
    }

    /**
     * POST /api/v1/domi/pla-jornada
     * El planificador de domi empeny el pla anual teòric de jornada (bossa de
     * 1726 h repartida): per dia, jornada que toca i hora de sortida teòrica.
     * Es replica aquí perquè el treballador ho vegi al seu calendari laboral.
     * Conciliació per DNI, com la resta de syncs; s'esborra el futur de cada
     * treballador abans d'inserir perquè el pla nou substitueixi el vell.
     */
    public function plaJornada(Request $request)
    {
        $data = $request->validate([
            'pla' => 'required|array|max:20000',
            'pla.*.dni' => 'required|string|max:16',
            'pla.*.dia' => 'required|date_format:Y-m-d',
            'pla.*.jornada_min' => 'required|integer|min:0|max:1440',
            'pla.*.sortida' => 'nullable|date_format:H:i',
            'pla.*.finestra_fi' => 'nullable|date_format:H:i',
            'pla.*.surt_abans_min' => 'nullable|integer|min:0|max:1440',
        ]);

        $perDni = User::whereNotNull('dni')->pluck('id', 'dni')
            ->mapWithKeys(fn ($id, $dni) => [strtoupper(str_replace(['-', ' ', '.'], '', $dni)) => $id]);

        $files = [];
        $usuaris = [];
        foreach ($data['pla'] as $f) {
            $dni = strtoupper(str_replace(['-', ' ', '.'], '', $f['dni']));
            $uid = $perDni[$dni] ?? null;
            if ($uid === null) {
                continue;
            }
            $usuaris[$uid] = true;
            $files[] = [
                'user_id' => $uid, 'date' => $f['dia'],
                'jornada_min' => (int) $f['jornada_min'],
                'sortida' => $f['sortida'] ?? null,
                'finestra_fi' => $f['finestra_fi'] ?? null,
                'surt_abans_min' => (int) ($f['surt_abans_min'] ?? 0),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($files, $usuaris) {
            if ($usuaris) {
                \Illuminate\Support\Facades\DB::table('domi_jornada_pla')
                    ->whereIn('user_id', array_keys($usuaris))
                    ->where('date', '>=', now()->toDateString())
                    ->delete();
            }
            foreach (array_chunk($files, 500) as $chunk) {
                \Illuminate\Support\Facades\DB::table('domi_jornada_pla')->insert($chunk);
            }
        });

        return response()->json(['ok' => true, 'rebuts' => count($data['pla']), 'inserits' => count($files)]);
    }

    /** POST /api/v1/domi/rendiment */
    public function rendiment(Request $request)
    {
        $data = $request->validate([
            'periode_desde' => 'required|date_format:Y-m-d',
            'periode_fins' => 'required|date_format:Y-m-d',
            'files' => 'required|array|max:500',
            'files.*.dni' => 'nullable|string|max:16',
            'files.*.user_id' => 'nullable|integer',
            'files.*.sessions_firmades' => 'nullable|integer|min:0',
            'files.*.compliment_pct' => 'nullable|integer|min:0|max:100',
            'files.*.documental_pct' => 'nullable|integer|min:0|max:100',
            'files.*.altes_sense_informe' => 'nullable|integer|min:0',
            'files.*.processos_tancats' => 'nullable|integer|min:0',
            'files.*.adherencia_pct' => 'nullable|integer|min:0|max:100',
            'files.*.processos_sota_70pct' => 'nullable|integer|min:0',
            'files.*.puntualitat_pct' => 'nullable|integer|min:0|max:100',
            'files.*.retard_mitja_min' => 'nullable|integer|min:0',
            'files.*.aportacions' => 'nullable|integer|min:0',
            'files.*.prou_mostra' => 'nullable|boolean',
            'files.*.agraiments' => 'nullable|integer|min:0',
            'files.*.queixes' => 'nullable|integer|min:0',
            'mitjana' => 'nullable|array',
        ]);

        $perDni = User::whereNotNull('dni')->pluck('id', 'dni')
            ->mapWithKeys(fn ($id, $dni) => [strtoupper(str_replace(['-', ' ', '.'], '', $dni)) => $id]);

        $camps = ['sessions_firmades', 'compliment_pct', 'documental_pct', 'altes_sense_informe',
            'processos_tancats', 'adherencia_pct', 'processos_sota_70pct', 'puntualitat_pct',
            'retard_mitja_min', 'aportacions', 'agraiments', 'queixes'];

        $files = [];
        $noConciliats = [];
        foreach ($data['files'] as $f) {
            $uid = $f['user_id'] ?? null;
            if (! $uid && ! empty($f['dni'])) {
                $uid = $perDni[strtoupper(str_replace(['-', ' ', '.'], '', $f['dni']))] ?? null;
            }
            if (! $uid) {
                $noConciliats[] = $f['dni'] ?? ('user_id ' . ($f['user_id'] ?? '?'));
                continue;
            }
            $fila = ['user_id' => $uid,
                'periode_desde' => $data['periode_desde'], 'periode_fins' => $data['periode_fins'],
                'prou_mostra' => (bool) ($f['prou_mostra'] ?? false),
                'created_at' => now(), 'updated_at' => now()];
            foreach ($camps as $c) {
                $fila[$c] = isset($f[$c]) ? (int) $f[$c] : null;
            }
            $fila['sessions_firmades'] = (int) ($f['sessions_firmades'] ?? 0);
            $fila['altes_sense_informe'] = (int) ($f['altes_sense_informe'] ?? 0);
            $files[] = $fila;
        }

        if ($files) {
            \Illuminate\Support\Facades\DB::table('domi_rendiment')->upsert($files,
                ['user_id', 'periode_desde', 'periode_fins'],
                array_merge($camps, ['prou_mostra', 'updated_at']));
        }

        $m = $data['mitjana'] ?? null;
        if ($m) {
            \Illuminate\Support\Facades\DB::table('domi_rendiment_mitjana')->upsert([[
                'periode_desde' => $data['periode_desde'], 'periode_fins' => $data['periode_fins'],
                'n' => (int) ($m['n'] ?? 0),
                'sessions_firmades' => $m['sessions_firmades'] ?? null,
                'compliment_pct' => $m['compliment_pct'] ?? null,
                'documental_pct' => $m['documental_pct'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
            ]], ['periode_desde', 'periode_fins'],
                ['n', 'sessions_firmades', 'compliment_pct', 'documental_pct', 'updated_at']);
        }

        return response()->json(['ok' => true, 'files' => count($files), 'no_conciliats' => $noConciliats]);
    }

    /** GET /api/v1/domi/sancions-rlt */
    public function sancionsRlt(Request $request)
    {
        $any = (int) $request->input('any', now()->year);
        $casos = \App\Models\DisciplinaryCase::where('gravetat', 'molt_greu')
            ->where('estat', '!=', 'arxivat')
            ->whereNotNull('resolucio_tipus')
            ->where('resolucio_tipus', '!=', 'arxiu')
            ->whereYear('updated_at', $any)
            ->get();

        $files = $casos->map(fn ($c) => [
            'tipus_falta' => $c->tipus_falta,
            'sancio' => \App\Support\ConveniSancions::etiqueta((string) $c->resolucio_tipus),
            'dies' => $c->resolucio_dies,
            'data' => optional($c->updated_at)->toDateString(),
            'informada' => $c->rlt_informat_ts !== null,
        ])->values();

        return response()->json([
            'any' => $any,
            'total' => $files->count(),
            'pendents' => $files->where('informada', false)->count(),
            'sancions' => $files,
        ]);
    }

    /** POST /api/v1/domi/element-disciplinari */
    public function elementDisciplinari(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required_without:dni|integer',
            'dni' => 'required_without:user_id|string|max:16',
            'tipus_falta' => 'required|string|max:60',
            'data_fet' => 'required|date',
            'descripcio' => 'required|string|max:255',
            'motivacio' => 'required|string|min:15|max:500',
            'qui_eleva' => 'required|string|max:60',
            'font' => 'nullable|string|max:80',
            'valor' => 'nullable|string|max:120',
        ]);

        $worker = null;
        if (! empty($data['user_id'])) {
            $worker = User::find((int) $data['user_id']);
        }
        if (! $worker && ! empty($data['dni'])) {
            $dni = strtoupper(str_replace(['-', ' ', '.'], '', $data['dni']));
            $worker = User::whereRaw("UPPER(REPLACE(REPLACE(REPLACE(dni,'-',''),' ',''),'.','')) = ?", [$dni])->first();
        }
        if (! $worker) {
            return response()->json(['error' => 'treballador no trobat'], 404);
        }
        if (! \App\Models\DisciplinaryFaultType::where('clau', $data['tipus_falta'])->exists()) {
            return response()->json(['error' => 'tipus de falta desconegut'], 422);
        }

        $element = \App\Models\DisciplinaryElement::create([
            'professional' => $worker->name,
            'user_id' => $worker->id,
            'tipus_falta' => $data['tipus_falta'],
            'data_fet' => $data['data_fet'],
            'data_coneixement' => now()->toDateString(),
            'font' => $data['font'] ?? 'domi · panell de rendiment',
            'valor' => $data['valor'] ?? '',
            'descripcio' => mb_substr($data['descripcio'] . ' — Motivació de qui eleva ('
                . $data['qui_eleva'] . '): ' . $data['motivacio'], 0, 255),
            'imputable' => 'condicional',
            'estat' => 'valorat',
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'ok' => true,
            'element_id' => $element->id,
            'treballador' => $worker->name,
            'avis' => 'Element registrat com a CONDICIONAL a la safata de RRHH. No obre cap expedient: '
                    . 'RRHH decideix si escau instruir, amb la seva pròpia motivació.',
        ], 201);
    }

    public function openIncidents()
    {
        $refs = \App\Models\WorkLogSegment::where('kind', 'visita')
            ->whereNotNull('ref')
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhereHas('workLog.alerts', fn ($a) => $a->whereNull('dismissed_at'));
            })
            ->pluck('ref')->unique()->values();

        return response()->json(['refs' => $refs]);
    }

    /** Finestra horària: el hito cau dins l'horari del dia del treballador? */
    private function isScheduledDayPublic(User $worker, Carbon $when): bool
    {
        $schedule = $worker->workSchedule;
        if (! $schedule || ! is_array($schedule->days)) {
            return false;
        }
        $local = $when->copy()->setTimezone('Europe/Madrid');
        $jsDay = (int) $local->format('w');
        foreach ($schedule->days as $d) {
            if ((int) ($d['day'] ?? -1) === $jsDay
                && ($d['active'] ?? true) !== false
                && ! empty($d['start']) && ! empty($d['end'])) {
                $t = $local->format('H:i');
                if ($t >= substr($d['start'], 0, 5) && $t <= substr($d['end'], 0, 5)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Codi d'autorització (hores complementàries) vigent en el moment del hito? */
    private function hitoHasAuthCode(User $worker, Carbon $when): bool
    {
        return \App\Models\AuthorizationCode::where('revoked', false)
            ->where(function ($q) use ($worker) {
                $q->where('user_id', $worker->id)->orWhereNull('user_id');
            })
            ->where('valid_from', '<=', $when)
            ->where('valid_to', '>=', $when)
            ->exists();
    }

    // ── Helpers (mirall de la lògica del frontend, per paritat de comportament) ──

    private function scheduledHoursToday(User $worker): float
    {
        $schedule = $worker->workSchedule;
        if (! $schedule || ! is_array($schedule->days)) {
            return 0;
        }
        $jsDay = (int) now('Europe/Madrid')->format('w'); // 0=Dg .. 6=Ds
        foreach ($schedule->days as $d) {
            if ((int) ($d['day'] ?? -1) === $jsDay
                && ($d['active'] ?? true) !== false
                && ! empty($d['start']) && ! empty($d['end'])) {
                [$sh, $sm] = array_map('intval', explode(':', $d['start']));
                [$eh, $em] = array_map('intval', explode(':', $d['end']));

                return max(0, (($eh * 60 + $em) - ($sh * 60 + $sm)) / 60);
            }
        }

        return 0;
    }

    private function pausaPerPlanificacio(User $worker): array
    {
        $s = BreakSetting::getSettings();
        $mode = $worker->break_override_time ? 'fixed' : $s->break_start_mode;
        $fixa = $worker->break_override_time ?: $s->break_start_fixed_time;
        $fixa = $fixa instanceof Carbon ? $fixa->format('H:i') : ($fixa ? substr((string) $fixa, 0, 5) : null);

        return [
            'aplica' => (bool) $s->enabled,
            'durada_min' => (int) $s->break_duration_minutes,
            'llindar_h' => (int) $s->threshold_hours,
            // L'override del treballador obliga la pausa encara que no arribi al llindar.
            'sempre' => $worker->break_override === true,
            'mode' => $mode,
            'offset_min' => (int) ($s->break_start_offset_minutes ?: $s->threshold_hours * 60),
            'hora_fixa' => $mode === 'fixed' ? ($fixa ?: '12:00') : null,
        ];
    }

    private function breakTriggerTime(WorkLog $log, User $worker, BreakSetting $settings): ?Carbon
    {
        $clockIn = Carbon::parse($log->start_time);
        $mode = $worker->break_override_time ? 'fixed' : $settings->break_start_mode;

        if ($mode === 'fixed') {
            $fixed = $worker->break_override_time ?: ($settings->break_start_fixed_time ?: '12:00');
            $fixed = $fixed instanceof Carbon ? $fixed->format('H:i') : substr((string) $fixed, 0, 5);
            [$fh, $fm] = array_map('intval', explode(':', $fixed));

            return $clockIn->copy()->setTime($fh, $fm, 0);
        }

        if ($mode === 'auto') {
            // El motor de Domiciliària ha triat l'hora d'avui i l'ha empès a planned_breaks.
            $pb = \Illuminate\Support\Facades\DB::table('planned_breaks')
                ->where('user_id', $worker->id)
                ->where('date', $clockIn->toDateString())
                ->first();
            if ($pb && $pb->start_time) {
                [$ph, $pm] = array_map('intval', explode(':', substr((string) $pb->start_time, 0, 5)));

                return $clockIn->copy()->setTime($ph, $pm, 0);
            }
            // Sense pla del motor per avui: cau a l'offset perquè la pausa mai es perdi.
        }

        $offset = $settings->break_start_offset_minutes ?: ($settings->threshold_hours * 60);

        return $clockIn->copy()->addMinutes((int) $offset);
    }

    /**
     * POST /api/v1/domi/pausa-dia
     * El motor de Domiciliària empeny, per als propers dies, l'hora de pausa que ha
     * col·locat a la ruta de cada professional (mode 'auto'). Conciliació per DNI;
     * el pla nou substitueix el vell dia a dia.
     */
    public function pausaDia(Request $request)
    {
        $data = $request->validate([
            'pauses' => 'required|array|max:5000',
            'pauses.*.dni' => 'required|string|max:16',
            'pauses.*.dia' => 'required|date_format:Y-m-d',
            'pauses.*.hora_ini' => 'required|date_format:H:i',
            'pauses.*.hora_fi' => 'nullable|date_format:H:i',
            'pauses.*.durada_min' => 'required|integer|min:5|max:180',
        ]);

        $perDni = User::whereNotNull('dni')->pluck('id', 'dni')
            ->mapWithKeys(fn ($id, $dni) => [strtoupper(str_replace(['-', ' ', '.'], '', $dni)) => $id]);

        $n = 0;
        foreach ($data['pauses'] as $p) {
            $dni = strtoupper(str_replace(['-', ' ', '.'], '', $p['dni']));
            $uid = $perDni[$dni] ?? null;
            if ($uid === null) {
                continue;
            }
            \Illuminate\Support\Facades\DB::table('planned_breaks')->updateOrInsert(
                ['user_id' => $uid, 'date' => $p['dia']],
                ['start_time' => $p['hora_ini'] . ':00',
                 'end_time' => isset($p['hora_fi']) ? $p['hora_fi'] . ':00' : null,
                 'duration_minutes' => (int) $p['durada_min'],
                 'source' => 'domi', 'updated_at' => now(), 'created_at' => now()]
            );
            $n++;
        }

        return response()->json(['ok' => true, 'rebudes' => count($data['pauses']), 'aplicades' => $n]);
    }
}
