<?php

namespace App\Support;

/**
 * Política de contrasenya ENS per a RRHH. Mateix contracte que includes/password.php de
 * domi (>=12, majúscula, minúscula, xifra, símbol) — les dues apps no han de divergir.
 * Vegeu domi_crt.gt/docs/PROPOSTA-CREDENCIALS-ENS.md.
 */
class PasswordPolicy
{
    public const MIN = 12;

    /** Regla de validació de Laravel per a contrasenyes noves. */
    public static function rules(): array
    {
        return ['required', 'string', 'min:' . self::MIN,
            'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/\d/', 'regex:/[^A-Za-z0-9]/'];
    }

    public static function messages(): array
    {
        return [
            'password.min' => 'La contrasenya ha de tenir com a mínim ' . self::MIN . ' caràcters.',
            'password.regex' => 'La contrasenya ha d\'incloure majúscula, minúscula, xifra i símbol.',
        ];
    }

    /** ¿Compleix la política? (per validar contrasenyes ja existents o generades). */
    public static function compleix(string $plain): bool
    {
        return mb_strlen($plain) >= self::MIN
            && preg_match('/[a-z]/', $plain) && preg_match('/[A-Z]/', $plain)
            && preg_match('/\d/', $plain) && preg_match('/[^A-Za-z0-9]/', $plain);
    }

    /** Genera una contrasenya aleatòria que SEMPRE compleix la política. */
    public static function genera(int $length = 14): string
    {
        $length = max(self::MIN, $length);
        $min = 'abcdefghijkmnopqrstuvwxyz'; $may = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $num = '23456789'; $sym = '!@#$%&*+-=?';
        $p = [$may[random_int(0, strlen($may) - 1)], $min[random_int(0, strlen($min) - 1)],
              $num[random_int(0, strlen($num) - 1)], $sym[random_int(0, strlen($sym) - 1)]];
        $tot = $min . $may . $num . $sym;
        for ($i = count($p); $i < $length; $i++) $p[] = $tot[random_int(0, strlen($tot) - 1)];
        for ($i = count($p) - 1; $i > 0; $i--) { $j = random_int(0, $i); [$p[$i], $p[$j]] = [$p[$j], $p[$i]]; }
        return implode('', $p);
    }
}
