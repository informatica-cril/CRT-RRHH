<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IaLocal
{
    private string $url;
    private string $model;
    private ?string $key;

    private array $curlExtra = [];

    public function __construct()
    {
        $this->url = rtrim((string) config('ia.url', env('IA_LOCAL_URL', 'http://127.0.0.1:11434')), '/');
        $this->model = (string) config('ia.model', env('IA_LOCAL_MODEL', 'qwen2.5vl:32b'));
        $this->key = config('ia.key', env('IA_LOCAL_KEY')) ?: null;

        $ip = trim((string) env('IA_LOCAL_IP', ''));
        if ($ip !== '') {
            $host = (string) parse_url($this->url, PHP_URL_HOST);
            $port = (int) (parse_url($this->url, PHP_URL_PORT) ?: (parse_url($this->url, PHP_URL_SCHEME) === 'https' ? 443 : 80));
            if ($host !== '') {
                $this->curlExtra[CURLOPT_RESOLVE] = ["{$host}:{$port}:{$ip}"];
            }
        }
        $bind = trim((string) env('IA_LOCAL_BIND', ''));
        if ($bind === 'auto' && stripos(PHP_OS, 'darwin') !== false) {
            $bind = trim((string) @shell_exec("ifconfig 2>/dev/null | awk '/^[a-z]/{u=/^utun/} u && /inet 10\\./{print \$2; exit}'"));
        }
        if ($bind !== '' && $bind !== 'auto') {
            $this->curlExtra[CURLOPT_INTERFACE] = $bind;
        }
    }

    private function http()
    {
        $p = Http::withHeaders($this->capceleres());

        return $this->curlExtra ? $p->withOptions(['curl' => $this->curlExtra]) : $p;
    }

    public function model(): string
    {
        return $this->model;
    }

    private function capceleres(): array
    {
        $h = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($this->key) {
            $h['Authorization'] = 'Bearer ' . $this->key;
        }

        return $h;
    }

    public function disponible(): bool
    {
        foreach (['/api/tags', '/v1/models'] as $ruta) {
            try {
                $r = $this->http()->connectTimeout(10)->timeout(8)->get($this->url . $ruta);
                if ($r->successful()) {
                    return true;
                }
            } catch (\Throwable $e) {
                // provem la següent
            }
        }

        return false;
    }

    public function xat(string $sistema, string $usuari, array $opts = []): array
    {
        $t0 = microtime(true);
        $payload = [
            'model' => $opts['model'] ?? $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $sistema],
                ['role' => 'user', 'content' => $usuari],
            ],
            'temperature' => $opts['temperature'] ?? 0.15,
            'max_tokens' => $opts['max_tokens'] ?? 3200,
            'stream' => false,
        ];

        $error = null;
        foreach (['/v1/chat/completions', '/api/chat/completions'] as $ruta) {
            try {
                $r = $this->http()
                    ->connectTimeout($opts['connect_timeout'] ?? 30)
                    ->timeout($opts['timeout'] ?? 180)
                    ->post($this->url . $ruta, $payload);
                if ($r->successful()) {
                    $text = (string) data_get($r->json(), 'choices.0.message.content', '');

                    return [
                        'ok' => $text !== '',
                        'text' => self::neteja($text),
                        'ms' => (int) round((microtime(true) - $t0) * 1000),
                        'model' => $payload['model'],
                    ];
                }
                $error = 'HTTP ' . $r->status();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }

        Log::warning('[IaLocal] sense resposta: ' . $error);

        return ['ok' => false, 'text' => '', 'ms' => (int) round((microtime(true) - $t0) * 1000),
                'model' => $payload['model'], 'error' => $error];
    }

    public static function neteja(string $text): string
    {
        $t = preg_replace('/<think>.*?<\/think>/su', '', $text);
        $t = preg_replace('/\*\*(.+?)\*\*/su', '$1', (string) $t);
        $t = preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/su', '$1', (string) $t);
        $t = preg_replace('/^#{1,6}\s*/mu', '', (string) $t);
        $t = preg_replace('/^\s*[-•*]\s+/mu', '- ', (string) $t);
        $t = preg_replace('/\n{3,}/u', "\n\n", (string) $t);

        return trim((string) $t);
    }
}
