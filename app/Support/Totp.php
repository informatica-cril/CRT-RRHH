<?php

namespace App\Support;

/**
 * TOTP (RFC 6238) en PHP pur, compatible amb Google Authenticator. MATEIX algorisme que
 * includes/totp.php de domi (les dues apps no divergeixen): HMAC-SHA1, 6 dígits, 30 s,
 * finestra ±1. Vegeu domi_crt.gt/docs/PROPOSTA-CREDENCIALS-ENS.md §4.6.
 */
class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;
    public const WINDOW = 1;
    public const SECRET_LEN = 20;

    public static function nouSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_LEN));
    }

    public static function codi(string $secretB32, ?int $ts = null): ?string
    {
        $ts = $ts ?? time();
        $key = self::base32Decode($secretB32);
        if ($key === '') return null;
        $bin = pack('N*', 0) . pack('N*', intdiv($ts, self::PERIOD));
        $hash = hash_hmac('sha1', $bin, $key, true);
        $off = ord($hash[strlen($hash) - 1]) & 0x0f;
        $trunc = ((ord($hash[$off]) & 0x7f) << 24) | ((ord($hash[$off + 1]) & 0xff) << 16)
               | ((ord($hash[$off + 2]) & 0xff) << 8) | (ord($hash[$off + 3]) & 0xff);
        return str_pad((string) ($trunc % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function verifica(string $secretB32, string $codiUsuari): bool
    {
        $codiUsuari = preg_replace('/\s+/', '', $codiUsuari);
        if (! preg_match('/^\d{' . self::DIGITS . '}$/', $codiUsuari)) return false;
        $ara = time();
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $c = self::codi($secretB32, $ara + $i * self::PERIOD);
            if ($c !== null && hash_equals($c, $codiUsuari)) return true;
        }
        return false;
    }

    public static function uri(string $secretB32, string $compte, string $emissor = 'CRT RRHH'): string
    {
        return 'otpauth://totp/' . rawurlencode($emissor . ':' . $compte)
             . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($emissor)
             . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function base32Encode(string $data): string
    {
        $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = ''; $bits = 0; $val = 0;
        for ($i = 0; $i < strlen($data); $i++) {
            $val = ($val << 8) | ord($data[$i]); $bits += 8;
            while ($bits >= 5) { $out .= $alf[($val >> ($bits - 5)) & 31]; $bits -= 5; }
        }
        if ($bits > 0) $out .= $alf[($val << (5 - $bits)) & 31];
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $alf = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $bits = 0; $val = 0; $out = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $val = ($val << 5) | strpos($alf, $b32[$i]); $bits += 5;
            if ($bits >= 8) { $out .= chr(($val >> ($bits - 8)) & 0xff); $bits -= 8; }
        }
        return $out;
    }
}
