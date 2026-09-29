<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PortalAppGrant;
use App\Models\PortalSsoToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Portal d'accés únic (CRT Accés).
 *
 * Un sol login (el de RRHH) i un llançador amb les apps a què Direcció ha
 * donat accés. El salt a cada app es fa amb un BITLLET d'un sol ús i vida
 * curta: el portal el crea, la app destí el valida servidor-a-servidor amb
 * el compte de servei, i la sessió s'obre a la app com sempre. RRHH és la
 * font de veritat de la identitat; les apps no veuen mai la contrasenya.
 *
 * Revocació immediata: sense grant no hi ha targeta ni bitllet. El login
 * directe de cada app segueix viu com a via d'emergència (si RRHH cau, la
 * gent encara pot entrar a domi).
 */
class PortalController extends Controller
{
    /** Apps que existeixen de veritat: el catàleg viu a config/portal.php. */
    private function catalogApps(): array
    {
        return config('portal.apps', []);
    }

    /**
     * GET /api/v1/portal/apps
     * Targetes del llançador per a l'usuari autenticat: només les que té
     * concedides i existeixen al catàleg. No exposa URLs d'entrada: la URL
     * només es dona en el moment del salt, amb bitllet inclòs.
     */
    public function apps(Request $request)
    {
        $cataleg = $this->catalogApps();
        $meves = PortalAppGrant::where('user_id', $request->user()->id)
            ->pluck('app')
            ->filter(fn ($app) => isset($cataleg[$app]))
            ->values();

        return response()->json([
            'apps' => $meves->map(fn ($app) => [
                'app'        => $app,
                'nom'        => $cataleg[$app]['nom'],
                'descripcio' => $cataleg[$app]['descripcio'] ?? '',
            ]),
        ]);
    }

    /**
     * POST /api/v1/portal/launch  {app}
     * Crea el bitllet d'un sol ús i torna la URL d'aterratge de la app.
     * El token viatja UNA vegada (query de la redirecció) i a la base només
     * en queda el hash; caduca en segons (config portal.token_ttl).
     */
    public function launch(Request $request)
    {
        $data = $request->validate(['app' => 'required|string|max:30']);
        $app = $data['app'];
        $cataleg = $this->catalogApps();
        abort_unless(isset($cataleg[$app]), 404, 'App desconeguda.');

        $te = PortalAppGrant::where('user_id', $request->user()->id)
            ->where('app', $app)->exists();
        abort_unless($te, 403, 'No tens accés concedit a aquesta app.');

        $token = Str::random(64);
        PortalSsoToken::create([
            'user_id'    => $request->user()->id,
            'app'        => $app,
            'token_hash' => hash('sha256', $token),
            'expires_at' => Carbon::now()->addSeconds((int) config('portal.token_ttl', 60)),
            'created_ip' => $request->ip(),
        ]);

        /* Neteja oportunista: fora bitllets morts de fa més d'un dia. */
        PortalSsoToken::where('expires_at', '<', Carbon::now()->subDay())->delete();

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'portal.launch',
            'entity_type' => 'portal_app',
            'entity_id'   => null,
            'description' => "Salt del portal a l'app {$app}",
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'url' => $cataleg[$app]['url_entrada'] . '?token=' . urlencode($token),
        ]);
    }

    /**
     * POST /api/v1/domi/sso/valida  {token, app}
     * NOMÉS compte de servei (la ruta viu sota /domi/* per l'allowlist de
     * RestrictServiceRole). Valida el bitllet: existent, de la app que el
     * reclama, no usat i no caducat. El marca usat ABANS de respondre
     * (un sol ús real, també amb peticions concurrents) i torna la identitat
     * mínima perquè la app resolgui el seu compte local.
     */
    public function valida(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:120',
            'app'   => 'required|string|max:30',
        ]);

        $consumits = PortalSsoToken::where('token_hash', hash('sha256', $data['token']))
            ->where('app', $data['app'])
            ->whereNull('used_at')
            ->where('expires_at', '>=', Carbon::now())
            ->update(['used_at' => Carbon::now()]);

        if ($consumits !== 1) {
            return response()->json(['ok' => false, 'motiu' => 'token_invalid'], 401);
        }

        $bitllet = PortalSsoToken::where('token_hash', hash('sha256', $data['token']))->first();
        $usuari = User::find($bitllet->user_id);
        if (! $usuari) {
            return response()->json(['ok' => false, 'motiu' => 'usuari_inexistent'], 404);
        }

        AuditLog::create([
            'user_id'     => $usuari->id,
            'action'      => 'portal.sso_validat',
            'entity_type' => 'portal_app',
            'entity_id'   => null,
            'description' => "Bitllet SSO validat per l'app {$data['app']}",
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json([
            'ok' => true,
            'usuari' => [
                'rrhh_id'       => $usuari->id,
                'nom'           => $usuari->name,
                'email'         => $usuari->email,
                /* El DNI ja no hi va: domi conciliava per DNI quan no trobava el compte
                   i, amb DNIs compartits entre comptes, això obria la sessió equivocada.
                   Ara concilia per clau de compte i el DNI no li cal per res. */
                'domi_username' => $usuari->domi_username,
            ],
        ]);
    }

    /**
     * GET /api/v1/portal/grants?user_id=N
     * Gestió (admin/hr): grants de tothom o d'un treballador concret.
     */
    public function grants(Request $request)
    {
        $q = PortalAppGrant::with('user:id,name,email,role');
        if ($request->filled('user_id')) {
            $q->where('user_id', (int) $request->query('user_id'));
        }

        return response()->json([
            'cataleg' => collect($this->catalogApps())->map(fn ($a, $k) => [
                'app' => $k, 'nom' => $a['nom'], 'descripcio' => $a['descripcio'] ?? '',
            ])->values(),
            'grants' => $q->orderBy('user_id')->get(),
        ]);
    }

    /**
     * POST /api/v1/portal/grants  {user_id, app}
     */
    public function storeGrant(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'app'     => 'required|string|max:30',
        ]);
        abort_unless(isset($this->catalogApps()[$data['app']]), 404, 'App desconeguda.');

        $grant = PortalAppGrant::firstOrCreate(
            ['user_id' => $data['user_id'], 'app' => $data['app']],
            ['granted_by' => $request->user()->name],
        );

        AuditLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'portal.grant',
            'entity_type' => 'portal_app_grant',
            'entity_id'   => $grant->id,
            'description' => "Accés a {$data['app']} concedit a user_id {$data['user_id']}",
            'ip_address'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['ok' => true, 'grant' => $grant], 201);
    }

    /**
     * DELETE /api/v1/portal/grants  {user_id, app}
     * Revocació immediata: el llançador deixa de mostrar la targeta i cap
     * bitllet nou no es pot crear. Les sessions ja obertes cauen soles per
     * inactivitat, com sempre.
     */
    public function destroyGrant(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer',
            'app'     => 'required|string|max:30',
        ]);

        $n = PortalAppGrant::where('user_id', $data['user_id'])
            ->where('app', $data['app'])->delete();

        if ($n) {
            AuditLog::create([
                'user_id'     => $request->user()->id,
                'action'      => 'portal.revoca',
                'entity_type' => 'portal_app_grant',
                'entity_id'   => null,
                'description' => "Accés a {$data['app']} revocat a user_id {$data['user_id']}",
                'ip_address'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return response()->json(['ok' => true, 'eliminats' => $n]);
    }
}
