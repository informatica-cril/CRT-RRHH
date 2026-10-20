<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\QuadrantDia;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\HorariDia;
use App\Services\RecalculaTrams;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quadrant (RRHH, 19-10-2026): RRHH i admin canvien l'horari de cada persona dia a dia; el
 * treballador veu el seu. Un dia canviat mana sobre la plantilla setmanal (App\Services\HorariDia).
 * Coordinació no hi té accés.
 */
class QuadrantController extends Controller
{
    private const MES = '/^\d{4}-(0[1-9]|1[0-2])$/';

    /** GET /api/v1/quadrant?mes=AAAA-MM[&department_id=] — el mes de tothom (o d'un departament). */
    public function mes(Request $request)
    {
        $data = $request->validate([
            'mes' => ['required', 'regex:' . self::MES],
            'department_id' => 'nullable|integer',
        ]);
        [$inici, $fi] = $this->limitsMes($data['mes']);

        // Els treballadors i, encara que tinguin un altre rol, qui és d'algun departament (p. ex. el
        // personal d'Administració). Amb un departament triat, tota la gent d'aquell departament.
        $persones = User::with('workSchedule:id,name,days')
            ->where('active', true)->where('role', '!=', 'service')
            ->when(! empty($data['department_id']),
                fn ($q) => $q->whereHas('departments', fn ($d) => $d->where('departments.id', $data['department_id'])),
                fn ($q) => $q->where(fn ($w) => $w->where('role', 'worker')->orWhereHas('departments')))
            ->orderBy('name')->get(['id', 'name', 'work_schedule_id']);

        $files = QuadrantDia::whereIn('user_id', $persones->pluck('id'))->whereBetween('dia', [$inici, $fi])->get()
            ->groupBy('user_id');
        $absencies = $this->diesAmbAbsencia($persones->pluck('id'), $inici, $fi);
        $dies = $this->dies($inici, $fi);

        return response()->json([
            'mes' => $data['mes'],
            'dies' => $dies,
            'festius' => Holiday::whereBetween('date', [$inici, $fi])->get()
                ->mapWithKeys(fn ($h) => [substr((string) $h->getRawOriginal('date'), 0, 10) => $h->name]),
            'plantilles' => $this->plantilles(),
            'departaments' => Department::where('active', true)->orderBy('sort')->get(['id', 'name']),
            'persones' => $persones->map(fn (User $u) => $this->fila($u, $dies, $files->get($u->id, collect()), $absencies[$u->id] ?? [])),
        ]);
    }

    /** GET /api/v1/quadrant/any?user_id=&any=AAAA — l'any d'una persona, dia a dia. */
    public function any(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'any' => 'required|integer|min:2020|max:2100',
        ]);
        $u = User::with('workSchedule:id,name,days')->findOrFail($data['user_id']);
        $inici = "{$data['any']}-01-01";
        $fi = "{$data['any']}-12-31";
        $files = QuadrantDia::where('user_id', $u->id)->whereBetween('dia', [$inici, $fi])->get();
        $absencies = $this->diesAmbAbsencia(collect([$u->id]), $inici, $fi);

        return response()->json([
            'any' => (int) $data['any'],
            'festius' => Holiday::whereBetween('date', [$inici, $fi])->get()
                ->mapWithKeys(fn ($h) => [substr((string) $h->getRawOriginal('date'), 0, 10) => $h->name]),
            'plantilles' => $this->plantilles(),
            'persona' => $this->fila($u, $this->dies($inici, $fi), $files, $absencies[$u->id] ?? []),
        ]);
    }

    /**
     * PUT /api/v1/quadrant
     * { user_ids: [...], dies: ["AAAA-MM-DD", ...], accio: plantilla|trams|lliure|esborra,
     *   work_schedule_id?, trams?: [{start, end}, ...], nota? }
     * «esborra» torna els dies a la plantilla de la fitxa. Els fitxatges pendents d'aquests dies
     * es tornen a dividir en trams amb l'horari nou.
     */
    public function desa(Request $request, RecalculaTrams $recalcula)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'integer|exists:users,id',
            'dies' => 'required|array|min:1|max:400',
            'dies.*' => 'date_format:Y-m-d',
            'accio' => 'required|in:plantilla,trams,lliure,esborra',
            'work_schedule_id' => 'required_if:accio,plantilla|nullable|integer|exists:work_schedules,id',
            'trams' => 'required_if:accio,trams|nullable|array|max:3',
            'trams.*.start' => 'required|date_format:H:i',
            'trams.*.end' => 'required|date_format:H:i',
            'nota' => 'nullable|string|max:255',
        ]);
        $userIds = array_values(array_unique($data['user_ids']));
        $dies = array_values(array_unique($data['dies']));
        if (count($userIds) * count($dies) > 10000) {
            throw ValidationException::withMessages(['dies' => 'Massa dies d\'un cop: fes-ho per parts.']);
        }

        $trams = [];
        if ($data['accio'] === 'trams') {
            $trams = $this->validaTrams($data['trams']);
        }
        $plantilla = $data['accio'] === 'plantilla' ? WorkSchedule::find($data['work_schedule_id']) : null;
        $autor = $request->user();

        DB::transaction(function () use ($data, $userIds, $dies, $trams, $plantilla, $autor) {
            if ($data['accio'] === 'esborra') {
                QuadrantDia::whereIn('user_id', $userIds)->whereIn('dia', $dies)->delete();

                return;
            }
            foreach ($userIds as $uid) {
                foreach ($dies as $dia) {
                    $t = match ($data['accio']) {
                        'plantilla' => HorariDia::dePlantilla($plantilla, $dia),
                        'trams' => $trams,
                        default => [],
                    };
                    QuadrantDia::updateOrCreate(['user_id' => $uid, 'dia' => $dia], [
                        'trams' => $t,
                        // Un dia que la plantilla triada no treballa (cap de setmana) queda lliure.
                        'lliure' => $t === [],
                        'work_schedule_id' => $plantilla?->id,
                        'nota' => $data['nota'] ?? null,
                        'created_by' => $autor->id,
                    ]);
                }
            }
        });
        HorariDia::oblida();

        $recalculats = $recalcula->perUsuaris($userIds, $autor, $dies);
        $que = match ($data['accio']) {
            'plantilla' => "plantilla «{$plantilla->name}»",
            'trams' => 'horari ' . implode(' i ', array_map(fn ($t) => "{$t['start']}-{$t['end']}", $trams)),
            'lliure' => 'dia lliure',
            'esborra' => 'tornar a la plantilla de la fitxa',
        };
        $this->audita($request, sprintf('Quadrant: %s a %d persona(es), %d dia(es) (%s – %s)',
            $que, count($userIds), count($dies), min($dies), max($dies)));

        return response()->json(['canviats' => count($userIds) * count($dies), 'trams_recalculats' => $recalculats]);
    }

    /**
     * POST /api/v1/quadrant/copia { user_ids: [...], de: AAAA-MM, a: AAAA-MM }
     * El mes «a» queda com el «de», dia de la setmana per dia de la setmana (el primer dilluns
     * com el primer dilluns…). Els dies que al mes d'origen seguien la plantilla també la segueixen.
     */
    public function copia(Request $request, RecalculaTrams $recalcula)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1|max:500',
            'user_ids.*' => 'integer|exists:users,id',
            'de' => ['required', 'regex:' . self::MES],
            'a' => ['required', 'regex:' . self::MES, 'different:de'],
        ]);
        [$deIni, $deFi] = $this->limitsMes($data['de']);
        [$aIni, $aFi] = $this->limitsMes($data['a']);
        $userIds = array_values(array_unique($data['user_ids']));
        $autor = $request->user();
        $origen = QuadrantDia::whereIn('user_id', $userIds)->whereBetween('dia', [$deIni, $deFi])->get()
            ->groupBy('user_id');
        $diesDesti = $this->dies($aIni, $aFi);

        DB::transaction(function () use ($userIds, $origen, $diesDesti, $aIni, $aFi, $deIni, $autor) {
            QuadrantDia::whereIn('user_id', $userIds)->whereBetween('dia', [$aIni, $aFi])->delete();
            foreach ($userIds as $uid) {
                $files = $origen->get($uid, collect())->keyBy(fn ($q) => substr($q->getRawOriginal('dia'), 0, 10));
                foreach ($diesDesti as $dia) {
                    $font = $files->get($this->diaEquivalent($dia, $deIni));
                    if (! $font) continue;
                    QuadrantDia::create([
                        'user_id' => $uid, 'dia' => $dia, 'trams' => $font->trams, 'lliure' => $font->lliure,
                        'work_schedule_id' => $font->work_schedule_id, 'nota' => $font->nota, 'created_by' => $autor->id,
                    ]);
                }
            }
        });
        HorariDia::oblida();

        $recalculats = $recalcula->perUsuaris($userIds, $autor, $diesDesti);
        $this->audita($request, sprintf('Quadrant: %s copiat a %s per a %d persona(es)', $data['de'], $data['a'], count($userIds)));

        return response()->json(['persones' => count($userIds), 'trams_recalculats' => $recalculats]);
    }

    /** GET /api/v1/el-meu-quadrant?mes=AAAA-MM — el treballador veu el seu horari dia a dia. */
    public function elMeu(Request $request)
    {
        $data = $request->validate(['mes' => ['required', 'regex:' . self::MES]]);
        [$inici, $fi] = $this->limitsMes($data['mes']);
        $u = $request->user()->load('workSchedule:id,name,days');
        $files = QuadrantDia::where('user_id', $u->id)->whereBetween('dia', [$inici, $fi])->get();

        $fila = $this->fila($u, $this->dies($inici, $fi), $files, $this->diesAmbAbsencia(collect([$u->id]), $inici, $fi)[$u->id] ?? []);

        return response()->json([
            'mes' => $data['mes'],
            'plantilla' => $fila['plantilla'],
            'minuts' => $fila['minuts'],
            'dies' => $fila['dies'],
            'festius' => Holiday::whereBetween('date', [$inici, $fi])->get()
                ->mapWithKeys(fn ($h) => [substr((string) $h->getRawOriginal('date'), 0, 10) => $h->name]),
        ]);
    }

    /** Una persona amb l'horari de cada dia: del quadrant o de la plantilla. */
    private function fila(User $u, array $dies, Collection $files, array $absencies): array
    {
        $perDia = $files->keyBy(fn ($q) => substr($q->getRawOriginal('dia'), 0, 10));
        $minuts = 0;
        $out = [];
        foreach ($dies as $dia) {
            $q = $perDia->get($dia);
            $trams = $q ? ($q->lliure ? [] : HorariDia::neteja($q->trams)) : HorariDia::dePlantilla($u->workSchedule, $dia);
            $minuts += HorariDia::minuts($trams);
            $out[$dia] = [
                'trams' => $trams,
                'origen' => $q ? 'quadrant' : 'plantilla',
                'work_schedule_id' => $q ? $q->work_schedule_id : $u->work_schedule_id,
                'nota' => $q?->nota,
                'absencia' => in_array($dia, $absencies, true),
            ];
        }

        return [
            'id' => $u->id,
            'name' => $u->name,
            'plantilla' => $u->workSchedule ? ['id' => $u->workSchedule->id, 'name' => $u->workSchedule->name] : null,
            'minuts' => $minuts,
            'dies' => $out,
        ];
    }

    /** Trams a mida: com a molt 3, de més curt a més llarg i sense trepitjar-se. */
    private function validaTrams(array $trams): array
    {
        $nets = HorariDia::neteja($trams);
        if (count($nets) !== count($trams)) {
            throw ValidationException::withMessages(['trams' => 'Cada tram ha d\'acabar més tard del que comença.']);
        }
        for ($i = 1; $i < count($nets); $i++) {
            if ($nets[$i]['start'] < $nets[$i - 1]['end']) {
                throw ValidationException::withMessages(['trams' => 'Els trams no es poden trepitjar.']);
            }
        }

        return $nets;
    }

    /** El dia del mes d'origen que fa el mateix paper: mateix dia de la setmana i mateixa setmana. */
    private function diaEquivalent(string $dia, string $iniciOrigen): string
    {
        $d = Carbon::parse($dia);
        $n = intdiv($d->day - 1, 7);                 // 0 = primer dilluns (o el dia que sigui) del mes
        $primer = Carbon::parse($iniciOrigen);
        while ($primer->dayOfWeek !== $d->dayOfWeek) $primer->addDay();
        $font = $primer->copy()->addWeeks($n);
        // El mes d'origen no té un cinquè dilluns: es fa servir el quart.
        if ($font->month !== $primer->month) $font->subWeek();

        return $font->toDateString();
    }

    /** @return array<int, string[]> dies amb absència aprovada per persona */
    private function diesAmbAbsencia(Collection $userIds, string $inici, string $fi): array
    {
        $out = [];
        Absence::whereIn('user_id', $userIds)->where('approved', true)
            ->where('start_date', '<=', $fi)->where('end_date', '>=', $inici)
            ->get(['user_id', 'start_date', 'end_date'])
            ->each(function ($a) use (&$out, $inici, $fi) {
                $des = max($a->start_date->toDateString(), $inici);
                $fins = min($a->end_date->toDateString(), $fi);
                foreach ($this->dies($des, $fins) as $d) $out[$a->user_id][] = $d;
            });

        return $out;
    }

    /** Les plantilles amb les hores que suma de veritat el seu horari (per al color del quadrant). */
    private function plantilles(): Collection
    {
        return WorkSchedule::orderBy('name')->get(['id', 'name', 'days', 'total_hours_weekly'])
            ->map(fn ($s) => $s->toArray() + ['minuts_reals' => WorkSchedule::minutsSetmanals($s->days)]);
    }

    private function limitsMes(string $mes): array
    {
        $inici = Carbon::parse("{$mes}-01");

        return [$inici->toDateString(), $inici->copy()->endOfMonth()->toDateString()];
    }

    private function dies(string $inici, string $fi): array
    {
        return collect(CarbonPeriod::create($inici, $fi))->map(fn ($d) => $d->toDateString())->all();
    }

    private function audita(Request $request, string $descripcio): void
    {
        try {
            AuditLog::create([
                'user_id' => $request->user()->id, 'action' => 'QUADRANT_CANVI', 'entity_type' => 'quadrant',
                'description' => $descripcio,
                'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
