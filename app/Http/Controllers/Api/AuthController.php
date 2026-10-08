<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)
                     ->where('active', true)
                     ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            // ENS: traça d'autenticació fallida (sense revelar si l'email existeix).
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $user->id ?? null,
                    'action' => 'LOGIN_FAILED',
                    'entity_type' => 'auth',
                    'description' => 'Intent de login fallit per a ' . $request->email,
                    'ip_address' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) { /* la traça no pot bloquejar el flux */ }
            throw ValidationException::withMessages([
                'email' => ['Les credencials proporcionades no són correctes.'],
            ]);
        }

        // «Accés a l'app» tret per RRHH: segueix a la plantilla però no entra aquí. Es diu després
        // de comprovar la contrasenya, perquè no serveixi per saber quins comptes existeixen.
        if ($user->acces_app === false) {
            try {
                \App\Models\AuditLog::create([
                    'user_id' => $user->id, 'action' => 'LOGIN_SENSE_ACCES', 'entity_type' => 'auth',
                    'description' => 'Intent d\'entrada d\'un compte sense accés a l\'app',
                    'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) { /* la traça no bloqueja */ }
            throw ValidationException::withMessages([
                'email' => ['Aquest compte no té accés a l\'app. Si creus que és un error, parla amb RRHH.'],
            ]);
        }

        // ENS Fase 2 — SEGON FACTOR (simètric a domi). La contrasenya és el factor 1.
        $sf = \App\Support\SecondFactor::verifica($user, [
            'disp_token'       => $request->input('disp_token'),
            'totp_codi'        => $request->input('totp_codi'),
            'codi_recuperacio' => $request->input('codi_recuperacio'),
        ]);
        try {
            \App\Models\AuditLog::create([
                'user_id' => $user->id, 'action' => '2FA_' . ($sf['ok'] ? 'OK' : 'FAIL'),
                'entity_type' => 'auth',
                'description' => 'Segon factor: ' . $sf['metode']
                    . (! empty($sf['recuperacio']) ? ' (CODI DE RECUPERACIÓ consumit; en queden '
                        . \App\Support\SecondFactor::codisRecuperacioRestants($user->fresh()) . ')' : ''),
                'ip_address' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) { /* la traça no bloqueja */ }
        if ($sf['obligatori'] && ! $sf['ok']) {
            if ($sf['metode'] === \App\Support\SecondFactor::TOTP) {
                // Contrasenya correcta, falta el codi: el client l'ha de demanar i reenviar.
                return response()->json(['second_factor_required' => true, 'method' => 'totp',
                    'message' => 'Introdueix el codi de la teva app d\'autenticació.'], 200);
            }
            throw ValidationException::withMessages(['email' => [$sf['motiu']]]);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        // Auto-assign onboarding profile for workers who don't have one yet
        if ($user->role === 'worker' && !$user->onboarding_profile_id && !$user->onboarding_completed) {
            $profile = null;

            // Fisioterapeuta → specific profile
            if ($user->job_profile === 'Fisioterapeuta') {
                $profile = \App\Models\OnboardingProfile::where('name', 'LIKE', '%Fisioterapeuta%')->first();
            }

            // Fallback: default profile for any worker
            if (!$profile) {
                $profile = \App\Models\OnboardingProfile::where('is_default', true)->first();
            }

            if ($profile) {
                $user->update(['onboarding_profile_id' => $profile->id]);
                $user->refresh();
            }
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->makeHidden(['password']),
        ]);
    }

    /**
     * POST /api/v1/auth/change-password — canvi de la contrasenya PRÒPIA.
     * És el destí obligat quan must_change_password=true (una temporal del reset o de
     * l'alta). Exigeix la contrasenya actual, aplica la política ENS i treu el flag.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => \App\Support\PasswordPolicy::rules(),
            'password_confirmation' => 'required|same:password',
        ], \App\Support\PasswordPolicy::messages());

        $user = $request->user();
        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['La contrasenya actual no és correcta.']]);
        }
        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['password' => ['La nova contrasenya ha de ser diferent de l\'actual.']]);
        }

        $user->update(['password' => Hash::make($request->password), 'must_change_password' => false]);
        // Invalida la resta de tokens: un canvi de contrasenya tanca les altres sessions.
        $user->tokens()->where('id', '!=', optional($user->currentAccessToken())->id)->delete();

        try {
            \App\Models\AuditLog::create([
                'user_id' => $user->id, 'action' => 'PASSWORD_CHANGED', 'entity_type' => 'auth',
                'description' => 'Canvi de contrasenya propi', 'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) { /* la traça no bloqueja */ }

        return response()->json(['success' => true, 'message' => 'Contrasenya actualitzada.']);
    }

    /**
     * GET /api/v1/auth/2fa — estat del segon factor PROPI (el que ha de pintar la pantalla).
     * No exposa mai el secret ni els còdis: només si n'hi ha i quants en queden.
     */
    public function twofaStatus(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'method'                  => \App\Support\SecondFactor::metode($u),
            'enrolled'                => \App\Support\SecondFactor::enrolat($u),
            'required'                => \App\Support\SecondFactor::obligatoriPer($u),   // imposat per Direcció
            'enforced'                => \App\Support\SecondFactor::exigit($u),          // se li demanarà al login
            'recovery_codes_left'     => \App\Support\SecondFactor::codisRecuperacioRestants($u),
            'recovery_generated_at'   => $u->totp_recovery_generated_at,
        ]);
    }

    /**
     * POST /api/v1/auth/2fa/prepare — l'usuari (amb mètode 'totp') prepara l'enrolament.
     * Retorna el secret i la URI otpauth per afegir-ho a l'app; encara sense confirmar.
     *
     * Es NEGA si ja està enrolat: preparar sobreescriu el secret i tomba l'enrolament vigent,
     * o sigui que amb una sessió robada n'hi hauria prou per moure el 2FA a un altre mòbil.
     * Per canviar de dispositiu cal desactivar-lo primer (exigeix contrasenya + codi).
     */
    public function twofaPrepare(Request $request)
    {
        $user = $request->user();
        if (\App\Support\SecondFactor::metode($user) !== \App\Support\SecondFactor::TOTP) {
            return response()->json(['error' => 'not_totp', 'message' => 'El teu accés no usa Google Authenticator.'], 422);
        }
        if (\App\Support\SecondFactor::enrolat($user)) {
            return response()->json(['error' => 'already_enrolled',
                'message' => 'Ja tens el segon factor actiu. Desactiva\'l primer si vols canviar de dispositiu.'], 422);
        }
        $r = \App\Support\SecondFactor::totpPreparar($user);

        return response()->json(['secret' => $r['secret'], 'uri' => $r['uri']]);
    }

    /**
     * POST /api/v1/auth/2fa/confirm — confirma l'enrolament amb el primer codi i LLIURA els
     * còdis de recuperació. És l'únic moment en què existeixen en clar: si l'usuari no els
     * desa aquí, haurà de regenerar-los (i els antics deixaran de valer).
     */
    public function twofaConfirm(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();
        $ok = \App\Support\SecondFactor::totpConfirmar($user, $request->code);
        if (! $ok) {
            return response()->json(['success' => false, 'message' => 'El codi no és correcte.'], 422);
        }
        $codis = \App\Support\SecondFactor::generaCodisRecuperacio($user->fresh());
        $this->traca($request, '2FA_ENROLLED', 'Segon factor TOTP activat per l\'usuari');

        return response()->json([
            'success' => true,
            'message' => 'Segon factor activat. Desa els còdis de recuperació: no es tornaran a mostrar.',
            'recovery_codes' => $codis,
        ]);
    }

    /**
     * POST /api/v1/auth/2fa/recovery-codes — regenera el joc de còdis (invalida els anteriors).
     * Exigeix un codi vigent de l'app: si no, una sessió robada es fabricaria còdis propis.
     */
    public function twofaRecoveryCodes(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user();
        if (! \App\Support\SecondFactor::enrolat($user)) {
            return response()->json(['message' => 'No tens el segon factor actiu.'], 422);
        }
        $sf = \App\Support\SecondFactor::verifica($user, ['totp_codi' => $request->code]);
        if (! $sf['ok']) {
            return response()->json(['message' => 'El codi no és correcte.'], 422);
        }
        $codis = \App\Support\SecondFactor::generaCodisRecuperacio($user);
        $this->traca($request, '2FA_RECOVERY_REGEN', 'Còdis de recuperació regenerats (els anteriors ja no valen)');

        return response()->json(['success' => true, 'recovery_codes' => $codis]);
    }

    /**
     * DELETE /api/v1/auth/2fa — l'usuari es desactiva el segon factor.
     * Exigeix contrasenya I un codi vigent (o un codi de recuperació): desactivar-lo és el
     * primer que faria qui robés una sessió.
     * Si Direcció l'ha declarat obligatori per a aquesta persona, no es pot desactivar.
     */
    public function twofaDisable(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
            'code' => 'nullable|string',
            'codi_recuperacio' => 'nullable|string',
        ]);
        $user = $request->user();

        if (! \App\Support\SecondFactor::enrolat($user)) {
            return response()->json(['message' => 'No tens el segon factor actiu.'], 422);
        }
        if (\App\Support\SecondFactor::obligatoriPer($user)) {
            return response()->json(['message' => 'El segon factor és obligatori per al teu perfil: '
                . 'no te\'l pots desactivar. Parla amb administració.'], 403);
        }
        if (! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages(['password' => ['La contrasenya no és correcta.']]);
        }
        $sf = \App\Support\SecondFactor::verifica($user, [
            'totp_codi' => $request->input('code'),
            'codi_recuperacio' => $request->input('codi_recuperacio'),
        ]);
        if (! $sf['ok']) {
            return response()->json(['message' => 'El codi no és correcte.'], 422);
        }

        \App\Support\SecondFactor::desactiva($user);
        $this->traca($request, '2FA_DISABLED', 'Segon factor TOTP desactivat per l\'usuari');

        return response()->json(['success' => true, 'message' => 'Segon factor desactivat.']);
    }

    /** Traça d'auditoria que MAI bloqueja el flux. */
    private function traca(Request $request, string $accio, string $descripcio): void
    {
        try {
            \App\Models\AuditLog::create([
                'user_id' => $request->user()?->id, 'action' => $accio, 'entity_type' => 'auth',
                'description' => $descripcio, 'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) { /* la traça no pot bloquejar el flux */ }
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessió tancada correctament']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request)
    {
        return response()->json($request->user()->makeHidden(['password']));
    }

    /**
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)
                     ->where('active', true)
                     ->first();

        // Always return the same success response regardless of whether email exists (prevent enumeration)
        if (! $user) {
            return response()->json([
                'success' => true,
                'message' => "Si existeix un compte actiu amb aquest correu, rebreu la nova contrasenya.",
            ]);
        }

        $newPassword = \App\Support\PasswordPolicy::genera(14);   // ENS: compleix política
        $user->update([
            'password' => Hash::make($newPassword),
            'must_change_password' => true,
        ]);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $newPassword));
            \Log::info("Password reset email sent", ['user_id' => $user->id, 'email' => $user->email]);
        } catch (\Throwable $e) {
            \Log::error('Password reset email failed', [
                'user_id' => $user->id,
                'email' => $user->email,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Si existeix un compte actiu amb aquest correu, rebreu la nova contrasenya.",
        ]);
    }

    /**
     * POST /api/v1/auth/generate-random-password
     */
    public function generateRandomPassword(Request $request)
    {
        $request->validate([
            'length' => 'nullable|integer|min:8|max:32',
        ]);

        // ENS: la generada ha de complir la política (Str::random no garanteix símbol/xifra).
        $password = \App\Support\PasswordPolicy::genera($request->length ?? 14);

        return response()->json([
            'password' => $password,
        ]);
    }

    /**
     * POST /api/v1/auth/geo-consent
     */
    public function geoConsent(Request $request)
    {
        $user = $request->user();
        $user->update(['geo_consent_accepted_at' => now()]);
        return response()->json($user->fresh()->makeHidden(['password']));
    }
}
