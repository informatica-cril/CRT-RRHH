<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * Client de segellat de temps RFC 3161 (FNMT / Mensatek…).
 *
 * Construeix la petició (TimeStampReq) a partir de l'ARREL del dia amb `openssl ts -query`, la porta
 * a la TSA per HTTP i desa el token (TimeStampToken DER en base64). No inventa cap endpoint: si no
 * està configurat (config/integrity.php → tsa.url buit), retorna 'pendent' i el dia queda protegit
 * només per la cadena de hash interna fins que s'activi la FNMT.
 *
 * Robust per disseny: qualsevol error retorna estat 'error' amb el motiu; MAI llança (no ha de tombar
 * el tancament diari ni malgastar el crèdit).
 */
class TsaClient
{
    /** @return array{status:string, provider?:string, token?:string, time?:\Carbon\Carbon|null, error?:string} */
    public function stamp(string $rootHashHex): array
    {
        $cfg = config('integrity.tsa');
        $provider = $cfg['provider'] ?? 'fnmt';

        if (empty($cfg['url'])) {
            return ['status' => 'pendent', 'provider' => $provider, 'error' => 'TSA no configurada (tsa.url buit)'];
        }
        if (! preg_match('/^[0-9a-f]{64}$/i', $rootHashHex)) {
            return ['status' => 'error', 'provider' => $provider, 'error' => 'arrel no vàlida'];
        }

        $dir = sys_get_temp_dir() . '/tsa_' . bin2hex(random_bytes(6));
        @mkdir($dir);
        $tsq = "$dir/req.tsq";
        $tsr = "$dir/resp.tsr";

        try {
            // 1) Petició de segellat sobre el digest (l'arrel JA és un sha256 hex).
            $alg = $cfg['hash_alg'] ?? 'sha256';
            $p = new Process([$cfg['openssl'] ?? 'openssl', 'ts', '-query', '-digest', $rootHashHex, "-$alg", '-cert', '-out', $tsq]);
            $p->setTimeout($cfg['timeout'] ?? 20);
            $p->run();
            if (! $p->isSuccessful() || ! is_file($tsq)) {
                return $this->fail($provider, 'openssl ts -query: ' . trim($p->getErrorOutput()), $dir);
            }

            // 2) Enviament a la TSA (RFC 3161 sobre HTTP).
            $req = Http::timeout($cfg['timeout'] ?? 20)
                ->withBody(file_get_contents($tsq), 'application/timestamp-query')
                ->withHeaders(['Accept' => 'application/timestamp-reply']);
            if (! empty($cfg['user'])) {
                $req = $req->withBasicAuth($cfg['user'], $cfg['password'] ?? '');
            }
            $resp = $req->post($cfg['url']);
            if (! $resp->successful() || $resp->body() === '') {
                return $this->fail($provider, 'TSA HTTP ' . $resp->status(), $dir);
            }
            file_put_contents($tsr, $resp->body());

            // 3) Extreu el genTime del token (informatiu; el token DER és la prova).
            $time = null;
            $pt = new Process([$cfg['openssl'] ?? 'openssl', 'ts', '-reply', '-in', $tsr, '-text']);
            $pt->setTimeout($cfg['timeout'] ?? 20);
            $pt->run();
            if ($pt->isSuccessful() && preg_match('/Time stamp:\s*(.+)/', $pt->getOutput(), $m)) {
                try { $time = Carbon::parse(trim($m[1])); } catch (\Throwable $e) { $time = null; }
            }

            $token = base64_encode(file_get_contents($tsr));
            $this->cleanup($dir);

            return ['status' => 'segellat', 'provider' => $provider, 'token' => $token, 'time' => $time];
        } catch (\Throwable $e) {
            return $this->fail($provider, substr($e->getMessage(), 0, 200), $dir);
        }
    }

    private function fail(string $provider, string $msg, string $dir): array
    {
        $this->cleanup($dir);

        return ['status' => 'error', 'provider' => $provider, 'error' => substr($msg, 0, 255)];
    }

    private function cleanup(string $dir): void
    {
        foreach (glob("$dir/*") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
