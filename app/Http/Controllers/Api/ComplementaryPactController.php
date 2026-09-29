<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pacte d'hores complementàries: l'accepta la PERSONA TREBALLADORA, per quinzena.
 *
 * ── LA REGLA QUE FA QUE AIXÒ SERVEIXI DE PROVA ──────────────────────────────────────
 *   **Només un mateix pot acceptar el seu pacte.** Ni administració ni Recursos Humans poden
 *   acceptar-lo per ningú, i el codi ho impedeix: no hi ha cap paràmetre d'usuari a la crida
 *   d'acceptació —s'agafa de qui està autenticat—, de manera que no hi ha manera d'acceptar
 *   en nom d'un altre encara que es manipuli la petició.
 *
 *   Aquesta és la diferència entre un consentiment i una casella. L'art. 12.5 ET exigeix pacte
 *   **escrit i voluntari**; un tic que pot posar un tercer no acredita voluntat de ningú.
 */
class ComplementaryPactController extends Controller
{
    /** Durada del període. Quinze dies: coincideix amb la declaració quinzenal del servei. */
    public const DIES_PERIODE = 15;

    /** Versió del text acceptat. Canviar-la quan canviï la redacció, mai retroactivament. */
    public const TEXT_VERSIO = '1.0';

    /**
     * El text que s'accepta. Es retorna amb l'estat perquè la pantalla no en tingui una còpia
     * pròpia: si el text visquessin a dos llocs, acabarien divergint i no se sabria quin es va
     * acceptar de debò.
     */
    public static function textPacte(float $maxHores): array
    {
        return [
            'versio' => self::TEXT_VERSIO,
            'titol'  => "Pacte d'hores complementàries",
            'punts'  => [
                "Accepto realitzar hores complementàries durant els propers " . self::DIES_PERIODE . " dies, fins a un màxim de " . number_format($maxHores, 1, ',', '.') . " hores.",
                "L'acceptació és **voluntària** i es refereix **només a aquest període**. Passats els " . self::DIES_PERIODE . " dies caduca sola i no es prorroga.",
                "Puc **revocar-la en qualsevol moment**. La revocació afecta les hores encara no realitzades; les ja prestades es liquiden igualment.",
                "Les hores complementàries es retribueixen com estableixen el conveni i l'art. 12.5 de l'Estatut dels Treballadors.",
                "No acceptar aquest pacte **no té cap conseqüència** sobre la meva relació laboral.",
            ],
        ];
    }

    /** Inici de la quinzena que conté una data: dies 1 i 16 de cada mes. */
    private function iniciPeriode(Carbon $d): Carbon
    {
        return $d->day <= 15 ? $d->copy()->startOfMonth() : $d->copy()->startOfMonth()->addDays(15);
    }

    private function fiPeriode(Carbon $inici): Carbon
    {
        return $inici->day === 1 ? $inici->copy()->addDays(14) : $inici->copy()->endOfMonth();
    }

    /**
     * Estat del pacte de qui consulta: el període actual, el següent, i si estan acceptats.
     *
     * Es retornen ELS DOS perquè el professional ha de poder acceptar el següent abans que
     * comenci: si només es pogués acceptar el període en curs, la planificació de la quinzena
     * vinent no es podria tancar fins al primer dia, que és tard.
     */
    /** GET /api/v1/pacte-complementaries/info/{user} — jornada i tope d'un treballador
        (per a la gestió a RRHH). */
    public function infoTreballador(\App\Models\User $user)
    {
        return response()->json([
            'jornada_pct' => $user->jornadaPct(),
            'annual_basic_h' => $user->annualBasicHours(),
            'ratio_actual' => $user->effectiveComplementaryRatio(),
            'ratio_cap_tope' => round($user->complementaryRatioCapByAnnual(), 2),
            'ratio_usable' => $user->usableComplementaryRatio(),
            'jornada_completa' => $user->isFullTime(),
            'tope_anual_h' => \App\Models\User::ANUAL_COMPLETA_H,
        ]);
    }

    /** POST /api/v1/pacte-complementaries/ratio — modula el % de complementàries d'un
        treballador (30%..50% del conveni) amb PREAVÍS de 7 dies. Gestió (admin/hr). */
    public function modulaRatio(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'ratio'   => 'required|numeric|min:0|max:0.50',   // granular; el màxim real el marca el tope de 1726 h
        ]);
        $u = \App\Models\User::findOrFail($data['user_id']);
        if ($u->isFullTime()) {
            return response()->json(['message' => 'Jornada completa: no admet hores complementàries (art. 12.4.c ET).'], 422);
        }
        $ratio = round((float) $data['ratio'], 2);
        $basic = $u->annualBasicHours();
        $capTope = round($u->complementaryRatioCapByAnnual(), 2);
        $maxPactable = min(\App\Models\User::COMPL_RATIO_MAX, $capTope);

        // Si no ho pot FER (superaria les 1726 h), tampoc ho pot PACTAR: es rebutja.
        if ($ratio > $maxPactable + 0.0001) {
            return response()->json([
                'message' => 'Amb el ' . round($u->jornadaPct(), 0) . '% de jornada que ja fa, no pot pactar un '
                    . (int) round($ratio * 100) . '% de complementàries: superaria les '
                    . (int) \App\Models\User::ANUAL_COMPLETA_H . ' h anuals. El màxim que pot pactar és el '
                    . (int) round($maxPactable * 100) . '%.',
                'ratio_max_pactable' => $maxPactable,
                'jornada_pct' => $u->jornadaPct(),
            ], 422);
        }

        $desde = \Carbon\Carbon::today()->addDays(7);
        $u->complementary_ratio_pending = $ratio;
        $u->complementary_ratio_pending_from = $desde->toDateString();
        $u->save();

        $totalH = round($basic * (1 + $ratio), 1);
        return response()->json([
            'ok' => true,
            'ratio_actual' => $u->effectiveComplementaryRatio(),
            'ratio_pendent' => $ratio,
            'ratio_max_pactable' => $maxPactable,
            'jornada_pct' => $u->jornadaPct(),
            'total_amb_complementaries_h' => $totalH,
            'tope_anual_h' => \App\Models\User::ANUAL_COMPLETA_H,
            'efecte' => $desde->toDateString(),
            'planificacio' => round(max(0.0, $ratio - \App\Models\User::COMPL_RESERVA), 2),
            'missatge' => 'El nou percentatge (' . (int) round($ratio * 100) . '%) tindrà efecte el '
                . $desde->format('d/m/Y') . ' (preavís de 7 dies). El planificador farà servir un 2% menys.',
        ]);
    }

    public function estat(Request $request)
    {
        $u = $request->user();
        $avui = Carbon::today();

        $periodes = [];
        $ini = $this->iniciPeriode($avui);
        for ($i = 0; $i < 2; $i++) {
            $fi = $this->fiPeriode($ini);
            $p  = DB::table('complementary_pacts')
                ->where('user_id', $u->id)
                ->whereDate('period_start', $ini->toDateString())
                ->first();

            $periodes[] = [
                'period_start' => $ini->toDateString(),
                'period_end'   => $fi->toDateString(),
                'actual'       => $i === 0,
                'acceptat'     => $p && !$p->revoked_at,
                'accepted_at'  => $p->accepted_at ?? null,
                'max_hours'    => $p->max_hours ?? null,
                'revoked_at'   => $p->revoked_at ?? null,
            ];
            $ini = $fi->copy()->addDay();
        }

        $completa = $u->isFullTime();
        return response()->json([
            'pot_pactar' => $u->work_type === 'DOMICILIARIA' && $u->relacio !== 'autonom' && ! $completa,
            'jornada_completa' => $completa,
            'motiu_no'   => $u->relacio === 'autonom'
                ? "Els col·laboradors autònoms no tenen hores complementàries: no és una figura de la relació laboral."
                : ($completa
                    ? "Jornada completa (37,5 h = 100%): la llei no admet hores complementàries en un contracte a temps complet (art. 12.4.c ET). Només hores extraordinàries: màxim 80 h/any a 1,25× el preu ordinari."
                    : null),
            'max_hores_periode' => $this->maxHoresPeriode($u),
            'text'      => self::textPacte($this->maxHoresPeriode($u)),
            'periodes'  => $periodes,
            'ratio_actual' => $u->effectiveComplementaryRatio(),
            'ratio_usable' => $u->usableComplementaryRatio(),
            'ratio_pendent' => $u->complementary_ratio_pending !== null ? (float) $u->complementary_ratio_pending : null,
            'ratio_pendent_des_de' => $u->complementary_ratio_pending_from,
            'ratio_max_conveni' => \App\Models\User::COMPL_RATIO_MAX,
            'ratio_cap_tope' => round($u->complementaryRatioCapByAnnual(), 2),
            'jornada_pct' => $u->jornadaPct(),
            'ratio_planificacio' => $u->planningComplementaryRatio(),
        ]);
    }

    /**
     * Sostre d'hores per quinzena.
     *
     * És el **30% de la jornada del període** que permet l'art. 12.5 ET. El 28% que fa servir
     * la planificació surt d'aquí a domi, amb un 2% de reserva: si es planifiqués el 30%
     * sencer, qualsevol desviació real deixaria l'empresa fora del límit legal.
     */
    private function maxHoresPeriode(User $u): float
    {
        $setmanals = (float) DB::table('work_schedules')
            ->where('id', $u->work_schedule_id)->value('total_hours_weekly') ?: 0.0;
        if ($setmanals <= 0) { return 0.0; }
        return round($setmanals * (self::DIES_PERIODE / 7) * 0.30, 2);
    }

    /** Accepta el pacte del període indicat. Sempre per a un mateix. */
    public function accepta(Request $request)
    {
        $u = $request->user();

        if ($u->relacio === 'autonom') {
            return response()->json(['ok' => false,
                'error' => "Els col·laboradors autònoms no tenen hores complementàries."], 422);
        }

        $inici = Carbon::parse($request->input('period_start', Carbon::today()->toDateString()))->startOfDay();
        $inici = $this->iniciPeriode($inici);
        $fi    = $this->fiPeriode($inici);

        /* No s'accepta un període ja tancat: un consentiment retroactiu no és un consentiment. */
        if ($fi->lt(Carbon::today())) {
            return response()->json(['ok' => false,
                'error' => "Aquest període ja ha acabat. El pacte no es pot acceptar amb efecte retroactiu."], 422);
        }

        $max = $this->maxHoresPeriode($u);
        if ($max <= 0) {
            return response()->json(['ok' => false,
                'error' => "No tens jornada assignada, i sense jornada no es pot calcular el màxim d'hores complementàries."], 422);
        }

        DB::table('complementary_pacts')->updateOrInsert(
            ['user_id' => $u->id, 'period_start' => $inici->toDateString()],
            [
                'period_end'     => $fi->toDateString(),
                'max_hours'      => $max,
                'accepted_at'    => now(),
                'accepted_ip'    => $request->ip(),
                'accepted_agent' => substr((string) $request->userAgent(), 0, 255),
                'text_version'   => self::TEXT_VERSIO,
                'revoked_at'     => null,
                'revoked_reason' => null,
                'updated_at'     => now(),
                'created_at'     => now(),
            ]
        );

        $this->sincronitzaTic($u);

        return response()->json(['ok' => true, 'period_start' => $inici->toDateString(),
                                 'period_end' => $fi->toDateString(), 'max_hours' => $max]);
    }

    /** Revoca el pacte d'un període. No l'esborra: el tanca amb data i motiu. */
    public function revoca(Request $request)
    {
        $u = $request->user();
        $inici = $this->iniciPeriode(Carbon::parse($request->input('period_start', Carbon::today()->toDateString())));

        $n = DB::table('complementary_pacts')
            ->where('user_id', $u->id)
            ->whereDate('period_start', $inici->toDateString())
            ->whereNull('revoked_at')
            ->update([
                'revoked_at'     => now(),
                'revoked_reason' => substr((string) $request->input('motiu', ''), 0, 255),
                'updated_at'     => now(),
            ]);

        $this->sincronitzaTic($u);

        return response()->json(['ok' => $n > 0,
            'error' => $n > 0 ? null : "No hi havia cap pacte viu per a aquest període."]);
    }

    /**
     * Manté `users.pacte_complementaries` alineat amb l'existència d'un pacte viu.
     *
     * El camp es conserva perquè domi el llegeix, però **deixa de ser la font**: passa a ser
     * un reflex de les acceptacions. Així cap administrador el pot activar per ningú sense que
     * hi hagi una acceptació al darrere.
     */
    private function sincronitzaTic(User $u): void
    {
        $viu = DB::table('complementary_pacts')
            ->where('user_id', $u->id)
            ->whereNull('revoked_at')
            ->whereDate('period_end', '>=', Carbon::today()->toDateString())
            ->exists();

        DB::table('users')->where('id', $u->id)->update(['pacte_complementaries' => $viu ? 1 : 0]);
    }
}
