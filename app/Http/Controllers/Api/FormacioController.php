<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * FormacioController — integració READ-ONLY amb la formació obligatòria de domi.
 *
 * Repos separats: RRHH consumeix per API servei-a-servei (token) l'estat d'acreditació de la
 * formació de plataforma i els documents (diploma/rebut). El "fer la formació" (manual+test+firma)
 * viu a domi; aquí només es VEU i es descarrega. Les firmes ja entren al segell diari compartit
 * del llibre d'integritat (api/firmes_dia.php de domi).
 *
 * Config: config('integrity.formacio') → estat_url, doc_url, token (derivats de DOMI_FIRMES_*).
 */
class FormacioController extends Controller
{
    /** Estat d'acreditació per empleat (proxy a domi). */
    public function acreditacions(Request $request)
    {
        $cfg = config('integrity.formacio');
        if (empty($cfg['estat_url']) || empty($cfg['token'])) {
            return response()->json(['ok' => false, 'error' => 'La formació de domi no està configurada (DOMI_FIRMES_URL/TOKEN).'], 500);
        }
        try {
            $resp = Http::withToken($cfg['token'])->timeout(12)->get($cfg['estat_url'], array_filter([
                'campanya' => $request->query('campanya'),
            ]));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'domi no accessible'], 424);
        }
        if (! $resp->ok()) {
            return response()->json(['ok' => false, 'error' => 'error de domi', 'status' => $resp->status()], 424);
        }
        return response()->json($resp->json());
    }

    /** Descàrrega del diploma/rebut (proxy; el token de servei queda al servidor). */
    public function doc(Request $request, string $tipus, string $usuari, string $rol)
    {
        $cfg = config('integrity.formacio');
        if (empty($cfg['doc_url']) || empty($cfg['token'])) {
            abort(500, 'La formació de domi no està configurada.');
        }
        try {
            $resp = Http::withToken($cfg['token'])->timeout(20)->get($cfg['doc_url'], [
                'tipus'    => $tipus === 'rebut' ? 'rebut' : 'diploma',
                'usuari'   => $usuari,
                'rol'      => $rol,
                'campanya' => (int) $request->query('campanya', 0),
            ]);
        } catch (\Throwable $e) {
            abort(502, 'domi no accessible');
        }
        if (! $resp->ok()) {
            abort(404, 'Document no disponible');
        }
        return response($resp->body(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="formacio-' . ($tipus === 'rebut' ? 'rebut' : 'diploma') . '.pdf"');
    }
}
