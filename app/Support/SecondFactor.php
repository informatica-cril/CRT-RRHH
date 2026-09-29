<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

/**
 * Segon factor per usuari a RRHH (ENS ALTA), simètric al de domi (segon_factor.php):
 *   'dispositiu' -> possessió de la llave d'empresa (token HMAC, mateixa clau que el
 *                   fichatge: config('services.disp_corp.key')).
 *   'totp'       -> codi de 6 xifres (Google Authenticator), per a qui l'admin autoritza.
 *
 * DESPLEGAMENT SEGUR — TRES interruptors, de menys a més abast (vegeu docs/SEGON-FACTOR.md):
 *   1. VOLUNTARI (per defecte): qui acaba l'enrolament TOTP se l'exigeix a si mateix. Ningú
 *      més el nota. És l'estat actual: 2FA DISPONIBLE i OPCIONAL, sense canviar la dinàmica.
 *   2. PER ROLS: SECOND_FACTOR_ROLES=admin,hr → obligatori només per a aquests rols.
 *   3. TOTHOM: SECOND_FACTOR_REQUIRED=true (Fase B).
 * Kill-switch d'emergència per sobre de tots: env SECOND_FACTOR_OFF=1.
 *
 * El secret TOTP i els còdis de recuperació es desen xifrats amb Crypt (APP_KEY).
 * Vegeu domi_crt.gt/docs/PROPOSTA-CREDENCIALS-ENS.md §4.
 */
class SecondFactor
{
    public const DISPOSITIU = 'dispositiu';
    public const TOTP = 'totp';

    /** Quants còdis de recuperació es lliuren en enrolar-se. */
    public const CODIS_RECUPERACIO = 10;

    /** Kill-switch d'emergència: desactiva QUALSEVOL exigència de segon factor. */
    public static function apagat(): bool
    {
        return env('SECOND_FACTOR_OFF') === '1' || env('SECOND_FACTOR_OFF') === 1;
    }

    /** Interruptor GLOBAL (Fase B): obligatori per a tota la plantilla. */
    public static function obligatori(): bool
    {
        if (self::apagat()) return false;

        /* La política la decideix Direcció des de l'aplicació (taula second_factor_settings).
           El .env queda com a XARXA: si la taula no hi és —desplegament a mig fer, base
           restaurada d'una còpia antiga— es cau al valor de config i tot es comporta com
           abans. Mai al revés: una taula que falta no pot obrir una porta que estava tancada. */
        $s = \App\Models\SecondFactorSetting::actual();
        if ($s) { return $s->mode === \App\Models\SecondFactorSetting::ALL; }

        return (bool) config('security.second_factor_required', false);
    }

    /** Rols als quals Direcció ha decidit exigir-lo (config: security.second_factor_roles). */
    public static function rolsObligats(): array
    {
        $raw = config('security.second_factor_roles', '');
        $rols = is_array($raw) ? $raw : preg_split('/[\s,]+/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);

        $s = \App\Models\SecondFactorSetting::actual();
        if ($s) {
            /* En mode 'all' l'obligatorietat ja la resol obligatori(); aquí només compta la
               llista quan el mode és 'roles'. */
            $rols = $s->mode === \App\Models\SecondFactorSetting::ROLES ? ($s->roles ?: []) : [];
        }

        return array_values(array_filter(array_map('strtolower', $rols ?: [])));
    }

    /** Obligat a aquesta persona per decisió de Direcció (global o pel seu rol)? */
    public static function obligatoriPer(User $u): bool
    {
        if (self::apagat()) return false;
        if (self::obligatori()) return true;

        return in_array(strtolower((string) $u->role), self::rolsObligats(), true);
    }

    /**
     * S'ha d'exigir el segon factor a aquesta persona en aquest login?
     * Obligat per Direcció, O bé se l'ha activat ella mateixa (enrolament TOTP confirmat).
     * Aquesta segona branca és la que fa que el 2FA voluntari serveixi d'alguna cosa: qui
     * l'activa queda protegit encara que a la resta de la plantilla no se li demani res.
     */
    public static function exigit(User $u): bool
    {
        if (self::apagat()) return false;
        if (self::obligatoriPer($u)) return true;

        return self::metode($u) === self::TOTP && (bool) $u->totp_confirmed;
    }

    public static function metode(User $u): string
    {
        return $u->second_factor === self::TOTP ? self::TOTP : self::DISPOSITIU;
    }

    /** El token identifica un dispositiu corporatiu? (mateix HMAC que el fichatge). */
    public static function esDispositiuCorporatiu(?string $token): bool
    {
        $key = (string) config('services.disp_corp.key');
        if (strlen($key) < 32 || ! $token) return false;
        return hash_equals(hash_hmac('sha256', 'dispositiu-corporatiu-crt', $key), $token);
    }

    /**
     * Verifica el segon factor. Amb mètode TOTP accepta el codi de l'app o, si l'usuari ha
     * perdut el mòbil, un codi de recuperació ('codi_recuperacio'), que ES CONSUMEIX.
     *
     * @return array{ok:bool, metode:string, motiu:string, obligatori:bool, recuperacio:bool}
     */
    public static function verifica(User $u, array $ctx): array
    {
        $metode = self::metode($u);
        $obl = self::exigit($u);

        if ($metode === self::TOTP) {
            if (! $u->totp_confirmed || ! $u->totp_secret) {
                return ['ok' => false, 'metode' => self::TOTP, 'obligatori' => $obl, 'recuperacio' => false,
                        'motiu' => 'Cal completar l\'enrolament del segon factor.'];
            }
            $secret = self::desSecret($u->totp_secret);
            $ok = $secret !== null && Totp::verifica($secret, (string) ($ctx['totp_codi'] ?? ''));
            if ($ok) {
                return ['ok' => true, 'metode' => self::TOTP, 'obligatori' => $obl, 'recuperacio' => false, 'motiu' => ''];
            }

            // Segona via: codi de recuperació d'un sol ús.
            $recup = (string) ($ctx['codi_recuperacio'] ?? '');
            if ($recup !== '' && self::consumeixCodiRecuperacio($u, $recup)) {
                return ['ok' => true, 'metode' => self::TOTP, 'obligatori' => $obl, 'recuperacio' => true, 'motiu' => ''];
            }

            return ['ok' => false, 'metode' => self::TOTP, 'obligatori' => $obl, 'recuperacio' => false,
                    'motiu' => 'Codi de verificació incorrecte.'];
        }

        $ok = self::esDispositiuCorporatiu($ctx['disp_token'] ?? null);
        return ['ok' => $ok, 'metode' => self::DISPOSITIU, 'obligatori' => $obl, 'recuperacio' => false,
                'motiu' => $ok ? '' : 'Aquest dispositiu no està reconegut com a dispositiu d\'empresa.'];
    }

    /** Prepara un secret TOTP (sense confirmar) i retorna secret + URI. */
    public static function totpPreparar(User $u): array
    {
        $secret = Totp::nouSecret();
        $u->update(['totp_secret' => self::xifSecret($secret), 'totp_confirmed' => false]);
        return ['secret' => $secret, 'uri' => Totp::uri($secret, $u->email)];
    }

    /** Confirma l'enrolament amb el primer codi. */
    public static function totpConfirmar(User $u, string $codi): bool
    {
        $secret = $u->totp_secret ? self::desSecret($u->totp_secret) : null;
        if ($secret === null || ! Totp::verifica($secret, $codi)) return false;
        $u->update(['totp_confirmed' => true]);
        return true;
    }

    /** Enrolament complet (l'app ja té el secret)? */
    public static function enrolat(User $u): bool
    {
        return self::metode($u) === self::TOTP && (bool) $u->totp_confirmed && (bool) $u->totp_secret;
    }

    /**
     * Desactiva el segon factor TOTP d'aquesta persona: esborra el secret, l'enrolament i els
     * còdis de recuperació. NO toca el mètode assignat per l'admin — si Direcció l'ha declarat
     * obligatori (global o pel seu rol), la persona s'haurà de tornar a enrolar per entrar.
     */
    public static function desactiva(User $u): void
    {
        $u->update([
            'totp_secret' => null, 'totp_confirmed' => false,
            'totp_recovery_codes' => null, 'totp_recovery_generated_at' => null,
        ]);
    }

    // ── Còdis de recuperació ──────────────────────────────────────────────────
    // Un sol ús. Es desa NOMÉS el hash (sha256 d'un codi de 50 bits d'entropia: el diccionari
    // no hi arriba i el hash ràpid no hi afegeix risc), i el JSON sencer va xifrat.

    /** Genera i desa un joc nou de còdis. Retorna els còdis EN CLAR (única vegada que existeixen). */
    public static function generaCodisRecuperacio(User $u, int $quants = self::CODIS_RECUPERACIO): array
    {
        $codis = [];
        for ($i = 0; $i < $quants; $i++) {
            $codis[] = self::codiAleatori();
        }
        $u->update([
            'totp_recovery_codes' => self::xifSecret(json_encode(array_map([self::class, 'hashCodi'], $codis))),
            'totp_recovery_generated_at' => now(),
        ]);

        return $codis;
    }

    /** Quants còdis de recuperació li queden sense fer servir. */
    public static function codisRecuperacioRestants(User $u): int
    {
        return count(self::hashosRecuperacio($u));
    }

    /** Consumeix un codi de recuperació (un sol ús). */
    public static function consumeixCodiRecuperacio(User $u, string $codi): bool
    {
        $hashos = self::hashosRecuperacio($u);
        if ($hashos === []) return false;

        $objectiu = self::hashCodi($codi);
        $trobat = false;
        $resta = [];
        foreach ($hashos as $h) {
            if (! $trobat && hash_equals((string) $h, $objectiu)) { $trobat = true; continue; }
            $resta[] = $h;
        }
        if (! $trobat) return false;

        $u->update(['totp_recovery_codes' => self::xifSecret(json_encode($resta))]);

        return true;
    }

    /** @return string[] */
    private static function hashosRecuperacio(User $u): array
    {
        if (! $u->totp_recovery_codes) return [];
        $pla = self::desSecret($u->totp_recovery_codes);
        if ($pla === null) return [];
        $llista = json_decode($pla, true);

        return is_array($llista) ? array_values(array_filter($llista, 'is_string')) : [];
    }

    /** Normalitza abans de hashejar: majúscules i sense guions ni espais. */
    private static function hashCodi(string $codi): string
    {
        return hash('sha256', strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codi)));
    }

    /** 10 caràcters base32 (~50 bits) en format XXXXX-XXXXX, llegible per telèfon. */
    private static function codiAleatori(): string
    {
        $alf = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // sense I,O,0,1: es dicten per telèfon
        $c = '';
        for ($i = 0; $i < 10; $i++) {
            $c .= $alf[random_int(0, strlen($alf) - 1)];
        }

        return substr($c, 0, 5) . '-' . substr($c, 5);
    }

    /** ADMIN determina el mètode. En canviar de mètode s'invalida l'enrolament anterior. */
    public static function adminSetMetode(User $u, string $metode, string $adminEmail): bool
    {
        if (! in_array($metode, [self::DISPOSITIU, self::TOTP], true)) return false;
        $u->update(['second_factor' => $metode, 'totp_secret' => null, 'totp_confirmed' => false,
                    'totp_recovery_codes' => null, 'totp_recovery_generated_at' => null,
                    'second_factor_by' => $adminEmail, 'second_factor_at' => now()]);
        return true;
    }

    private static function xifSecret(string $plain): string { return Crypt::encryptString($plain); }
    private static function desSecret(string $blob): ?string
    {
        try { return Crypt::decryptString($blob); } catch (\Throwable $e) { return null; }
    }
}
