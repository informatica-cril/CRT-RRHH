<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use Illuminate\Http\Request;

/**
 * Calendari laboral: els festius es configuren aquí cada any (abans eren una
 * llista fixa de 2026 al codi). Són la base de la bossa anual d'hores i es
 * publiquen cap a la Domiciliària perquè el planificador no hi programi.
 */
class HolidayController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        return response()->json(
            Holiday::where('year', $year)->orderBy('date')
                ->get(['id', 'date', 'name', 'type'])
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d|unique:holidays,date',
            'name' => 'required|string|max:120',
            'type' => 'nullable|in:national,regional,local,empresa',
        ]);

        $h = Holiday::create([
            'date' => $data['date'],
            'name' => $data['name'],
            'type' => $data['type'] ?? 'empresa',
            'year' => (int) substr($data['date'], 0, 4),
        ]);

        return response()->json($h, 201);
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return response()->json(['ok' => true]);
    }
}
