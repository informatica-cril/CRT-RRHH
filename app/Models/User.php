<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        'role', 'work_type', 'job_profile',
        'dni', 'device_phone', 'pacte_complementaries',
        'complementary_ratio', 'complementary_ratio_pending', 'complementary_ratio_pending_from',
        'relacio', 'disponibilitat_setmanal',
        'second_factor', 'totp_secret', 'totp_confirmed', 'second_factor_by', 'second_factor_at',
        'totp_recovery_codes', 'totp_recovery_generated_at',
        'domi_username', 'domi_privilege',
        /* Quan domi va CONFIRMAR el compte. Diferent de domi_username, que el pot omplir
           l'Excel d'alta massiva sense que domi hagi creat res. */
        'domi_provisioned_at',
        'postal_code_assigned', 'work_schedule_id', 'break_override', 'break_override_time', 'active',
        'privacy_consent', 'seniority_date',
        'onboarding_profile_id',
        'must_change_password',
        'chat_status', 'last_chat_heartbeat',
        'geo_consent_accepted_at',
        // CRIL, CRT o CRIL_CRT: qui treballa a totes dues entitats té accés a totes dues plataformes.
        'entitat',
        // Alumnat en pràctiques (hores del conveni). Només les escriu PractiquesController.
        'practiques', 'practiques_tipus', 'practiques_hores', 'practiques_inici', 'practiques_fi', 'practiques_centre', 'practiques_detall',
    ];

    /**
     * Col·laborador AUTÒNOM? FAIL-SAFE: només amb el valor explícit 'autonom';
     * NULL (no classificat) o qualsevol altra cosa = laboral, el costat segur
     * (el registre horari és obligatori per als laborals, art. 34.9 ET).
     * Mateix contracte que crt_relacio() a domi: mai divergir.
     */
    public function esAutonom(): bool
    {
        return $this->relacio === 'autonom';
    }

    // ── Relationships ──

    public function chatConversations()
    {
        return $this->belongsToMany(ChatConversation::class, 'conversation_user', 'user_id', 'conversation_id')->withTimestamps();
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function ambulatoryCenters()
    {
        return $this->belongsToMany(AmbulatoryCenter::class, 'ambulatory_center_user', 'user_id', 'ambulatory_center_id')
            ->withPivot('valid_from', 'valid_to')
            ->withTimestamps();
    }

    public function municipalAssignments()
    {
        return $this->hasMany(MunicipalAssignment::class);
    }

    public function zones()
    {
        return $this->belongsToMany(Zone::class, 'zone_worker', 'user_id', 'zone_id')
            ->withPivot('valid_from', 'valid_to')
            ->withTimestamps();
    }

    /** Lots de clàusula del contracte que cobreix (B1 Barcelona, B9 Vallès). N:M: n'hi pot cobrir més d'un. */
    public function lots()
    {
        return $this->belongsToMany(Lot::class, 'lot_user')->withTimestamps();
    }

    /** Departaments: servei × modalitat (RHB/LOGO/TO × ambulatòria/domiciliària). */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_user')->withTimestamps();
    }

    public function workLocations()
    {
        return $this->belongsToMany(WorkLocation::class, 'location_worker', 'user_id', 'work_location_id')
            ->withPivot('valid_from', 'valid_to')
            ->withTimestamps();
    }

    public function specialties()
    {
        return $this->belongsToMany(Specialty::class, 'specialty_user', 'user_id', 'specialty_id')
            ->withTimestamps();
    }

    // El secret TOTP i els hashos dels còdis de recuperació no surten MAI per l'API.
    protected $hidden = ['password', 'remember_token', 'totp_secret', 'totp_recovery_codes'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'privacy_consent' => 'boolean',
        'onboarding_completed' => 'boolean',
        'must_change_password' => 'boolean',
        'seniority_date' => 'date',
        'totp_confirmed' => 'boolean',
        'second_factor_at' => 'datetime',
        'domi_provisioned_at' => 'datetime',
        'totp_recovery_generated_at' => 'datetime',
        'practiques' => 'boolean',
    ];

    // ── Relationships ──

    public function workSchedule()
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    public function workLogs()
    {
        return $this->hasMany(WorkLog::class);
    }

    /**
     * Saldo d'hores COMPLEMENTÀRIES de l'any (art. 12.5 ET), per exposar a domi:
     * jornada bàsica anual (work_schedule × dies laborables, exclou festius),
     * màxim legal 30%, sostre de planificació 28% (2% reservat per a extensions
     * pròpies) i consum REAL (work_logs.complementary_minutes de l'any).
     */
    /** Jornada completa (>=37,5 h/setmana): art. 12.4.c ET -> sense hores
        complementàries, només extraordinàries (80 h/any a 1,25x). */
    const COMPL_RATIO_DEFAULT = \App\Support\Jornada::COMPLEMENTARY_MAX_RATIO;   // art. 12.5 ET
    const COMPL_RATIO_MAX     = 0.50;   // sostre del conveni XII
    const COMPL_RESERVA       = 0.02;   // el planificador va sempre 2% per sota del màxim
    // Tope de jornada anual (bossa): contracte + complementàries mai per sobre.
    // El número viu a Support\Jornada: no es torna a escriure enlloc.
    const ANUAL_COMPLETA_H    = \App\Support\Jornada::ANUAL_COMPLETA_H;

    /** Màxim ratio de complementàries que NO fa superar les 1726 h anuals: el diferencial
        entre la jornada que ja fa i la completa. Un parcial alt té molt poc marge. */
    public function complementaryRatioCapByAnnual(): float
    {
        $basic = $this->annualBasicHours();
        if ($basic <= 0) return self::COMPL_RATIO_MAX;
        return max(0.0, self::ANUAL_COMPLETA_H / $basic - 1.0);
    }

    /** Ratio realment USABLE: el pactat, però mai per sobre del que permet el tope de 1726 h. */
    public function usableComplementaryRatio(): float
    {
        return min($this->effectiveComplementaryRatio(), $this->complementaryRatioCapByAnnual());
    }

    /** Ratio de complementàries VIGENT avui, promovent el pendent si ja toca (preavís
        de 7 dies exhaurit). Lectura amb auto-promoció: no cal cap cron. */
    public function effectiveComplementaryRatio(): float
    {
        if ($this->complementary_ratio_pending !== null
            && $this->complementary_ratio_pending_from !== null
            && \Carbon\Carbon::parse($this->complementary_ratio_pending_from)->lte(\Carbon\Carbon::today())) {
            $this->complementary_ratio = $this->complementary_ratio_pending;
            $this->complementary_ratio_pending = null;
            $this->complementary_ratio_pending_from = null;
            $this->save();
        }
        // No hi ha cap "30% per a tothom": sense pacte escrit no hi ha complementàries
        // (art. 12.5 ET) i la jornada completa tampoc en fa. Qui en pacta té el % que ha
        // signat, sense terra; el sostre real és el diferencial de contracte fins a 1726 h.
        if (! $this->pacte_complementaries || $this->isFullTime()) {
            return 0.0;
        }
        $r = (float) ($this->complementary_ratio ?? 0.0);
        return max(0.0, min(self::COMPL_RATIO_MAX, $r));
    }

    /** Ratio que el planificador ha de fer servir: l'usable (que respecta el tope de
        1726 h) menys el 2% de reserva. */
    public function planningComplementaryRatio(): float
    {
        return max(0.0, $this->usableComplementaryRatio() - self::COMPL_RESERVA);
    }

    public function isFullTime(): bool
    {
        $wh = optional($this->workSchedule)->total_hours_weekly;
        return $wh !== null && (float) $wh >= \App\Support\Jornada::SETMANA_COMPLETA_H - 0.01;
    }

    /** Fracció de jornada (0..1) respecte de la completa de 37,5 h/setmana. */
    public function jornadaFraccio(): float
    {
        $wh = optional($this->workSchedule)->total_hours_weekly;
        if ($wh === null || (float) $wh <= 0) return 0.0;
        return min(1.0, (float) $wh / \App\Support\Jornada::SETMANA_COMPLETA_H);
    }

    /** Hores anuals de la jornada BÀSICA contractada = 1726 h × fracció de jornada.
        Mateix model que la bossa de domi (evita el desajust dels dies naturals). */
    public function annualBasicHours(?int $year = null): float
    {
        return round(self::ANUAL_COMPLETA_H * $this->jornadaFraccio(), 2);
    }

    /** Percentatge de jornada respecte de la completa (37,5 h/setmana = 100%). */
    public function jornadaPct(?int $year = null): float
    {
        return round($this->jornadaFraccio() * 100, 1);
    }

    /**
     * Materialitza el PACTE de complementàries com a codi anual. Es crida SOLA a
     * l'alta/edició del treballador amb pacte: cap alta manual duplicada a Codis.
     * Les hores ja autoritzades vigents I les consumides de codis revocats compten:
     * un pacte esgotat dins l'any no torna a néixer.
     */
    public function materialitzaPacte(?int $generatedBy = null): ?\App\Models\AuthorizationCode
    {
        if ($this->isFullTime() || ! (bool) $this->pacte_complementaries) return null;
        $viu = \App\Models\AuthorizationCode::where('user_id', $this->id)
            ->where('type', '!=', 'extraordinaria')->where('revoked', false)
            ->whereDate('valid_from', '<=', now())
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', now()))
            ->exists();
        if ($viu) return null;

        $year = now()->year;
        $capH = (float) ($this->complementaryStats($year)['max_anual_h'] ?? 0);
        if ($capH <= 0) return null;

        $vigents = round((float) \App\Models\AuthorizationCode::where('user_id', $this->id)
            ->where('type', '!=', 'extraordinaria')->where('revoked', false)
            ->whereYear('valid_from', $year)->sum('authorized_hours'), 2);
        $consumitRevocat = round((float) \Illuminate\Support\Facades\DB::table('work_logs')
            ->join('authorization_codes as ac', 'ac.code', '=', 'work_logs.authorized_extra_code')
            ->where('ac.user_id', $this->id)->where('ac.type', '!=', 'extraordinaria')
            ->where('ac.revoked', true)->whereYear('ac.valid_from', $year)
            ->selectRaw('SUM(COALESCE(NULLIF(work_logs.extra_hours_authorized,0), work_logs.complementary_minutes/60, 0)) h')
            ->value('h'), 2);
        $disponible = round($capH - $vigents - $consumitRevocat, 2);
        if ($disponible <= 0) return null;

        return \App\Models\AuthorizationCode::create([
            'code' => strtoupper(bin2hex(random_bytes(4))),
            'concept' => "Pacte de complementàries signat (art. 12.5) — alta anual {$year}",
            'authorized_hours' => $disponible,
            'type' => 'complementaria',
            'factor' => 1.00,
            'generated_by' => $generatedBy ?: $this->id,
            'user_id' => $this->id,
            'valid_from' => now()->toDateString(),
            'valid_to' => "{$year}-12-31",
        ]);
    }

    public function complementaryStats(?int $year = null): array
    {
        $year = $year ?: now()->year;
        $annualBasic = $this->annualBasicHours($year);
        $consumedMin = (int) $this->workLogs()->whereYear('date', $year)->sum('complementary_minutes');

        $ratioMax   = $this->effectiveComplementaryRatio();          // el pactat
        $ratioUsable = $this->usableComplementaryRatio();            // limitat pel tope de 1726
        $ratioPlan  = $this->planningComplementaryRatio();           // usable − 2%
        $capTope    = $ratioUsable < $ratioMax - 0.0001;             // el 1726 mossega el pactat?

        return [
            'year' => $year,
            'annual_basic_h' => $annualBasic,
            'jornada_pct' => $this->jornadaPct($year),
            'ratio_max' => $ratioMax,               // % pactat de complementàries
            'ratio_usable' => $ratioUsable,         // % que de fet es pot fer sense superar 1726 h
            'ratio_planificacio' => $ratioPlan,     // el que fa servir el planificador (usable − 2%)
            'limitat_per_tope_anual' => $capTope,
            'max_anual_h' => round($annualBasic * $ratioUsable, 2),
            'cap_planificacio_h' => round($annualBasic * $ratioPlan, 2),
            'tope_anual_h' => self::ANUAL_COMPLETA_H,
            'total_amb_complementaries_h' => round($annualBasic * (1 + $ratioUsable), 2),
            'consumides_h' => round($consumedMin / 60, 2),
        ];
    }

    public function absences()
    {
        return $this->hasMany(Absence::class);
    }

    public function cpAssignments()
    {
        return $this->hasMany(PostalCodeAssignment::class);
    }

    public function excedencias()
    {
        return $this->hasMany(Excedencia::class);
    }

    public function documentSignatures()
    {
        return $this->hasMany(DocumentSignature::class);
    }

    public function onboardingStatus()
    {
        return $this->hasOne(OnboardingStatus::class);
    }

    public function onboardingProfile()
    {
        return $this->belongsTo(OnboardingProfile::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeWorkers($query)
    {
        return $query->where('role', 'worker');
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeCoordinators($query)
    {
        return $query->where('role', 'coordinator');
    }

    // ── Helpers ──

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isWorker(): bool
    {
        return $this->role === 'worker';
    }

    public function isCoordinator(): bool
    {
        return $this->role === 'coordinator';
    }

    /**
     * Personal de gestió (admin, coordinació o RRHH): els rols que legítimament
     * poden veure/gestionar dades de TERCERS. Un 'worker' només accedeix a les
     * seves pròpies dades. Base de l'autorització del backend (middleware 'owner'
     * i trait AuthorizesOwnership); l'enforcement NO pot dependre del frontend.
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'coordinator', 'hr'], true);
    }

    /**
     * Comprueba si el usuario puede gestionar zonas y horarios
     * (admin o coordinator).
     */
    public function canManageZonesAndSchedules(): bool
    {
        return in_array($this->role, ['admin', 'coordinator']);
    }

    public function getActiveCpAssignment()
    {
        $today = now()->toDateString();
        return $this->cpAssignments()
            ->where('valid_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $today);
            })
            ->orderBy('valid_from', 'desc')
            ->first();
    }
}
