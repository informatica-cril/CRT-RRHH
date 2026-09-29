<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServeiParametresController extends Controller
{
    private const SERVEIS = ['domi', 'logo_domi', 'ambu', 'administracio'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'servei' => 'required|string|in:' . implode(',', self::SERVEIS),
            'parametres' => 'required|array|max:50',
        ]);

        $files = [];
        foreach ($data['parametres'] as $clau => $valor) {
            $files[] = [
                'servei' => $data['servei'],
                'clau' => substr((string) $clau, 0, 60),
                'valor' => json_encode($valor, JSON_UNESCAPED_UNICODE),
                'rebut_ts' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('servei_parametres')->upsert($files, ['servei', 'clau'], ['valor', 'rebut_ts', 'updated_at']);

        return response()->json(['ok' => true, 'servei' => $data['servei'], 'desats' => count($files)]);
    }

    public function senyals(Request $request)
    {
        $data = $request->validate([
            'servei' => 'required|string|in:' . implode(',', self::SERVEIS),
            'senyals' => 'required|array|max:80',
        ]);

        $files = [];
        foreach ($data['senyals'] as $clau => $valor) {
            $files[] = [
                'servei' => $data['servei'],
                'clau' => substr((string) $clau, 0, 80),
                'valor' => json_encode($valor, JSON_UNESCAPED_UNICODE),
                'rebut_ts' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('servei_senyals')->upsert($files, ['servei', 'clau'], ['valor', 'rebut_ts', 'updated_at']);

        return response()->json(['ok' => true, 'servei' => $data['servei'], 'desats' => count($files)]);
    }
}
