<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\Zone;
use App\Models\WorkLocation;
use App\Models\AmbulatoryCenter;
use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\Excedencia;
use App\Models\ExcedenciaType;
use App\Models\Holiday;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    use \App\Http\Controllers\Concerns\RegistraDecisions;

    /** Dades de CONTACTE que RRHH pot corregir a la fitxa d'una altra persona. */
    public const CAMPS_CONTACTE = ['name', 'email', 'device_phone'];

    /** Només administració pot crear (o fer) administradors; RRHH no. */
    public const NOMES_ADMIN = 'Només un administrador pot donar d\'alta un altre administrador (p. ex. el lloc «Gerencia»).';

    public function index()
    {
        // Excluir la columna password de la consulta (ya está en $hidden,
        // pero así no se transfiere desde la BD). makeHidden es redundante
        // porque $hidden ya la oculta, pero se mantiene por compatibilidad.
        return response()->json(
            User::select(['id', 'name', 'email', 'role', 'work_type', 'job_profile',
                          'relacio', 'disponibilitat_setmanal',
                          'dni', 'device_phone', 'postal_code_assigned', 'work_schedule_id', 'active',
                          /* Perquè la pantalla pugui dir quants comptes de domi falten sense
                             demanar la fitxa de cadascú. */
                          'domi_username', 'domi_provisioned_at',
                          'privacy_consent', 'seniority_date', 'onboarding_profile_id',
                          'onboarding_completed', 'must_change_password', 'chat_status',
                          'last_chat_heartbeat', 'geo_consent_accepted_at', 'created_at', 'updated_at'])
                ->with('workSchedule')
                ->get()
        );
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        abort_if(($data['role'] ?? null) === 'admin' && ! $request->user()->isAdmin(), 403, self::NOMES_ADMIN);

        $data['password'] = Hash::make($data['password']);
        // ENS: la contrasenya de l'alta la tria (i la veu) qui dona d'alta; la persona l'ha de
        // canviar en entrar per primer cop. Abans no es marcava i entrava sense canviar-la.
        $data['must_change_password'] = true;
        $user = User::create($data);

        // Alta domiciliària → el compte domi es crea SOL (aprovisionament servei-a-servei; res manual).
        // Si domi no respon, l'alta a RRHH continua i es reintenta amb domi:provisiona-pendents.
        $domi = null;
        if ($user->role === 'worker' && str_starts_with((string) $user->work_type, 'DOMICILIARIA')) {
            $domi = \App\Services\DomiClient::provisiona($user);
        }

        // Pacte de complementàries a l'alta → el codi anual neix SOL (cap alta manual a Codis).
        try { $user->materialitzaPacte($request->user()->id); } catch (\Throwable $e) {}

        return response()->json($user->makeHidden(['password'])->toArray() + ($domi !== null ? ['domi' => $domi] : []), 201);
    }

    /**
     * Persones que fitxen i no tenen horari assignat (els autònoms no fan registre de jornada).
     * Sense horari no es poden calcular les hores fora d'horari, les extres ni la pausa.
     */
    public static function senseHorari()
    {
        // Un horari sense cap dia actiu és com no tenir-ne (n'hi havia amb 0 dies i 30 h setmanals).
        // Es filtra aquí i no a SQL: els dies són JSON i la BD de producció i la dels tests difereixen.
        return User::with('workSchedule')->where('active', true)->where('role', 'worker')
            ->where(fn ($q) => $q->whereNull('relacio')->orWhere('relacio', '<>', 'autonom'))
            ->orderBy('name')->get()
            ->filter(fn (User $u) => ! $u->workSchedule
                || ! collect(\App\Models\WorkSchedule::normalizeDays($u->workSchedule->days))->contains('active', true))
            ->values();
    }

    /** GET /api/v1/users/sense-horari — llista per a la Safata (admin i RRHH). */
    public function llistaSenseHorari()
    {
        return response()->json(self::senseHorari()->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'job_profile' => $u->job_profile,
            'created_at' => $u->created_at, 'motiu' => $u->workSchedule ? 'Horari sense cap dia' : 'Sense horari',
        ]));
    }

    public function show(User $user)
    {
        return response()->json($user->load(['workSchedule', 'specialties', 'lots', 'departments'])->makeHidden(['password']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();

        // AUTORITZACIÓ: un no-admin només pot editar-se a SI MATEIX i només camps
        // bàsics de perfil. Els camps privilegiats i la CONTRASENYA queden fora
        // (el canvi propi de contrasenya té endpoint dedicat: auth/change-password).
        //
        // EXCEPCIÓ: RRHH pot corregir les DADES DE CONTACTE de qualsevol persona.
        // Era absurd que pogués esborrar una fitxa sencera i no li pogués canviar
        // un telèfon mal escrit.
        if (! $request->user()->isAdmin()) {
            $esGestioRrhh = $request->user()->role === 'hr';
            $propia = (int) $request->user()->id === (int) $user->id;
            abort_unless($propia || $esGestioRrhh, 403, 'No autoritzat');

            if ($esGestioRrhh && ! $propia) {
                // Contacte i prou: res de rol, jornada, pacte ni contrasenya.
                $data = array_intersect_key($data, array_flip(self::CAMPS_CONTACTE));
            } else {
                foreach (['role', 'active', 'work_type', 'job_profile', 'work_schedule_id', 'device_phone',
                          'pacte_complementaries', 'relacio', 'disponibilitat_setmanal',
                          'seniority_date', 'onboarding_profile_id', 'onboarding_completed',
                          'must_change_password', 'password'] as $field) {
                    unset($data[$field]);
                }
            }
        }

        // work_type és NOT NULL: si el formulari l'envia buit (p. ex. treballadors importats
        // sense servei), es manté el valor actual en comptes de petar a la BD.
        if (array_key_exists('work_type', $data) && $data['work_type'] === null) {
            unset($data['work_type']);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $horariAbans = $user->work_schedule_id;
        $user->update($data);
        // Canvi d'horari: es tornen a calcular els trams dels seus fitxatges pendents.
        $tramsRecalculats = 0;
        if ((int) $user->work_schedule_id !== (int) $horariAbans) {
            $tramsRecalculats = app(\App\Services\RecalculaTrams::class)->perUsuaris([$user->id], $request->user());
        }

        // Si l'edició activa el pacte, el codi anual neix SOL aquí mateix.
        if (array_key_exists('pacte_complementaries', $data) && $data['pacte_complementaries']) {
            try { $user->materialitzaPacte($request->user()->id); } catch (\Throwable $e) {}
        }

        // Especialitat clínica: dada privilegiada (com job_profile), només admin.
        if ($request->user()->isAdmin() && $request->has('specialties')) {
            $ids = \App\Models\Specialty::whereIn('code', (array) $request->input('specialties'))->pluck('id');
            $user->specialties()->sync($ids);
        }

        /* Lot territorial i departament (Direcció, 31-07-2026). Els assignen admin i RRHH,
           que són qui munta el quadre de disponibilitat i qui reparteix la feina; un
           treballador no s'ha de poder canviar de lot ell mateix.
           Es fa per CODI i no per id: així un Excel d'alta massiva o una API externa poden
           dir «B1» sense saber quin número li ha tocat a la taula d'aquesta instància. */
        if ($request->user()->isStaff() && $request->user()->role !== 'coordinator') {
            if ($request->has('lots')) {
                $ids = \App\Models\Lot::whereIn('code', (array) $request->input('lots'))->pluck('id');
                $user->lots()->sync($ids);
            }
            if ($request->has('departments')) {
                $ids = \App\Models\Department::whereIn('code', (array) $request->input('departments'))->pluck('id');
                $user->departments()->sync($ids);
            }
        }

        return response()->json(array_merge(
            $user->fresh()->load(['specialties', 'lots', 'departments'])->makeHidden(['password'])->toArray(),
            ['trams_recalculats' => $tramsRecalculats]
        ));
    }

    /**
     * POST /api/v1/users/{user}/provisiona-domi — crea el compte d'aquesta persona a domi.
     *
     * ── PER QUÈ CAL UN BOTÓ I NO NOMÉS LA COMANDA ───────────────────────────────────
     * L'alta individual ja aprovisiona sola, però la CÀRREGA MASSIVA no: 80 crides HTTP
     * dins d'una sola petició web no caben. Després d'una càrrega, doncs, la plantilla és a
     * RRHH i no a domi, i fins ara l'única manera de resoldre-ho era que algú entrés al
     * servidor a executar `php artisan domi:provisiona-pendents --apply`.
     *
     * Amb això, RRHH pot arreglar una persona concreta des de la seva fitxa, sense
     * dependre de ningú i veient el resultat. La comanda segueix existint per a les tandes.
     *
     * És IDEMPOTENT: domi tracta la petició com a tal i torna el compte que ja tingués. Per
     * això es pot prémer dues vegades sense por —i per això la migració no va marcar ningú
     * com a aprovisionat: reintentar no fa mal, i donar per bo el que no ho és, sí.
     */
    public function provisionaDomi(Request $request, User $user)
    {
        if ($user->role !== 'worker' || ! str_starts_with((string) $user->work_type, 'DOMICILIARIA')) {
            return response()->json([
                'ok' => false,
                'error' => 'Només el personal domiciliari té compte a domi. '
                         . 'Reviseu el rol i l\'àmbit de la fitxa.',
            ], 422);
        }

        $r = \App\Services\DomiClient::provisiona($user);
        $user->refresh();

        return response()->json([
            'ok' => (bool) ($r['ok'] ?? false),
            'error' => $r['error'] ?? null,
            'domi_username' => $user->domi_username,
            'domi_provisioned_at' => $user->domi_provisioned_at,
        ], ($r['ok'] ?? false) ? 200 : 502);
    }

    /**
     * POST /api/v1/users/provisiona-domi-pendents — crea a domi TOTS els comptes que falten.
     *
     * El mateix que `php artisan domi:provisiona-pendents --apply`, però des de la pantalla.
     * Direcció (31-07-2026): «vull dependre d'informàtica el mínim possible». Després d'una
     * càrrega massiva, això és exactament el pas que obligava a trucar a algú.
     *
     * ── PER QUÈ VA PER TANDES I NO TOTS DE COP ──────────────────────────────────────
     * Cada alta és una crida HTTP a domi amb 8 s de timeout. Amb 80 persones, en el pitjor
     * cas són 10 minuts: qualsevol servidor web talla la petició abans i la càrrega es
     * queda a mitges sense que ningú sàpiga per on anava. Es fan MAX_TANDA per petició i es
     * torna quants en queden; la pantalla ho repeteix fins a zero. Així cada tanda acaba, i
     * si una falla, les anteriors ja estan fetes.
     */
    public const MAX_TANDA = 15;

    public function provisionaDomiPendents(Request $request)
    {
        $pendents = User::where('role', 'worker')
            ->where('work_type', 'like', 'DOMICILIARIA%')
            ->whereNull('domi_provisioned_at')
            ->where('active', true)
            ->orderBy('id')
            ->limit(self::MAX_TANDA)
            ->get();

        $fets = 0;
        $errors = [];
        foreach ($pendents as $u) {
            $r = \App\Services\DomiClient::provisiona($u);
            if ($r['ok'] ?? false) { $fets++; }
            else { $errors[] = $u->name . ': ' . ($r['error'] ?? 'domi no ha respost'); }
        }

        $queden = User::where('role', 'worker')
            ->where('work_type', 'like', 'DOMICILIARIA%')
            ->whereNull('domi_provisioned_at')
            ->where('active', true)->count();

        return response()->json([
            'ok' => true,
            'fets' => $fets,
            'queden' => $queden,
            /* La pantalla ha de saber si val la pena tornar-hi. Si una tanda no ha fet CAP
               alta i encara en queden, insistir només repetiria el mateix error: el que
               falla és domi o la configuració, no la tanda. */
            'continua' => $queden > 0 && $fets > 0,
            'errors' => $errors,
        ]);
    }

    /**
     * DELETE /api/v1/users/{user} — BAIXA, no supressió.
     *
     * Qui té registre horari no es pot esborrar: l'art. 34.9 ET obliga a conservar-lo
     * 4 anys i el DELETE se l'enduia en cascada. En aquest cas es desactiva la fitxa
     * (deixa d'entrar a l'aplicació) i el registre es queda. Només s'esborra de debò
     * una fitxa que no ha arribat a fitxar mai (alta errònia).
     */
    public function destroy(Request $request, User $user)
    {
        $fitxatges = WorkLog::where('user_id', $user->id)->count();

        if ($fitxatges > 0) {
            $user->update(['active' => false]);
            $this->registraDecisio($request, 'USER_DEACTIVATED', 'user', $user->id,
                sprintf('Baixa de %s (conserva %d fitxatges, art. 34.9 ET), feta per %s',
                    $user->name, $fitxatges, $request->user()->name));

            return response()->json([
                'message' => 'Persona donada de BAIXA. No s\'esborra: té ' . $fitxatges
                    . ' registres de jornada que la llei obliga a conservar 4 anys (art. 34.9 ET). '
                    . 'La fitxa queda desactivada i ja no pot entrar a l\'aplicació.',
                'deactivated' => true,
                'work_logs_kept' => $fitxatges,
            ], 200);
        }

        $nom = $user->name;
        $id = $user->id;
        $user->delete();
        $this->registraDecisio($request, 'USER_DELETED', 'user', $id,
            sprintf('Fitxa de %s esborrada (sense cap registre horari), per %s', $nom, $request->user()->name));

        return response()->json(['message' => 'Usuari eliminat', 'deactivated' => false], 200);
    }

    /**
     * PUT /api/v1/users/{user}/2fa — l'ADMIN determina el mètode de segon factor.
     * 'dispositiu' (llave d'empresa) o 'totp' (Google Authenticator, per a qui treballa
     * des de casa). Camp privilegiat: només admin (ho garanteix la ruta amb role:admin).
     */
    public function setSecondFactor(Request $request, User $user)
    {
        $data = $request->validate(['method' => 'required|in:dispositiu,totp']);
        \App\Support\SecondFactor::adminSetMetode($user, $data['method'], $request->user()->email);
        return response()->json(['success' => true, 'method' => $data['method'],
            'message' => $data['method'] === 'totp'
                ? 'Assignat Google Authenticator. L\'usuari ha de completar l\'enrolament.'
                : 'Assignat dispositiu d\'empresa.']);
    }

    public function bulkStore(Request $request)
    {
        $request->validate(['users' => 'required|array']);
        abort_if(! $request->user()->isAdmin()
            && collect($request->users)->contains(fn ($u) => ($u['role'] ?? null) === 'admin'), 403, self::NOMES_ADMIN);

        $created = [];
        foreach ($request->users as $userData) {
            $password = $userData['password'] ?? 'changeme';
            if (!str_starts_with($password, '$2y$')) {
                $password = Hash::make($password);
            }
            $userData['password'] = $password;
            // Igual que l'alta individual: qui dona d'alta coneix la contrasenya, així que s'ha
            // de canviar en el primer accés (encara que la pantalla enviï el contrari).
            $userData['must_change_password'] = true;
            $created[] = User::create($userData)->makeHidden(['password']);
        }

        return response()->json($created, 201);
    }

    public function bulkIndex()
    {
        $today = now()->toDateString();

        return response()->json([
            'users' => User::with('workSchedule')->get()->makeHidden(['password']),
            'work_schedules' => WorkSchedule::all(),
            'zones' => Zone::where('active', true)->get(),
            'work_locations' => WorkLocation::where('active', true)->get(),
            'zone_assignments' => Zone::whereHas('users', function($q) use ($today) {
                $q->where('zone_worker.valid_from', '<=', $today)
                  ->where(function($sq) use ($today) {
                      $sq->whereNull('zone_worker.valid_to')->orWhere('zone_worker.valid_to', '>=', $today);
                  });
            })->with(['users' => function($q) use ($today) {
                $q->wherePivot('valid_from', '<=', $today)
                  ->wherePivot('valid_to', null)
                  ->orWherePivot('valid_to', '>=', $today);
            }])->get(),
            'location_assignments' => WorkLocation::whereHas('users', function($q) use ($today) {
                $q->where('location_worker.valid_from', '<=', $today)
                  ->where(function($sq) use ($today) {
                      $sq->whereNull('location_worker.valid_to')->orWhere('location_worker.valid_to', '>=', $today);
                  });
            })->with(['users' => function($q) use ($today) {
                $q->wherePivot('valid_from', '<=', $today)
                  ->wherePivot('valid_to', null)
                  ->orWherePivot('valid_to', '>=', $today);
            }])->get(),
            'ambulatory_centers' => AmbulatoryCenter::with('workLocations')->where('active', true)->get(),
            'center_assignments' => AmbulatoryCenter::whereHas('users', function($q) use ($today) {
                $q->where('ambulatory_center_user.valid_from', '<=', $today)
                  ->where(function($sq) use ($today) {
                      $sq->whereNull('ambulatory_center_user.valid_to')->orWhere('ambulatory_center_user.valid_to', '>=', $today);
                  });
            })->with(['workLocations', 'users' => function($q) use ($today) {
                $q->wherePivot('valid_from', '<=', $today)
                  ->wherePivot('valid_to', null)
                  ->orWherePivot('valid_to', '>=', $today);
            }])->get(),
        ]);
    }

    public function workerBulkInfo(Request $request)
    {
        $user = $request->user();
        $today = now()->toDateString();
        $year = now()->year;

        // Fetch user with workSchedule in one go
        $user->load(['workSchedule', 'zones', 'workLocations', 'ambulatoryCenters']);

        $absenceTypes = AbsenceType::all();
        $absences = Absence::where('user_id', $user->id)
            ->with('absenceType:id,name')
            ->orderBy('start_date', 'desc')
            ->get();

        $permissionUsage = [];
        foreach ($absenceTypes as $type) {
            if ($type->max_per_year) {
                // Filter in-memory to avoid N+1 or repeated DB calls
                $used = $absences->where('absence_type_id', $type->id)
                    ->where('approved', '!=', false)
                    ->filter(function($a) use ($year) {
                         // Carbon instance check for safety
                         return (is_string($a->start_date) ? \Carbon\Carbon::parse($a->start_date) : $a->start_date)->year === $year;
                    })
                    ->sum(function($a) {
                        $start = is_string($a->start_date) ? \Carbon\Carbon::parse($a->start_date) : $a->start_date;
                        $end = is_string($a->end_date) ? \Carbon\Carbon::parse($a->end_date) : $a->end_date;
                        return $start->diffInDays($end) + 1;
                    });
                $permissionUsage[$type->id] = $used;
            }
        }

        // Cache relationships to avoid re-querying
        $allZones = $user->zones;
        $allLocations = $user->workLocations;
        $allCenters = $user->ambulatoryCenters;

        // Filter active zones by pivot dates
        $activeZones = $allZones->filter(function ($z) use ($today) {
            $from = $z->pivot->valid_from ?? null;
            $to = $z->pivot->valid_to ?? null;
            return (!$from || $from <= $today) && (!$to || $to >= $today);
        });

        // Active ambulatory center (valid today)
        $activeCenter = $allCenters->first(function ($c) use ($today) {
            $from = $c->pivot->valid_from ?? null;
            $to = $c->pivot->valid_to ?? null;
            return (!$from || $from <= $today) && (!$to || $to >= $today);
        });

        return response()->json([
            'user' => $user,
            'absence_types' => $absenceTypes,
            'absences' => $absences,
            'permission_usage' => $permissionUsage,
            'excedencia_types' => ExcedenciaType::all(),
            'excedencias' => Excedencia::where('user_id', $user->id)->get(),
            'cp_assignment' => $activeZones->where('type', 'CP')->first(),
            'muni_assignment' => $activeZones->where('type', 'MUNICIPALITY')->first(),
            'active_location' => $allLocations->first(),
            'cp_history' => $allZones->where('type', 'CP')->values(),
            'muni_history' => $allZones->where('type', 'MUNICIPALITY')->values(),
            'location_history' => $allLocations,
            'ambulatory_center' => $activeCenter,
            'ambulatory_center_history' => $allCenters->values(),
            'holidays' => Holiday::where('year', '>=', $year)->get(),
            'jornada_pla' => \Illuminate\Support\Facades\DB::table('domi_jornada_pla')
                ->where('user_id', $user->id)
                ->where('date', '>=', now()->subDays(7)->toDateString())
                ->orderBy('date')
                ->get(['date', 'jornada_min', 'sortida', 'finestra_fi', 'surt_abans_min']),
            'work_logs' => WorkLog::where('user_id', $user->id)
                ->orderBy('date', 'desc')
                ->limit(50)
                ->get(),
            // Perque el frontend pugui corregir comptadors en viu (jornada, pausa) si
            // el rellotge del dispositiu va desquadrat, calculant un offset relatiu.
            'server_time' => now()->toDateTimeString(),
        ]);
    }
}
