<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Client per detectar la disponibilitat de la app domi.
 * RRHH NO depèn de domi: si no respon, es treballa en Mode B (validació per zona).
 */
class DomiClient
{
    /** Comprova si domi està online (health-check curt, no bloquejant). */
    public static function isOnline(): bool
    {
        $url = config('services.domi.health_url');
        if (empty($url)) {
            return false; // domi no configurat/desplegat → Mode B
        }
        try {
            return Http::timeout(2)->get($url)->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Retorna el mode de verificació segons disponibilitat de domi: 'A' (domi) o 'B' (zona). */
    public static function verificationMode(): string
    {
        return self::isOnline() ? 'A' : 'B';
    }

    /**
     * APROVISIONA el compte domi d'un treballador (alta a RRHH → compte a domi, sense passos manuals).
     * Idempotent al costat domi (crea / vincula per DNI / actualitza). Retorna la resposta de domi o
     * ['ok'=>false,...] si domi no respon — l'alta a RRHH MAI es bloqueja per això (es reintenta amb
     * `php artisan domi:provisiona-pendents`).
     */
    public static function provisiona(\App\Models\User $user): array
    {
        $url   = config('services.domi.provisiona_url');
        $token = (string) config('services.domi.token');
        if (empty($url) || $token === '') {
            return ['ok' => false, 'error' => 'aprovisionament domi no configurat (DOMI_PROVISIONA_URL / DOMI_EXPEDIENT_TOKEN)'];
        }
        // Perfil domi segons el tipus de feina (mai privilegis de gestió per aquesta via).
        $priv = str_starts_with((string) $user->work_type, 'DOMICILIARIA') ? 'Domiciliaria' : 'Domiciliaria';
        try {
            $resp = Http::withToken($token)->timeout(8)->post($url, [
                'rrhh_user_id' => $user->id,
                'name'         => $user->name,
                'email'        => $user->email,
                'dni'          => $user->dni,
                'telefono'     => $user->device_phone,
                'privilegio'   => $priv,
            ]);
            $out = $resp->json() ?: ['ok' => false, 'error' => 'resposta buida de domi'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'domi no accessible'];
        }
        /* Persisteix el mapeig operatiu al moment (username domi ↔ usuari RRHH), i sobretot
           deixa constància que domi HO HA CONFIRMAT.
           `domi_username` sol pot venir de l'Excel d'alta massiva, o sigui que tenir-lo no
           demostra que el compte existeixi. `domi_provisioned_at` només l'escriu aquesta
           línia, i per tant només s'omple quan domi ha respost que sí. */
        if (! empty($out['ok']) && ! empty($out['username'])) {
            $user->update([
                'domi_username' => $user->domi_username ?: $out['username'],
                'domi_provisioned_at' => now(),
            ]);
        }

        return $out;
    }
}
