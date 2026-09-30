<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * ExpedientController — integració READ-ONLY amb l'API F1 de domi (api/rrhh_expedient.php).
 *
 * Repos separats: RRHH consumeix les mètriques agregades de l'expedient del treballador des de domi
 * per API servei-a-servei (token). NO hi ha lògica disciplinària aquí: només proxy autenticat i
 * control de rol. La qualificació i la sanció són sempre humanes (RGPD 22), al motor de domi.
 *
 * Config a .env de RRHH:
 *   DOMI_EXPEDIENT_URL=http://127.0.0.1:8080/api/rrhh_expedient.php
 *   DOMI_EXPEDIENT_TOKEN=<el mateix CRT_RRHH_TOKEN de domi/includes/wa_secret.php>
 */
class ExpedientController extends Controller
{
    public function show(Request $request, string $professional)
    {
        $url   = env('DOMI_EXPEDIENT_URL', 'http://127.0.0.1:8080/api/rrhh_expedient.php');
        $token = (string) env('DOMI_EXPEDIENT_TOKEN', '');
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'DOMI_EXPEDIENT_TOKEN no configurat a .env'], 500);
        }

        try {
            $resp = Http::withToken($token)->timeout(10)->get($url, array_filter([
                'professional' => $professional,
                'desde'        => $request->query('desde'),
                'fins'         => $request->query('fins'),
            ]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'domi no accessible'], 424);
        }

        if (! $resp->ok()) {
            return response()->json(['ok' => false, 'error' => 'error de domi', 'status' => $resp->status()], 424);
        }

        // Es retorna tal com ve de domi (ja porta objectiu/valoratiu + font per bloc).
        return response()->json($resp->json());
    }

    /** Detall PER-FET de la recollida (F1.bis de domi): la matèria primera per incorporar elements. */
    public function detall(Request $request, string $professional)
    {
        $url   = env('DOMI_EXPEDIENT_DETALL_URL', 'http://127.0.0.1:8080/api/rrhh_expedient_detall.php');
        $token = (string) env('DOMI_EXPEDIENT_TOKEN', '');
        if ($token === '') {
            return response()->json(['ok' => false, 'error' => 'DOMI_EXPEDIENT_TOKEN no configurat a .env'], 500);
        }
        try {
            $resp = Http::withToken($token)->timeout(10)->get($url, array_filter([
                'professional' => $professional,
                'desde'        => $request->query('desde'),
                'fins'         => $request->query('fins'),
            ]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'domi no accessible'], 424);
        }
        if (! $resp->ok()) {
            return response()->json(['ok' => false, 'error' => 'error de domi', 'status' => $resp->status()], 424);
        }

        return response()->json($resp->json());
    }
}
