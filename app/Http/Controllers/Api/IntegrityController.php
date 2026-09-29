<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySeal;
use App\Services\IntegrityLedger;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Consulta i verificació dels segells d'integritat (només admin — matèria de seguretat/ENS).
 * No permet MODIFICAR res: el llibre és append-only i el segellat el fa el cron.
 */
class IntegrityController extends Controller
{
    public function index()
    {
        return DailySeal::orderByDesc('seal_date')->limit(90)->get([
            'id', 'seal_date', 'events_count', 'root_hash', 'prev_seal_hash',
            'tsa_provider', 'tsa_status', 'tsa_time', 'sealed_at',
        ]);
    }

    public function show(DailySeal $seal)
    {
        $perSource = $seal->events()
            ->selectRaw('source, COUNT(*) n')->groupBy('source')->pluck('n', 'source');

        return [
            'seal'       => $seal,
            'per_source' => $perSource,
            'events'     => $seal->events()->orderBy('id')
                ->limit(500)->get(['id', 'occurred_at', 'source', 'source_id', 'hash']),
        ];
    }

    public function verify(DailySeal $seal, IntegrityLedger $ledger)
    {
        return $ledger->verify(Carbon::parse($seal->seal_date));
    }

    /** Segellat manual (p. ex. per provar o refer un dia). Un sol crèdit si demana TSA. */
    public function sealNow(Request $request, IntegrityLedger $ledger)
    {
        $day = $request->input('date') ? Carbon::parse($request->input('date')) : now();
        $withTsa = ! $request->boolean('no_tsa');

        return $ledger->sealDay($day, $withTsa);
    }
}
