<?php

namespace App\Services;

use App\Models\CertifiedMail;
use App\Models\MensatekSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Client de l'API v7 d'Email Certificat de Mensatek. La configuració (usuari API,
 * token i remitent) viu a mensatek_settings i la gestiona administració des de la UI.
 */
class MensatekCertificat
{
    protected const BASE = 'https://api.mensatek.com/v7';

    protected MensatekSetting $cfg;

    public function __construct()
    {
        $this->cfg = MensatekSetting::getSettings();
    }

    public function configurat(): bool
    {
        return $this->cfg->configurat();
    }

    protected function client(): PendingRequest
    {
        return Http::withBasicAuth((string) $this->cfg->usuari_api, (string) $this->cfg->api_token)
            ->acceptJson()->timeout(30);
    }

    /** Envia (o programa) el lot i desa l'idMensaje de cada destinatari. */
    public function enviar(CertifiedMail $mail): array
    {
        if (! $this->configurat()) {
            return ['ok' => false, 'error' => 'El canal certificat no està configurat.'];
        }

        $destinataris = $mail->recipients->map(fn ($r) => [
            'Nombre' => $r->nom,
            'Email' => $r->email,
        ])->values()->all();

        $payload = [
            'Remitente' => $this->cfg->remitent,
            'Destinatarios' => $destinataris,
            'Asunto' => $mail->assumpte,
            'Mensaje' => nl2br(e($mail->cos)),
            'Referenciausuario' => 'crtrrhh-' . $mail->id,
            'Resp' => 'JSON',
        ];
        if ($mail->programat_at) {
            $payload['Fecha'] = $mail->programat_at->format('Y-m-d H:i');
        }
        if ($mail->acceptacio) {
            $payload['Aceptacion'] = 'SI';
            $payload['Caducidadaceptacion'] = 10;
        }

        $adjunts = [];
        foreach ((array) $mail->adjunts_json as $adj) {
            $ruta = $adj['path'] ?? '';
            if ($ruta !== '' && Storage::exists($ruta)) {
                $adjunts[] = [
                    'Nombre' => $adj['nom'] ?? basename($ruta),
                    'Contenido' => base64_encode(Storage::get($ruta)),
                ];
            }
        }
        if ($adjunts) {
            $payload['Adjuntos'] = $adjunts;
        }

        $resp = $this->client()->post(self::BASE . '/EnviarEMAILCERTIFICADO', $payload);
        $data = $resp->json() ?? [];
        $res = (int) ($data['Res'] ?? -99);

        if ($res <= 0) {
            $motius = [-1 => 'Error d\'autenticació amb Mensatek.', -2 => 'No hi ha crèdits suficients.'];
            return ['ok' => false, 'error' => $data['Error'] ?? ($motius[$res] ?? "Error de l'API (Res $res).")];
        }

        foreach ((array) ($data['Destinatarios'] ?? []) as $d) {
            $email = $d['Email'] ?? $d['email'] ?? null;
            $idm = $d['idMensaje'] ?? $d['IdMensaje'] ?? null;
            if ($email && $idm) {
                $mail->recipients()->where('email', $email)->update(['id_mensaje' => (int) $idm]);
            }
        }

        return ['ok' => true, 'enviats' => (int) ($data['Enviados'] ?? 0),
            'credits' => $data['Cred'] ?? null];
    }

    /** Consulta l'estat actual d'un missatge. */
    public function estat(int $idMensaje): ?array
    {
        if (! $this->configurat()) {
            return null;
        }
        $data = $this->client()->post(self::BASE . '/GetReportEMAILCERTIFICADO', [
            'Idmensaje' => $idMensaje, 'Resp' => 'JSON',
        ])->json() ?? [];

        if ((int) ($data['Res'] ?? 0) !== 1) {
            return null;
        }

        return ['estat' => (int) ($data['Estado'] ?? 0), 'estat_txt' => (string) ($data['txtEstado'] ?? '')];
    }

    /** Descarrega el ZIP de certificats (PDF + CSV) d'un missatge. */
    public function certificat(int $idMensaje): ?string
    {
        if (! $this->configurat()) {
            return null;
        }
        $resp = $this->client()->post(self::BASE . '/GetCertificadoEMAILCERTIFICADO', [
            'Idmensaje' => $idMensaje,
        ]);
        $ct = (string) $resp->header('Content-Type');
        if (! $resp->ok() || str_contains($ct, 'json')) {
            return null;
        }

        return $resp->body();
    }

    /** Cancel·la un missatge programat pendent d'enviar. */
    public function cancellar(int $idMensaje): bool
    {
        if (! $this->configurat()) {
            return false;
        }
        $data = $this->client()->post(self::BASE . '/CancelarEMAILCERTIFICADO', [
            'Idmensaje' => $idMensaje, 'Resp' => 'JSON',
        ])->json() ?? [];

        return (int) ($data['Res'] ?? 0) === 1;
    }

    public function credits(): ?float
    {
        if (! $this->configurat()) {
            return null;
        }
        $data = $this->client()->post(self::BASE . '/GetCreditos', ['Resp' => 'JSON'])->json() ?? [];

        return isset($data['Cred']) ? (float) $data['Cred'] : null;
    }
}
