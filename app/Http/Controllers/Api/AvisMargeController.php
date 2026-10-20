<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AvisMarge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AvisMargeController extends Controller
{
    public function index()
    {
        return response() ->json(AvisMarge::llista());
    }

    /** GET /api/v1/el-meu-avis-marge — el treballador veu si porta 3 fitxatges seguits fora d'horari. */
    public function meu(Request $request)
    {
        return response()->json(['avis' => AvisMarge::llista($request->user()->id)[0] ?? null]);
    }

    public function descarta(Request $request, User $user)
    {
        $data = $request->validate(['inici' => 'required|date_format:Y-m-d' ]);

        DB::table('avisos_marge_descartats')->updateOrInsert(
            ['user_id' => $user->id, 'inici_ratxa' => $data['inici']],
            ['descartat_per' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()],
        );

        return response()->json(['ok' => true]);
    }
}