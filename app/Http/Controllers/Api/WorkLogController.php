<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLogRequest;
use App\Http\Requests\UpdateWorkLogRequest;
use App\Models\WorkLog;
use App\Services\WorkLogService;
use App\Support\SecondFactor;
use Carbon\Carbon;
use Illuminate\Http\Request;

class WorkLogController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(protected WorkLogService $workLogs)
    {
    }

    public function index(Request $request)
    {
        $this->logLocationAccess($request, 'Consulta del llistat de fichatges');

        // Limitar a los últimos 500 registros para evitar respuestas enormes
        // y seleccionar solo las columnas necesarias de la relación user.
        // MINIMITZACIÓ (EIPD §6.3): el llistat MAI inclou coordenades — només el
        // detall, i sota les condicions de coordsVisibles().
        return response()->json(
            WorkLog::with('user:id,name')
                ->orderBy('date', 'desc')
                ->limit(500)
                ->get()
                ->each(fn ($w) => $w->makeHidden([
                    'start_location_lat', 'start_location_lng',
                    'end_location_lat', 'end_location_lng',
                ]))
        );
    }

    /**
     * Registra qui i quan accedeix a dades d'ubicació (log d'accessos exigit per la EIPD).
     */
    private function logLocationAccess(Request $request, string $desc): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }
        try {
            \App\Models\AuditLog::create([
                'user_id' => $user->id,
                'action' => 'ACCESS_LOCATION_DATA',
                'entity_type' => 'geolocation',
                'description' => $desc,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // no bloquejar la consulta si falla el log
        }
    }

    public function store(StoreWorkLogRequest $request)
    {
        $data = $request->validated();
        // AUTORITZACIÓ: es fitxa per a si mateix (o staff). El pont de domi actua
        // EN NOM del treballador (user resolver = worker), així que també passa.
        $this->ensureOwnerOrStaff($request, $data['user_id']);
        // DISPOSITIU CORPORATIU (Hexnode) amb GPS DENEGAT: bloquejat — la ubicació no
        // es pot desactivar en un dispositiu d'empresa (anomalia registrada en auditoria)
        if (empty($data['start_location_lat'])
            && ($data['gps_error'] ?? null) === 'denied'
            && SecondFactor::esDispositiuCorporatiu($data['disp'] ?? null)) {
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $request->user()?->id, 'action' => 'GPS_DENEGAT_DISP_CORP',
                    'entity_type' => 'work_log',
                    'description' => 'ANOMALIA: intent de fichatge amb GPS denegat en dispositiu corporatiu',
                    'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) {
            }
            abort(403, 'Aquest és un dispositiu d\'empresa: la ubicació és obligatòria i no es pot desactivar. Reactiva-la o contacta amb coordinació.');
        }
        unset($data['via_domi'], $data['disp'], $data['gps_error']);

        // Registre manual amb justificació (sense GPS): la justificació es traça com a
        // modificació, mai dins del work_log (EIPD §6.4). El registre MAI es bloqueja.
        $justificacio = $data['justificacio'] ?? '';
        unset($data['justificacio']);

        $now = $this->workLogs->getAccurateTime();
        // Hora de Madrid directa (sense convertir a UTC): perque qualsevol consulta
        // directa a BD (auditoria, inspeccio de treball) sigui llegible tal qual,
        // conveni consistent amb created_at/updated_at.
        $data['start_time'] = $now->toDateTimeString();
        $data['date'] = $now->toDateString();

        // Prevent duplicate open sessions for the same user on the same date
        $existing = WorkLog::where('user_id', $data['user_id'])
            ->whereDate('date', $data['date'])
            ->whereNull('end_time')
            ->first();
        if ($existing) {
            return response()->json($existing, 200);
        }

        $log = WorkLog::create($data);
        if (trim($justificacio) !== '') {
            try {
                \App\Models\WorkLogModification::create([
                    'work_log_id' => $log->id,
                    'user_id' => $log->user_id,
                    'action' => 'registre_manual',
                    'new_values' => ['context' => 'inici de jornada', 'justificacio' => $justificacio],
                    'comment' => "Registre manual sense GPS (inici de jornada): {$justificacio}",
                ]);
            } catch (\Throwable $e) {
                // la traça no pot bloquejar el registre
            }
        }

        return response()->json($log, 201);
    }

    public function byUser($userId)
    {
        // Limitar a los últimos 200 registros del usuario para evitar respuestas enormes.
        return response()->json(
            WorkLog::where('user_id', $userId)
                ->orderBy('date', 'desc')
                ->limit(200)
                ->get()
        );
    }

    public function update(UpdateWorkLogRequest $request, WorkLog $workLog)
    {
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        $data = $request->validated();
        if (empty($data['end_location_lat'])
            && ($data['gps_error'] ?? null) === 'denied'
            && SecondFactor::esDispositiuCorporatiu($data['disp'] ?? null)) {
            abort(403, 'Aquest és un dispositiu d\'empresa: la ubicació és obligatòria i no es pot desactivar.');
        }
        $justificacio = trim((string) ($data['justificacio'] ?? ''));
        unset($data['justificacio'], $data['disp'], $data['gps_error']);

        // If clocking out, override end_time and recalculate hours server-side
        // (client clock may lag behind NTP, causing totalHours to be 0 or negative)
        if (array_key_exists('end_time', $data) && $data['end_time'] !== null) {
            $endNow = $this->workLogs->getAccurateTime();
            // Hora de Madrid directa (mateix conveni que start_time, veure store()).
            $data['end_time'] = $endNow->toDateTimeString();

            $startRaw = $workLog->getRawOriginal('start_time');
            if ($startRaw) {
                $startLocal = Carbon::parse($startRaw, 'Europe/Madrid');
                $serverHours = round($startLocal->diffInSeconds($endNow) / 3600, 4);
                $hoursOutOfArea = round(floatval($data['hours_out_of_area'] ?? $workLog->hours_out_of_area ?? 0), 4);
                $data['total_hours_worked'] = $serverHours;
                $data['hours_worked']       = round(max(0, $serverHours - $hoursOutOfArea), 4);

                // Calcular horas complementarias en minutos
                $data['complementary_minutes'] = intval(round(max(0, $serverHours - 8) * 60));

                // Verificar si requiere pausa obligatoria
                $breakSettings = \App\Models\BreakSetting::getSettings();
                if ($breakSettings->enabled && $serverHours > $breakSettings->threshold_hours) {
                    $data['break_required'] = true;
                    $data['break_status'] = 'pending';
                }
            }

            // Mode de verificació (detecció prèvia de domi; RRHH és autònom):
            // A = domi online (verificació de domicili), B = domi offline (validació per zona).
            // Si la jornada JA té hitos de domi (mode A i categorització posades pel
            // flux d'hitos), el tancament NO les trepitja: els hitos són l'evidència.
            if ($workLog->verification_mode !== 'A') {
                $mode = \App\Services\DomiClient::verificationMode();
                $data['verification_mode'] = $mode;
                $data['home_verification'] = $mode === 'A' ? 'pendent' : 'no_disponible';
            }

            // Bandera fora de quadrant: dia sense horari actiu I sense hores complementàries
            // autoritzades per codi vigent (les complementàries SÍ són horari permès).
            $data['out_of_schedule'] = ! $this->workLogs->isScheduledDay($workLog->user, $endNow)
                && ! $this->workLogs->hasActiveAuthCode($workLog->user, $endNow);
        }

        $workLog->update($data);

        if ($justificacio !== '') {
            try {
                \App\Models\WorkLogModification::create([
                    'work_log_id' => $workLog->id,
                    'user_id' => $workLog->user_id,
                    'action' => 'registre_manual',
                    'new_values' => ['context' => 'final de jornada', 'justificacio' => $justificacio],
                    'comment' => "Registre manual sense GPS (final de jornada): {$justificacio}",
                ]);
            } catch (\Throwable $e) {
            }
        }

        // Segmentación automática al cerrar fichaje
        if (array_key_exists('end_time', $data) && $data['end_time'] !== null && !$workLog->segmented) {
            $this->workLogs->segmentWorkLog($workLog, $request->user());
        } elseif (array_key_exists('end_time', $data) && $data['end_time'] !== null && $workLog->segmented) {
            // Jornada segmentada per HITOS (domi): tancar la cua — visita provisional
            // oberta i desplaçament final fins al tancament — i recalcular efectiu.
            $this->workLogs->closeMilestoneTail($workLog);
        }

        return response()->json($workLog->fresh()->load('segments'));
    }

    /**
     * GET /api/v1/work-logs/{workLog}/detail
     * Devuelve el fichaje con sus tramos, modificaciones y alertas.
     */
    public function detail(Request $request, WorkLog $workLog)
    {
        // Accés al registre: titular o staff. (La visibilitat de COORDENADES dins
        // del detall té a més les regles EIPD §6.3 de més avall.)
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        $workLog->load([
            'user:id,name,work_type,job_profile',
            'segments' => fn($q) => $q->orderBy('segment_number'),
            'modifications.user:id,name,role',
            'alerts' => fn($q) => $q->whereNull('dismissed_at'),
        ]);

        // RESTRICCIÓ DE VISUALITZACIÓ (EIPD §6.3): les coordenades es mostren a
        //  (a) el rol ADMIN — control empresarial legítim (art. 20.3 ET); no és accés
        //      "lliure": CADA consulta queda registrada al log d'auditoria,
        //  (b) el PROPI treballador (transparència simètrica, art. 15 RGPD),
        //  (c) altres rols autoritzats NOMÉS si el fichatge està EN CONFLICTE (fora de
        //      zona/radi, rebutjat, fora de quadrant o amb alerta pendent), o
        //  (d) amb MOTIU d'auditoria explícit (?motiu=...), que queda registrat.
        // La resta de consultes reben el registre SENSE coordenades (booleans i
        // distàncies sí: el control funciona sense exposar la posició).
        $esAdmin = $request->user() && $request->user()->role === 'admin';
        $esPropi = $request->user() && $request->user()->id === $workLog->user_id;
        $conflicte = $workLog->out_of_schedule
            || $workLog->location_match === false
            || $workLog->alerts->isNotEmpty()
            || $workLog->segments->contains(fn ($s) => ! $s->in_zone
                || $s->home_verification === 'fora_radi' || $s->status === 'rejected');
        $motiu = trim((string) $request->query('motiu', ''));

        if ($esAdmin || $esPropi || $conflicte || $motiu !== '') {
            $desc = "Consulta del detall del fichatge #{$workLog->id} AMB coordenades — "
                . ($esAdmin ? 'rol admin (control art. 20.3 ET)'
                : ($esPropi ? 'titular de les dades'
                : ($conflicte ? 'cas en conflicte (revisió)' : "auditoria justificada: {$motiu}")));
            $this->logLocationAccess($request, $desc);
        } else {
            $this->logLocationAccess($request, "Consulta del detall del fichatge #{$workLog->id} sense coordenades (fora de conflicte i sense motiu)");
            $workLog->makeHidden(['start_location_lat', 'start_location_lng', 'end_location_lat', 'end_location_lng']);
            $workLog->segments->each(fn ($s) => $s->makeHidden(['start_lat', 'start_lng', 'end_lat', 'end_lng']));
            $workLog->setAttribute('coords_restringides', true);
        }

        // server_time: perque el frontend pugui corregir comptadors (p.ex. la pausa
        // obligatoria) si el rellotge del dispositiu del treballador va desquadrat,
        // sense dependre que el seu rellotge local sigui fiable. Convertit a UTC real
        // explicitament (mateix conveni que start_time): aixi Carbon el serialitza amb
        // una 'Z' veritable i el frontend el pot comparar be amb el seu Date.now().
        return response()->json(array_merge($workLog->toArray(), [
            'server_time' => now()->copy()->utc()->toISOString(),
        ]));
    }

    /**
     * POST /api/v1/work-logs/{workLog}/start-break
     * Inicia la pausa obligatoria.
     */
    public function startBreak(Request $request, WorkLog $workLog)
    {
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        if ($workLog->break_status === 'active') {
            return response()->json(['message' => 'La pausa ja està en curs'], 409);
        }

        $now = $this->workLogs->getAccurateTime();
        $settings = \App\Models\BreakSetting::getSettings();
        $geo = $request->validate(['location_match' => 'nullable|boolean']);

        // Hora de Madrid directa (mateix conveni que start_time/end_time).
        $nowLocal = $now->toDateTimeString();
        $workLog->update([
            'break_start_time' => $nowLocal,
            'break_start_location_match' => $geo['location_match'] ?? null,
            'break_status' => 'active',
            'break_required' => true,
        ]);

        \App\Models\WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'break_started',
            'new_values' => ['break_start_time' => $now->toDateTimeString()],
            'comment' => 'Pausa obligatòria iniciada (' . $settings->break_duration_minutes . ' minuts)',
        ]);

        return response()->json([
            'work_log' => $workLog->fresh(),
            'break_duration_minutes' => $settings->break_duration_minutes,
            'break_start_time' => $nowLocal,
            // server_time SÍ va en UTC real (amb 'Z' veritable): només serveix perquè el
            // frontend calculi un offset relatiu contra Date.now(), no es guarda a BD.
            'server_time' => $now->copy()->utc()->toISOString(),
        ]);
    }

    /**
     * POST /api/v1/work-logs/{workLog}/complete-break
     * Completa la pausa obligatoria.
     */
    public function completeBreak(Request $request, WorkLog $workLog)
    {
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        $now = $this->workLogs->getAccurateTime();

        $geo = $request->validate(['location_match' => 'nullable|boolean']);
        $workLog->update([
            'break_end_time' => $now->toDateTimeString(),
            'break_end_location_match' => $geo['location_match'] ?? null,
            'break_status' => 'completed',
        ]);

        \App\Models\WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'break_completed',
            'new_values' => ['break_end_time' => $now->toDateTimeString()],
            'comment' => 'Pausa obligatòria completada',
        ]);

        return response()->json($workLog->fresh());
    }

    /**
     * POST /api/v1/work-logs/{workLog}/skip-break
     * Omite la pausa obligatoria (con justificación).
     */
    public function skipBreak(Request $request, WorkLog $workLog)
    {
        $this->ensureOwnerOrStaff($request, $workLog->user_id);
        $request->validate(['reason' => 'nullable|string']);

        $workLog->update([
            'break_status' => 'skipped',
        ]);

        \App\Models\WorkLogModification::create([
            'work_log_id' => $workLog->id,
            'user_id' => $request->user()->id,
            'action' => 'break_skipped',
            'new_values' => ['reason' => $request->reason],
            'comment' => 'Pausa obligatòria omesa: ' . ($request->reason ?? 'Sense motiu'),
        ]);

        return response()->json($workLog->fresh());
    }
}
