<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuthCodeRequest;
use App\Mail\AuthCodeNotification;
use App\Models\AuthorizationCode;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AuthCodeController extends Controller
{
    use \App\Http\Controllers\Concerns\RegistraDecisions;

    // Els números viuen a Support\Jornada (font única): aquí només s'hi apunta.
    // Màxim legal d'hores complementàries: 30% de la jornada bàsica contractada (art. 12.5 ET)
    const COMPLEMENTARY_MAX_RATIO = \App\Support\Jornada::COMPLEMENTARY_MAX_RATIO;
    // Extraordinàries (jornada completa): art. 20.1 conveni XII — 80 h/any a 1,25×.
    const EXTRA_ANNUAL_CAP = \App\Support\Jornada::EXTRA_ANUAL_H;
    const EXTRA_FACTOR = \App\Support\Jornada::EXTRA_FACTOR;

    // Sostre de PLANIFICACIÓ des de domi: 28%. El 2% restant queda reservat perquè
    // acceptar una extensió de sessions demanada pel mateix fisio mai superi el 30% legal.
    const COMPLEMENTARY_PLANNING_RATIO = 0.28;

    public function index()
    {
        $codes = AuthorizationCode::with(['generatedBy:id,name', 'assignedUser:id,name'])->get();

        // Consum REAL de cada codi: el mana el REGISTRE HORARI (el treballador imputa
        // el codi al fitxatge del dia). L'agenda no compta aquí: només s'hi projecta.
        $consum = \Illuminate\Support\Facades\DB::table('work_logs')
            ->whereNotNull('authorized_extra_code')
            ->groupBy('authorized_extra_code')
            ->selectRaw('authorized_extra_code codi,
                         SUM(COALESCE(NULLIF(extra_hours_authorized,0), complementary_minutes/60, 0)) hores,
                         MIN(date) primer_dia')
            ->get()->keyBy('codi');

        $out = $codes->map(function ($c) use ($consum) {
            $u = $consum->get($c->code);
            $usat = $u ? round((float) $u->hores, 2) : 0.0;
            $tot = (float) $c->authorized_hours;
            $pct = $tot > 0 ? (int) round(min(100, $usat * 100 / $tot)) : null;

            // Previsió d'esgotament pel ritme real de consum des del primer dia imputat.
            $esgota = null; $dinsValidesa = null;
            if ($tot > 0 && $usat >= $tot) {
                $esgota = 'esgotat';
            } elseif ($usat > 0) {
                $inici = $u->primer_dia ?: $c->valid_from;
                $dies = max(1, Carbon::parse($inici)->diffInDays(now()) + 1);
                $ritme = $usat / $dies;
                if ($ritme > 0) {
                    $esgota = now()->addDays((int) ceil(($tot - $usat) / $ritme))->toDateString();
                    $dinsValidesa = $c->valid_to
                        ? ($esgota <= Carbon::parse($c->valid_to)->toDateString()) : true;
                }
            }
            $arr = $c->toArray();
            $arr['consum'] = ['usat_h' => $usat, 'pct' => $pct,
                              'esgota' => $esgota, 'dins_validesa' => $dinsValidesa];
            return $arr;
        });

        return response()->json($out);
    }

    /**
     * GET /api/v1/auth-codes/my
     * Els codis VIGENTS del propi treballador, perquè no n'hagi de transcriure cap:
     * els nascuts del flux de domi (complementàries acceptades / extraordinàries
     * autoritzades) arriben aquí sols, a la mateixa pantalla on s'imputen.
     */
    public function myCodes(Request $request)
    {
        $codes = AuthorizationCode::where('user_id', $request->user()->id)
            ->where('revoked', false)
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now()))
            ->orderByDesc('valid_from')->limit(10)
            ->get(['id', 'code', 'concept', 'authorized_hours', 'type', 'factor', 'valid_from', 'valid_to']);
        return response()->json($codes);
    }

    /**
     * POST /api/v1/auth-codes/{authCode}/renew
     * Renova un codi: en neix un de nou amb les hores i el període editats i el vell
     * queda revocat a l'acte. El topall anual del tipus es recomprova sempre.
     */
    public function renew(Request $request, AuthorizationCode $authCode)
    {
        if ($authCode->revoked) {
            return response()->json(['error' => 'El codi ja està revocat: no es pot renovar.'], 409);
        }
        $data = $request->validate([
            'hours' => 'required|numeric|min:0.25|max:500',
            'valid_from' => 'required|date_format:Y-m-d',
            'valid_to' => 'required|date_format:Y-m-d|after_or_equal:valid_from',
        ]);
        $year = (int) substr($data['valid_from'], 0, 4);

        if ($authCode->user_id) {
            $ja = round((float) AuthorizationCode::where('user_id', $authCode->user_id)
                ->where('type', $authCode->type === 'extraordinaria' ? '=' : '!=', 'extraordinaria')
                ->where('revoked', false)->where('id', '!=', $authCode->id)
                ->whereYear('valid_from', $year)->sum('authorized_hours'), 2);
            // Les hores JA CONSUMIDES del codi que es renova compten SEMPRE contra el
            // topall: un pacte o un cupó d'extraordinàries esgotat dins l'any NO es
            // renova — a partir d'aquí, res per sobre del contracte.
            $consumit = round((float) \Illuminate\Support\Facades\DB::table('work_logs')
                ->where('authorized_extra_code', $authCode->code)
                ->selectRaw('SUM(COALESCE(NULLIF(extra_hours_authorized,0), complementary_minutes/60, 0)) h')
                ->value('h'), 2);
            if ($authCode->type === 'extraordinaria') {
                $nomTopall = 'd\'extraordinàries (80 h/any)';
                $disponible = round((float) self::EXTRA_ANNUAL_CAP - $ja - $consumit, 2);
            } else {
                // El PACTE signat és la quantitat ferma: renovar només pot re-periodificar
                // el ROMANENT del propi pacte (ampliar-lo exigiria una signatura nova).
                $stats = User::find($authCode->user_id)?->complementaryStats($year);
                $capH = (float) ($stats['max_anual_h'] ?? 0);
                $nomTopall = 'del pacte de complementàries';
                if ($capH <= 0) {
                    return response()->json(['error' => 'Topall de complementàries no calculable (jornada sense sincronitzar).'], 422);
                }
                $disponible = round(min((float) $authCode->authorized_hours - $consumit,
                                        $capH - $ja - $consumit), 2);
            }
            if ($disponible <= 0) {
                return response()->json(['error' => sprintf(
                    'Topall anual %s ESGOTAT (%.2f h vigents + %.2f h ja consumides d\'aquest codi): no es pot renovar.',
                    $nomTopall, $ja, $consumit)], 422);
            }
            if ((float) $data['hours'] > $disponible + 0.001) {
                return response()->json(['error' => sprintf(
                    'Superaria el topall anual %s: en queden %.2f h (%.2f vigents + %.2f consumides d\'aquest codi) i se\'n demanen %.2f.',
                    $nomTopall, $disponible, $ja, $consumit, (float) $data['hours'])], 422);
            }
        }

        $nou = AuthorizationCode::create([
            'code' => strtoupper(bin2hex(random_bytes(4))),
            'concept' => 'Renovació de ' . $authCode->code . ' · ' . mb_substr((string) $authCode->concept, 0, 180),
            'authorized_hours' => $data['hours'],
            'type' => $authCode->type,
            'factor' => $authCode->factor,
            'generated_by' => $request->user()->id,
            'user_id' => $authCode->user_id,
            'valid_from' => $data['valid_from'],
            'valid_to' => $data['valid_to'],
            'time_slot_start' => $authCode->time_slot_start,
            'time_slot_end' => $authCode->time_slot_end,
        ]);
        $authCode->update(['revoked' => true, 'revoked_at' => now()]);

        return response()->json(['ok' => true, 'code' => $nou->code, 'id' => $nou->id], 201);
    }

    public function store(StoreAuthCodeRequest $request)
    {
        $data = $request->validated();

        // El multiplicador el posa el servidor, mai el formulari: una hora
        // extraordinària val 1,25× (art. 20.1 conveni XII) i una complementària 1,00×.
        $data['type'] = $data['type'] ?? 'complementaria';
        $data['factor'] = $data['type'] === 'extraordinaria' ? self::EXTRA_FACTOR : 1.00;

        // Franja horària flexible EXCEPTE la nocturna (22:00–06:00): només es
        // permet autoritzar dins de l'horari diürn 06:00–22:00.
        $slotError = $this->validateDaytimeSlot($data['time_slot_start'], $data['time_slot_end']);
        if ($slotError) {
            return response()->json([
                'message' => $slotError,
                'errors' => ['time_slot_start' => [$slotError]],
            ], 422);
        }

        // Topalls per persona. FAIL-CLOSED: si el topall no es pot calcular, es
        // DENEGA i es diu què falta. Abans, un treballador sense quadre horari
        // donava topall 0 i la condició `cap > 0` deixava passar el que fos.
        if (! empty($data['user_id'])) {
            $treballador = User::find($data['user_id']);
            $hores = (float) $data['authorized_hours'];
            $esExtra = ($data['type'] ?? null) === 'extraordinaria';
            $any = (int) Carbon::parse($data['valid_from'])->year;

            if ($esExtra) {
                // Extraordinàries: topall de 80 h/any (art. 20.1 conveni XII).
                $ja = round((float) AuthorizationCode::where('user_id', $data['user_id'])
                    ->where('type', 'extraordinaria')->where('revoked', false)
                    ->whereYear('valid_from', $any)->sum('authorized_hours'), 2);
                if ($ja + $hores > self::EXTRA_ANNUAL_CAP + 0.01) {
                    return response()->json([
                        'message' => sprintf('Superaria el topall anual d\'hores extraordinàries (%d h/any, art. 20.1 del conveni XII). '
                            . 'Ja n\'hi ha %.2f h autoritzades el %d; en queden %.2f i se\'n demanen %.2f.',
                            (int) self::EXTRA_ANNUAL_CAP, $ja, $any, max(0, self::EXTRA_ANNUAL_CAP - $ja), $hores),
                        'errors' => ['authorized_hours' => ['Supera el topall anual de 80 h extraordinàries']],
                    ], 422);
                }
            } else {
                // Complementàries: PROHIBIDES a jornada completa (art. 12.4.c ET).
                // La pantalla del treballador ja li ho deia; el servidor no ho comprovava.
                if ($treballador && $treballador->isFullTime()) {
                    return response()->json([
                        'message' => 'Aquesta persona té jornada completa (' . \App\Support\Jornada::SETMANA_COMPLETA_H
                            . ' h/setmana): la llei no admet hores complementàries en un contracte a temps complet '
                            . '(art. 12.4.c ET). Si cal treballar per sobre del contracte, emeteu hores EXTRAORDINÀRIES '
                            . '(màxim ' . (int) self::EXTRA_ANNUAL_CAP . ' h/any a ' . self::EXTRA_FACTOR . '×).',
                        'errors' => ['type' => ['Jornada completa: només hores extraordinàries']],
                    ], 422);
                }

                $cap = $this->complementaryCap($data['user_id'], $data['valid_from']);
                if (! $cap['calculable']) {
                    return response()->json([
                        'message' => 'No es pot calcular el topall d\'hores complementàries d\'aquesta persona, '
                            . 'i sense topall no s\'autoritzen hores. ' . $cap['motiu'],
                        'errors' => ['user_id' => [$cap['motiu']]],
                    ], 422);
                }
                $newTotal = $cap['already'] + $hores;
                if ($newTotal > $cap['cap'] + 0.01) {
                    return response()->json([
                        'message' => "Les hores complementàries superen el 30% de la jornada bàsica contractada anual ({$cap['year']}). "
                            . "Jornada bàsica anual: {$cap['annualBasic']}h · tope anual (30%): {$cap['cap']}h · "
                            . "ja autoritzades l'any: {$cap['already']}h · disponibles: {$cap['remaining']}h.",
                        'errors' => ['authorized_hours' => ['Supera el tope anual del 30% de la jornada bàsica']],
                    ], 422);
                }
            }
        }

        $authCode = AuthorizationCode::create($data);

        if ($authCode->user_id) {
            $this->registraDecisio($request, 'AUTHCODE_ISSUED', 'authorization_code', $authCode->id,
                sprintf('Codi %s · %.2f h (%s) per a l\'usuari #%d, emès per %s',
                    $authCode->code, (float) $authCode->authorized_hours,
                    $authCode->type ?: 'complementaria', $authCode->user_id, $request->user()->name));
            $this->avisaTreballador($authCode->user_id, sprintf(
                'Tens %.2f h %s autoritzades (codi %s), vàlides del %s al %s.',
                (float) $authCode->authorized_hours,
                $authCode->type === 'extraordinaria' ? 'extraordinàries' : 'complementàries',
                $authCode->code,
                Carbon::parse($authCode->valid_from)->toDateString(),
                $authCode->valid_to ? Carbon::parse($authCode->valid_to)->toDateString() : 'sense data fi'));
        }

        if (($request->input('notification_sent') || $request->input('send_notification')) && $authCode->user_id) {
            $recipient = User::find($authCode->user_id);
            $generatedBy = User::find($authCode->generated_by);
            if ($recipient?->email && $generatedBy) {
                // El correu és síncron (QUEUE=sync). Un SMTP lent/caigut NO pot fer
                // fallar la creació del codi (que ja existeix): es marca només si s'envia.
                try {
                    Mail::to($recipient->email)->send(new AuthCodeNotification($authCode, $recipient, $generatedBy));
                    $authCode->update(['notification_sent' => true]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('AuthCode notification failed: ' . $e->getMessage());
                }
            }
        }

        return response()->json($authCode, 201);
    }

    public function revoke(AuthorizationCode $authCode)
    {
        $authCode->revoke();
        return response()->json($authCode->fresh());
    }

    public function validateCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $code = AuthorizationCode::where('code', $request->code)->first();
        if (! $code) {
            return response()->json(['valid' => false, 'reason' => 'code_invalid']);
        }

        return response()->json($code->validate());
    }

    public function useCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $code = AuthorizationCode::where('code', $request->code)->first();
        if ($code) {
            $code->markUsed();
        }

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/auth-codes/complementary-cap?user_id=..&valid_from=..
     * Retorna el límit d'hores complementàries (30% de la jornada bàsica del mes)
     * per a un treballador, per mostrar-lo a la UI abans de crear el codi.
     */
    public function complementaryCapInfo(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'valid_from' => 'required|date',
        ]);

        return response()->json($this->complementaryCap($request->user_id, $request->valid_from));
    }

    /**
     * Calcula el TOPE ANUAL d'hores complementàries per a $userId (any de $validFrom):
     * 30% de la jornada bàsica contractada ANUAL (hores del conveni pels dies laborables
     * de l'any segons calendari laboral, exclou festius), amb SEGUIMENT MENSUAL del consum.
     */
    /**
     * GET /api/v1/hours-control?year=YYYY[&user_id=..]
     * Control conjunt per treballador: complementàries pactades (parcials) i
     * extraordinàries (jornada completa), amb els seus dos comptadors i topalls.
     * És la pestanya visual de RRHH per vigilar el compromís de pagament.
     */
    public function hoursControl(Request $request)
    {
        $year = (int) ($request->year ?: now()->year);
        $usersQ = User::query()
            ->whereIn('role', ['worker'])
            ->when($request->user_id, fn ($q) => $q->where('id', $request->user_id));
        // Només personal assistencial que fitxa (exclou perfils sense control horari)
        $users = $usersQ->orderBy('name')->get();

        // Consum REAL per treballador i tipus: minuts del registre horari imputats
        // contra codis. Els totalitzadors mostren consumit + compromès vs topall.
        $consumPerTipus = \Illuminate\Support\Facades\DB::table('work_logs')
            ->join('authorization_codes as ac', 'ac.code', '=', 'work_logs.authorized_extra_code')
            ->whereYear('ac.valid_from', $year)
            ->groupBy('ac.user_id')
            ->selectRaw("ac.user_id uid,
                SUM(CASE WHEN ac.type='extraordinaria' THEN COALESCE(NULLIF(work_logs.extra_hours_authorized,0), work_logs.complementary_minutes/60, 0) ELSE 0 END) extra,
                SUM(CASE WHEN ac.type<>'extraordinaria' THEN COALESCE(NULLIF(work_logs.extra_hours_authorized,0), work_logs.complementary_minutes/60, 0) ELSE 0 END) compl")
            ->get()->keyBy('uid');

        $rows = [];
        foreach ($users as $u) {
            /* El règim el mana el QUADRE HORARI, no el pacte: amb «sense pacte = completa»
               un parcial sense pacte sortia com a jornada completa i un complet amb pacte
               sortia com a parcial (i se li podien emetre complementàries). */
            $complet = $u->isFullTime();
            $codes = AuthorizationCode::where('user_id', $u->id)
                ->where('revoked', false)
                ->whereYear('valid_from', $year)
                ->get(['authorized_hours', 'type', 'factor', 'valid_from']);

            $complHores = round((float) $codes->where('type', '!=', 'extraordinaria')->sum('authorized_hours'), 2);
            $extraHores = round((float) $codes->where('type', 'extraordinaria')->sum('authorized_hours'), 2);
            $cons = $consumPerTipus->get($u->id);
            $complCons = $cons ? round((float) $cons->compl, 2) : 0.0;
            $extraCons = $cons ? round((float) $cons->extra, 2) : 0.0;
            $cap = $this->complementaryCap($u->id, sprintf('%04d-01-01', $year));

            $rows[] = [
                'user_id' => $u->id,
                'name' => $u->name,
                'dni' => $u->dni ?? null,
                'regim' => $cap['calculable'] ? ($complet ? 'completa' : 'parcial') : 'desconegut',
                /* Qui no es pot calcular SURT igualment i marcat: amagar-lo (o pintar-lo
                   de verd) feia desaparèixer del quadre les persones amb la fitxa
                   incompleta, que són justament les que cal mirar. */
                'calculable' => $cap['calculable'],
                'motiu_no_calculable' => $cap['motiu'],
                'complementaries' => [
                    'pacte' => (bool) ($u->pacte_complementaries ?? false),
                    'ratio' => $u->complementary_ratio ?? self::COMPLEMENTARY_MAX_RATIO,
                    'cap' => $cap['cap'],
                    'usades' => $complHores,            // compromeses (codis autoritzats vigents)
                    'consumides' => $complCons,          // treballades de debò (registre horari)
                    'restants' => round(max(0, $cap['cap'] - $complHores), 2),
                    'pct' => $cap['cap'] > 0 ? (int) round($complHores * 100 / $cap['cap']) : null,
                    'pct_consumit' => $cap['cap'] > 0 ? (int) round($complCons * 100 / $cap['cap']) : null,
                ],
                'extraordinaries' => [
                    'cap' => self::EXTRA_ANNUAL_CAP,
                    'factor' => self::EXTRA_FACTOR,
                    'usades' => $extraHores,             // compromeses (autoritzades = compromís de pagament)
                    'consumides' => $extraCons,          // treballades de debò (registre horari)
                    'restants' => round(max(0, self::EXTRA_ANNUAL_CAP - $extraHores), 2),
                    'pct' => (int) round($extraHores * 100 / self::EXTRA_ANNUAL_CAP),
                    'pct_consumit' => (int) round($extraCons * 100 / self::EXTRA_ANNUAL_CAP),
                    'cost_equivalent_h' => round($extraHores * self::EXTRA_FACTOR, 2),
                ],
            ];
        }

        // Els paràmetres de jornada viatgen amb les dades: la pantalla no els reescriu.
        return response()->json(['year' => $year, 'parametres' => \App\Support\Jornada::parametres(), 'rows' => $rows]);
    }

    /**
     * GET /api/v1/overtime-requests?status=pending
     * Sol·licituds d'extraordinàries forçades des de domi, pendents d'autorització de RRHH.
     */
    public function overtimeRequests(Request $request)
    {
        $q = \App\Models\OvertimeRequest::with('user:id,name,dni')
            ->when($request->status, fn ($qq) => $qq->where('status', $request->status))
            ->when($request->user_id, fn ($qq) => $qq->where('user_id', $request->user_id))
            ->latest();
        return response()->json($q->get());
    }

    /**
     * POST /api/v1/overtime-requests/{req}/authorize
     * RRHH autoritza (compromís de pagament): emet el codi d'autorització (1,25×,
     * type=extraordinaria) i marca la sol·licitud com autoritzada. Comprova el tope de 80 h/any.
     */
    public function authorizeOvertime(Request $request, \App\Models\OvertimeRequest $overtimeRequest)
    {
        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['error' => "la sol·licitud ja està {$overtimeRequest->status}"], 409);
        }
        $year = (int) Carbon::parse($overtimeRequest->period_from)->year;
        $jaExtra = round((float) AuthorizationCode::where('user_id', $overtimeRequest->user_id)
            ->where('type', 'extraordinaria')->where('revoked', false)
            ->whereYear('valid_from', $year)->sum('authorized_hours'), 2);
        if ($jaExtra + (float) $overtimeRequest->hours > self::EXTRA_ANNUAL_CAP + 0.001) {
            return response()->json(['error' => sprintf(
                'Superaria el tope anual d\'extraordinàries: ja n\'hi ha %.2f h de %d, i se\'n demanen %.2f més.',
                $jaExtra, (int) self::EXTRA_ANNUAL_CAP, (float) $overtimeRequest->hours)], 422);
        }

        $code = AuthorizationCode::create([
            'code' => strtoupper(bin2hex(random_bytes(4))),
            'concept' => 'Hores extraordinàries (art. 20.1) · forçat des de Coordinació: ' . $overtimeRequest->requested_by,
            'authorized_hours' => $overtimeRequest->hours,
            'type' => 'extraordinaria',
            'factor' => self::EXTRA_FACTOR,
            'domi_origen' => $overtimeRequest->domi_ref,
            'generated_by' => $request->user()->id,
            'user_id' => $overtimeRequest->user_id,
            'valid_from' => $overtimeRequest->period_from,
            'valid_to' => $overtimeRequest->period_to,
        ]);
        $overtimeRequest->update([
            'status' => 'authorized', 'authorized_by' => $request->user()->id,
            'authorized_at' => now(), 'authorization_code_id' => $code->id,
        ]);

        $this->registraDecisio($request, 'OVERTIME_AUTHORIZED', 'overtime_request', $overtimeRequest->id,
            sprintf('%.2f h extraordinàries AUTORITZADES per %s (codi %s)',
                (float) $overtimeRequest->hours, $request->user()->name, $code->code));
        $this->avisaTreballador($overtimeRequest->user_id, sprintf(
            'Se t\'han AUTORITZAT %.2f h extraordinàries (codi %s) del %s al %s.',
            (float) $overtimeRequest->hours, $code->code,
            Carbon::parse($overtimeRequest->period_from)->toDateString(),
            Carbon::parse($overtimeRequest->period_to)->toDateString()));

        return response()->json(['ok' => true, 'code' => $code->code, 'code_id' => $code->id]);
    }

    /** POST /api/v1/overtime-requests/{req}/deny — el motiu és obligatori. */
    public function denyOvertime(Request $request, \App\Models\OvertimeRequest $overtimeRequest)
    {
        if ($overtimeRequest->status !== 'pending') {
            return response()->json(['error' => "la sol·licitud ja està {$overtimeRequest->status}"], 409);
        }
        $motiu = trim((string) $request->input('reason', ''));
        if ($motiu === '') {
            return response()->json([
                'message' => 'Per denegar unes hores extraordinàries cal un motiu escrit.',
                'errors' => ['reason' => ['El motiu de la denegació és obligatori.']],
            ], 422);
        }
        $overtimeRequest->update([
            'status' => 'denied', 'authorized_by' => $request->user()->id,
            'authorized_at' => now(), 'denial_reason' => $motiu,
        ]);

        $this->registraDecisio($request, 'OVERTIME_DENIED', 'overtime_request', $overtimeRequest->id,
            sprintf('%.2f h extraordinàries DENEGADES per %s. Motiu: %s',
                (float) $overtimeRequest->hours, $request->user()->name, $motiu));
        $this->avisaTreballador($overtimeRequest->user_id, sprintf(
            'S\'han DENEGAT les %.2f h extraordinàries sol·licitades. Motiu: %s',
            (float) $overtimeRequest->hours, $motiu));

        return response()->json(['ok' => true]);
    }

    private function complementaryCap($userId, $validFrom): array
    {
        $user = User::find($userId);
        $schedule = $user && $user->work_schedule_id ? WorkSchedule::find($user->work_schedule_id) : null;

        $year = (int) Carbon::parse($validFrom)->year;

        /* Jornada bàsica ANUAL = 1726 h × fracció de jornada (art. 31.2 del conveni XII).
           Abans es recomptava dia a dia sobre el calendari laboral i sortien 1867,5 h per a
           una jornada completa: dues jornades anuals diferents dins de la mateixa aplicació.
           Ara el número surt d'un sol lloc (Support\Jornada, via User::annualBasicHours). */
        $annualBasic = $user ? $user->annualBasicHours($year) : 0.0;

        // FAIL-CLOSED: si no hi ha base de càlcul, es diu exactament què falta.
        $motiu = null;
        if (! $user) {
            $motiu = 'La persona no existeix.';
        } elseif (! $schedule) {
            $motiu = 'No té cap quadre horari assignat a la fitxa: assigneu-l\'hi i torneu-ho a provar.';
        } elseif ((float) $schedule->total_hours_weekly <= 0) {
            $motiu = 'El quadre horari «' . $schedule->name . '» no té hores setmanals: corregiu-lo abans d\'autoritzar hores.';
        } elseif (! is_array($schedule->days) || $schedule->days === []) {
            $motiu = 'El quadre horari «' . $schedule->name . '» està mal desat (sense dies): corregiu-lo abans d\'autoritzar hores.';
        } elseif ($annualBasic <= 0) {
            $motiu = 'La jornada anual calculada és zero: reviseu el quadre horari de la persona.';
        }

        $cap = round($annualBasic * self::COMPLEMENTARY_MAX_RATIO, 2);

        /* Codis COMPLEMENTARIS no revocats de l'any (per al seguiment). Les
           extraordinàries tenen el seu propi topall i no poden consumir aquest. */
        $codes = AuthorizationCode::where('user_id', $userId)
            ->where('revoked', false)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', '!=', 'extraordinaria'))
            ->whereYear('valid_from', $year)
            ->get(['authorized_hours', 'valid_from']);

        $already = round((float) $codes->sum('authorized_hours'), 2);

        // Seguiment mensual: hores autoritzades per mes (índex 0=gener .. 11=desembre)
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = round((float) $codes
                ->filter(fn ($c) => (int) Carbon::parse($c->valid_from)->month === $m)
                ->sum('authorized_hours'), 2);
        }

        return [
            'year' => $year,
            'annualBasic' => round($annualBasic, 2),
            'ratio' => self::COMPLEMENTARY_MAX_RATIO,
            'cap' => $cap,
            'already' => $already,
            'remaining' => round(max(0, $cap - $already), 2),
            'monthly' => $monthly,
            // Sense base de càlcul no s'autoritza res: qui ho mira ha de saber per què.
            'calculable' => $motiu === null,
            'motiu' => $motiu,
            'jornada_completa' => (bool) ($user?->isFullTime()),
        ];
    }

    /**
     * Valida que la franja [start,end] estigui dins l'horari diürn 06:00–22:00.
     * Retorna un missatge d'error si no és vàlida, o null si ho és.
     */
    private function validateDaytimeSlot($start, $end): ?string
    {
        $s = $this->toMinutes($start);
        $e = $this->toMinutes($end);
        if ($s === null || $e === null) {
            return null; // sense franja concreta, no es valida aquí
        }
        if ($e <= $s) {
            return 'La franja horària ha de començar abans d\'acabar (no es permet una franja que creui la nit).';
        }
        // Diürn permès: 06:00 (360) – 22:00 (1320). La resta és franja nocturna prohibida.
        if ($s < 360 || $e > 1320) {
            return 'La franja horària ha d\'estar entre les 06:00 i les 22:00. No es pot autoritzar la franja nocturna (22:00–06:00).';
        }
        return null;
    }

    private function toMinutes($t): ?int
    {
        if (! $t) {
            return null;
        }
        $parts = explode(':', substr((string) $t, 0, 5));
        if (count($parts) < 2) {
            return null;
        }
        return intval($parts[0]) * 60 + intval($parts[1]);
    }
}
