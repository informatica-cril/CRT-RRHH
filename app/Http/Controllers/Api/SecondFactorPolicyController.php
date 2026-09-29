<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SecondFactorSetting;
use App\Models\User;
use App\Support\SecondFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La política de segon factor, en mans de Direcció.
 *
 * Direcció (01-08-2026): «vull decidir-ho jo». Fins ara vivia al .env i el comandament el
 * tenia informàtica.
 *
 * ── PER QUÈ AIXÒ TORNA UN «IMPACTE» I NO NOMÉS L'ESTAT ──────────────────────────────
 * Activar l'obligatorietat amb la plantilla com està avui deixaria gent FORA de
 * l'aplicació, i no d'una manera evident: el mètode per defecte de tothom és «tauleta
 * corporativa», i per a qui treballa des d'un ordinador d'oficina això vol dir que el seu
 * segon factor és un aparell que no porta. Mesurat l'01-08-2026: 80 usuaris amb mètode
 * «dispositiu» i CAP amb TOTP confirmat —no perquè s'hagi decidit així, sinó perquè fins
 * avui no hi havia cap pantalla per canviar-ho—.
 *
 * Una pantalla que només digués «obligatori: sí/no» convidaria a prémer el botó i
 * descobrir-ho l'endemà amb la gent trucant. Per això aquí es diu, ABANS de decidir, quantes
 * persones quedarien bloquejades i quines són.
 */
class SecondFactorPolicyController extends Controller
{
    public function show(): JsonResponse
    {
        $s = SecondFactorSetting::actual();

        return response()->json([
            'mode'       => $s?->mode ?? 'off',
            'roles'      => $s?->roles ?? [],
            'changed_by' => $s?->changed_by,
            'changed_at' => $s?->changed_at,
            /* Si algú ha posat el kill-switch al servidor, la pantalla ho ha de dir: si no,
               Direcció canviaria el mode i no passaria res, sense entendre per què. */
            'apagat_al_servidor' => SecondFactor::apagat(),
            'impacte'    => $this->impacte(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $dades = $request->validate([
            'mode'    => ['required', 'in:off,roles,all'],
            'roles'   => ['array'],
            'roles.*' => ['in:admin,coordinator,hr,worker'],
        ]);

        $s = SecondFactorSetting::actual();
        if (! $s) {
            return response()->json([
                'ok' => false,
                'error' => 'Falta la taula de política (second_factor_settings). '
                         . 'Cal aplicar les migracions pendents abans de canviar això.',
            ], 500);
        }

        $s->update([
            'mode'  => $dades['mode'],
            'roles' => $dades['mode'] === SecondFactorSetting::ROLES ? array_values($dades['roles'] ?? []) : [],
            /* Qui afluixa o endureix el segon factor de tota una plantilla ha de constar:
               és mesura de l'ENS de categoria ALTA. Sense això, «algú ho va desactivar» no
               té resposta possible. */
            'changed_by' => $request->user()->email,
            'changed_at' => now(),
        ]);

        return response()->json(['ok' => true, 'mode' => $s->mode, 'roles' => $s->roles,
            'impacte' => $this->impacte()]);
    }

    /**
     * Qui es quedaria fora amb cada mode.
     *
     * EN RISC = té el mètode «tauleta corporativa» però NO és personal domiciliari, o sigui
     * que el seu segon factor és un aparell que probablement no porta. No ho podem saber del
     * cert —RRHH no registra qui té tauleta a la mà—, i per això es diu «en risc» i es donen
     * els noms, no un semàfor verd. La comprovació de veritat la fa una persona mirant la
     * llista.
     */
    private function impacte(): array
    {
        $actius = User::where('active', true)->where('role', '!=', 'service')->get();

        $enRisc = $actius->filter(function (User $u) {
            if (SecondFactor::metode($u) !== SecondFactor::DISPOSITIU) { return false; }
            return ! str_starts_with((string) $u->work_type, 'DOMICILIARIA');
        });

        $perRol = [];
        foreach (['admin', 'coordinator', 'hr', 'worker'] as $rol) {
            $delRol = $actius->where('role', $rol);
            $perRol[$rol] = [
                'total'        => $delRol->count(),
                'amb_totp'     => $delRol->filter(fn ($u) => SecondFactor::metode($u) === SecondFactor::TOTP
                                                          && (bool) $u->totp_confirmed)->count(),
                'en_risc'      => $enRisc->where('role', $rol)->count(),
            ];
        }

        return [
            'plantilla_activa' => $actius->count(),
            'amb_totp_confirmat' => $actius->filter(fn ($u) => SecondFactor::metode($u) === SecondFactor::TOTP
                                                            && (bool) $u->totp_confirmed)->count(),
            'en_risc_total' => $enRisc->count(),
            'per_rol' => $perRol,
            /* Amb noms: una xifra no es pot resoldre, una llista sí. Es talla a 25 perquè la
               pantalla no es faci inservible; si n'hi ha més, es diu. */
            'en_risc_noms' => $enRisc->take(25)->map(fn ($u) => [
                'id' => $u->id, 'nom' => $u->name, 'rol' => $u->role, 'ambit' => $u->work_type,
            ])->values(),
            'en_risc_mostrats' => min(25, $enRisc->count()),
        ];
    }
}
