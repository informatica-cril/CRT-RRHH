<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmbulatoryCenter;
use App\Models\WorkLocation;
use App\Models\Zone;
use App\Services\DisponibilitatService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Quadre de disponibilitat setmanal (petició de Direcció, 31-07-2026).
 *
 * Només hi arriben `admin` i `hr`: la taula ensenya, de tota la plantilla alhora, qui està
 * absent i per quin motiu —«Baixa mèdica», «Matrimoni»…—, i això és informació de salut i de
 * vida privada. Un coordinador necessita saber qui té l'agenda lliure, no per què no hi és.
 */
class DisponibilitatController extends Controller
{
    public function __construct(private DisponibilitatService $servei) {}

    public function index(Request $request): JsonResponse
    {
        $dades = $request->validate([
            'setmana'        => ['nullable', 'date_format:Y-m-d'],
            'lot_id'         => ['nullable', 'integer', 'exists:lots,id'],
            'departament_id' => ['nullable', 'integer', 'exists:departments,id'],
            'zona_id'     => ['nullable', 'integer', 'exists:zones,id'],
            'ubicacio_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'centre_id'   => ['nullable', 'integer', 'exists:ambulatory_centers,id'],
            'rol'         => ['nullable', 'in:admin,coordinator,worker,hr'],
        ]);

        $setmana = $dades['setmana'] ?? CarbonImmutable::now()->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        return response()->json($this->servei->setmana($setmana, [
            'lot_id'         => $dades['lot_id']         ?? null,
            'departament_id' => $dades['departament_id'] ?? null,
            'zona_id'     => $dades['zona_id']     ?? null,
            'ubicacio_id' => $dades['ubicacio_id'] ?? null,
            'centre_id'   => $dades['centre_id']   ?? null,
            'rol'         => $dades['rol']         ?? null,
        ]));
    }

    /**
     * La mateixa disponibilitat, però per a la PROGRAMACIÓ de domi (compte de servei).
     *
     * ── PER QUÈ AIXÒ EXISTEIX ───────────────────────────────────────────────────────────
     * domi programa visites a domicili de tota la gent que hi treballa —laborals i autònoms—
     * i decideix qui està absent mirant una taula SEVA (`crt_disp_absencia`). Les absències
     * aprovades dels laborals, en canvi, viuen aquí. O sigui que avui domi pot programar una
     * visita a algú que RRHH té de vacances aprovades, i ningú se n'assabenta fins que el
     * pacient es queda esperant.
     *
     * Aquest endpoint tanca aquest forat pel costat de RRHH: dona, per identificador de RRHH,
     * els dies en què cada persona NO està disponible i per quin motiu genèric.
     *
     * ⚠️ NO S'HI ENVIA EL MOTIU CONCRET. domi rep «absent», no «Baixa mèdica»: per programar
     * una visita cal saber que la persona no hi és, no per què. El motiu és dada de salut i
     * es queda aquí. Aquest és el mateix criteri que ja s'aplica a la resta de /domi/*.
     */
    public function perDomi(Request $request): JsonResponse
    {
        $dades = $request->validate([
            'desde' => ['required', 'date_format:Y-m-d'],
            'fins'  => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'ids'   => ['nullable', 'string'],   // llista d'IDs de RRHH separats per comes
        ]);

        $desde = CarbonImmutable::parse($dades['desde']);
        $fins  = CarbonImmutable::parse($dades['fins']);

        /* Un sostre: sense això, una petició de deu anys tomba el servidor. */
        if ($desde->diffInDays($fins) > 92) {
            return response()->json(['error' => 'Com a màxim 92 dies per petició.'], 422);
        }

        $ids = array_filter(array_map('intval', explode(',', (string) ($dades['ids'] ?? ''))));

        return response()->json($this->servei->perDomi($desde->toDateString(), $fins->toDateString(), $ids));
    }

    /**
     * Els valors dels desplegables.
     *
     * S'hi diu quants treballadors té assignats cada opció perquè filtrar per una zona buida
     * i veure el quadre en blanc no s'interpreti com «no hi ha ningú disponible» quan el que
     * passa és que allà encara no hi ha ningú assignat. Mesurat el 31-07-2026: de 80
     * treballadors, 38 tenen zona i 31 ubicació.
     */
    public function filtres(): JsonResponse
    {
        return response()->json([
            /* Lot de clàusula del contracte: territori ample (B1 Barcelona, B9 Vallès). */
            'lots' => \App\Models\Lot::where('active', true)->orderBy('sort')
                ->withCount('users')->get(['id', 'code', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'code' => $l->code, 'name' => $l->name,
                                  'nom' => $l->code . ' · ' . $l->name, 'treballadors' => $l->users_count]),

            /* Departament: servei × modalitat. */
            'departaments' => \App\Models\Department::where('active', true)->orderBy('sort')
                ->withCount('users')->get(['id', 'code', 'name'])
                ->map(fn ($d) => ['id' => $d->id, 'code' => $d->code, 'name' => $d->name,
                                  'nom' => $d->name, 'treballadors' => $d->users_count]),

            'zones' => Zone::where('active', true)->orderBy('name')
                ->withCount('users')->get(['id', 'name', 'type'])
                ->map(fn ($z) => ['id' => $z->id, 'nom' => $z->name, 'tipus' => $z->type, 'treballadors' => $z->users_count]),

            'ubicacions' => WorkLocation::where('active', true)->orderBy('name')
                ->withCount('users')->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'nom' => $l->name, 'treballadors' => $l->users_count]),

            'centres' => AmbulatoryCenter::where('active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'nom' => $c->name]),

            'rols' => [
                ['id' => 'worker',      'nom' => 'Treballador'],
                ['id' => 'coordinator', 'nom' => 'Coordinació'],
                ['id' => 'hr',          'nom' => 'Recursos Humans'],
                ['id' => 'admin',       'nom' => 'Administració'],
            ],
        ]);
    }
}
