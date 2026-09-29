<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MailSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailSettingController extends Controller
{
    /** GET /api/v1/mail-settings — configuració actual (sense exposar la contrasenya). */
    public function show()
    {
        $s = MailSetting::getSettings();

        return response()->json([
            'mailer' => $s->mailer,
            'host' => $s->host,
            'port' => $s->port,
            'encryption' => $s->encryption,
            'username' => $s->username,
            'has_password' => ! empty($s->password),
            'from_address' => $s->from_address,
            'from_name' => $s->from_name,
            'last_test_at' => $s->last_test_at,
            'last_test_status' => $s->last_test_status,
        ]);
    }

    /** PUT /api/v1/mail-settings — desa la configuració. */
    public function update(Request $request)
    {
        $data = $request->validate([
            'mailer' => 'required|in:smtp,log',
            'host' => 'nullable|string',
            'port' => 'nullable|integer|min:1|max:65535',
            'encryption' => 'nullable|in:tls,ssl,none',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'from_address' => 'nullable|email',
            'from_name' => 'nullable|string',
        ]);

        $s = MailSetting::getSettings();
        $s->mailer = $data['mailer'];
        $s->host = $data['host'] ?? null;
        $s->port = $data['port'] ?? 587;
        $s->encryption = ($data['encryption'] ?? null) === 'none' ? null : ($data['encryption'] ?? null);
        $s->username = $data['username'] ?? null;
        $s->from_address = $data['from_address'] ?? null;
        $s->from_name = $data['from_name'] ?? null;
        // Només actualitzar la contrasenya si s'ha enviat una de nova (no buida)
        if ($request->filled('password')) {
            $s->password = $data['password'];
        }
        $s->save();

        return $this->show();
    }

    /** POST /api/v1/mail-settings/test — envia un correu de prova i verifica l'enviament. */
    public function sendTest(Request $request)
    {
        $data = $request->validate([
            'to' => 'required|email',
        ]);

        $s = MailSetting::getSettings();
        $s->applyToRuntime();

        try {
            Mail::raw(
                "Aquest és un correu de prova de CRT RRHH.\n\n"
                . "Si el reps, la configuració de correu funciona correctament.\n"
                . "Enviat: " . now()->format('d/m/Y H:i:s'),
                function ($message) use ($data, $s) {
                    $message->to($data['to'])->subject('Prova de configuració de correu — CRT RRHH');
                    if ($s->from_address) {
                        $message->from($s->from_address, $s->from_name ?: 'CRT RRHH');
                    }
                }
            );

            $s->update(['last_test_at' => now(), 'last_test_status' => 'ok']);

            return response()->json([
                'success' => true,
                'message' => "Correu de prova enviat correctament a {$data['to']}"
                    . ($s->mailer === 'log' ? " (mailer='log': revisa storage/logs/laravel.log)" : ''),
            ]);
        } catch (\Throwable $e) {
            $s->update(['last_test_at' => now(), 'last_test_status' => 'error']);

            return response()->json([
                'success' => false,
                'message' => "No s'ha pogut enviar el correu: " . $e->getMessage(),
            ], 422);
        }
    }
}
