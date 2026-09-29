<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IaLocal;
use App\Support\Millores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MilloresController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('millores as m')
            ->leftJoin('users as u', 'u.id', '=', 'm.decidit_per')
            ->orderByRaw("FIELD(m.estat,'nova','acceptada','preparada','feta','preparacio_fallida','descartada')")
            ->orderByRaw("FIELD(m.impacte,'alt','mitja','baix')")
            ->orderByDesc('m.id')
            ->limit(120)
            ->get(['m.*', 'u.name as decidit_per_nom']);

        return response()->json([
            'millores' => $q,
            'senyals' => Millores::context(),
            'ia_disponible' => (new IaLocal())->disponible(),
        ]);
    }

    public function generate(Request $request)
    {
        $r = Millores::genera();
        if (! $r['ok']) {
            return response()->json(['message' => $r['motiu']], 503);
        }

        return response()->json($r);
    }

    public function decide(Request $request, int $id)
    {
        $data = $request->validate([
            'estat' => 'required|in:en_cua,descartada,en_curs,feta',
            'nota' => 'nullable|string|max:500',
            'prioritat' => 'nullable|integer|min:1|max:5',
            'hores_admin' => 'nullable|numeric|min:0|max:999',
        ]);
        $m = DB::table('millores')->find($id);
        abort_if(! $m, 404);

        if ($data['estat'] === 'en_cua' && empty($data['prioritat'])) {
            return response()->json(['message' => 'Per posar-la a la cua cal donar-li prioritat: '
                . 'informatica treballa per ordre i una cua sense ordre no serveix.'], 422);
        }

        $canvis = [
            'estat' => $data['estat'],
            'nota' => $data['nota'] ?? $m->nota,
            'decidit_per' => $request->user()->id,
            'decidit_ts' => now(),
            'updated_at' => now(),
        ];
        if (array_key_exists('prioritat', $data)) {
            $canvis['prioritat'] = $data['prioritat'];
        }
        if (array_key_exists('hores_admin', $data)) {
            $canvis['hores_admin'] = $data['hores_admin'];
        }
        if ($data['estat'] === 'en_cua' && ! $m->encuada_ts) {
            $canvis['encuada_ts'] = now();
        }
        if ($data['estat'] === 'en_curs' && ! $m->iniciada_ts) {
            $canvis['iniciada_ts'] = now();
        }
        if ($data['estat'] === 'feta') {
            $canvis['feta_ts'] = now();
        }

        DB::table('millores')->where('id', $id)->update($canvis);

        return response()->json(['ok' => true]);
    }

    public function cua(Request $request)
    {
        $files = DB::table('millores')
            ->whereIn('estat', ['en_cua', 'en_curs'])
            ->orderByRaw('FIELD(estat,"en_curs","en_cua")')
            ->orderBy('prioritat')
            ->orderByDesc('id')
            ->get();

        $hores = $files->sum(fn ($m) => (float) ($m->hores_admin ?? $m->hores_ia ?? 0));

        return response()->json([
            'cua' => $files,
            'total_hores' => round($hores, 1),
            'per_prioritat' => $files->groupBy('prioritat')->map(fn ($g) => [
                'quantitat' => $g->count(),
                'hores' => round($g->sum(fn ($m) => (float) ($m->hores_admin ?? $m->hores_ia ?? 0)), 1),
            ]),
        ]);
    }
}
