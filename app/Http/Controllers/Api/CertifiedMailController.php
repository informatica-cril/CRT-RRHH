<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CertifiedMail;
use App\Models\MensatekSetting;
use App\Models\User;
use App\Services\MensatekCertificat;
use Illuminate\Http\Request;

class CertifiedMailController extends Controller
{
    /** GET /api/v1/certified-mails — històric amb l'estat per destinatari. */
    public function index()
    {
        $mails = CertifiedMail::with(['recipients:id,certified_mail_id,user_id,email,nom,id_mensaje,estat,estat_txt,estat_at', 'sender:id,name'])
            ->orderByDesc('id')->limit(200)->get();

        return response()->json([
            'configurat' => MensatekSetting::getSettings()->configurat(),
            'mails' => $mails,
        ]);
    }

    /** POST /api/v1/certified-mails — crea i envia (o programa) un lot certificat. */
    public function store(Request $request, MensatekCertificat $svc)
    {
        $data = $request->validate([
            'tipus' => 'required|in:acuse,expedient',
            'expedient_ref' => 'nullable|string|max:120',
            'assumpte' => 'required|string|max:200',
            'cos' => 'required|string|max:20000',
            'acceptacio' => 'boolean',
            'programat_at' => 'nullable|date|after:now',
            'destinataris' => 'required|array|min:1',
            'destinataris.*' => 'integer|exists:users,id',
            'adjunts' => 'nullable|array|max:5',
            'adjunts.*' => 'file|max:8192|mimes:pdf,doc,docx,odt',
        ]);

        if (! $svc->configurat()) {
            return response()->json(['message' => 'El canal certificat no està configurat. Demana a administració que introdueixi les credencials de Mensatek.'], 422);
        }

        $usuaris = User::whereIn('id', $data['destinataris'])->get(['id', 'name', 'email']);
        $senseEmail = $usuaris->filter(fn ($u) => empty($u->email));
        if ($senseEmail->isNotEmpty()) {
            return response()->json(['message' => 'Sense correu a la fitxa: ' . $senseEmail->pluck('name')->implode(', ')], 422);
        }

        $adjunts = [];
        foreach ((array) $request->file('adjunts') as $f) {
            $ruta = $f->store('certified');
            $adjunts[] = ['nom' => $f->getClientOriginalName(), 'path' => $ruta];
        }

        $mail = CertifiedMail::create([
            'sender_id' => $request->user()->id,
            'tipus' => $data['tipus'],
            'expedient_ref' => $data['expedient_ref'] ?? null,
            'assumpte' => $data['assumpte'],
            'cos' => $data['cos'],
            'acceptacio' => (bool) ($data['acceptacio'] ?? false),
            'programat_at' => $data['programat_at'] ?? null,
            'adjunts_json' => $adjunts ?: null,
        ]);
        foreach ($usuaris as $u) {
            $mail->recipients()->create(['user_id' => $u->id, 'email' => $u->email, 'nom' => $u->name]);
        }
        $mail->load('recipients');

        $r = $svc->enviar($mail);
        if (! $r['ok']) {
            $mail->update(['estat_global' => 'error', 'error_txt' => mb_substr($r['error'], 0, 250)]);

            return response()->json(['message' => $r['error']], 424);
        }
        $mail->update(['estat_global' => $mail->programat_at ? 'programat' : 'enviat']);

        return response()->json(['id' => $mail->id, 'enviats' => $r['enviats'], 'credits' => $r['credits']], 201);
    }

    /** POST /api/v1/certified-mails/{mail}/refresh — actualitza l'estat de cada destinatari. */
    public function refresh(CertifiedMail $mail, MensatekCertificat $svc)
    {
        foreach ($mail->recipients as $rec) {
            if (! $rec->id_mensaje) {
                continue;
            }
            $e = $svc->estat((int) $rec->id_mensaje);
            if ($e !== null) {
                $rec->update(['estat' => $e['estat'], 'estat_txt' => $e['estat_txt'], 'estat_at' => now()]);
            }
        }

        return response()->json(['mail' => $mail->fresh('recipients')]);
    }

    /** GET /api/v1/certified-mails/recipients/{recipient}/certificate — ZIP del certificat. */
    public function certificate(int $recipientId, MensatekCertificat $svc)
    {
        $rec = \App\Models\CertifiedMailRecipient::findOrFail($recipientId);
        abort_if(! $rec->id_mensaje, 422, 'Aquest destinatari encara no té identificador de missatge.');
        $zip = $svc->certificat((int) $rec->id_mensaje);
        abort_if($zip === null, 502, 'Mensatek no ha retornat el certificat (potser encara no està generat).');

        return response($zip, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="certificat-' . $rec->id_mensaje . '.zip"',
        ]);
    }

    /** POST /api/v1/certified-mails/{mail}/cancel — cancel·la un enviament programat. */
    public function cancel(CertifiedMail $mail, MensatekCertificat $svc)
    {
        abort_if($mail->estat_global !== 'programat', 422, 'Només es pot cancel·lar un enviament programat.');
        $tots = true;
        foreach ($mail->recipients as $rec) {
            if ($rec->id_mensaje && ! $svc->cancellar((int) $rec->id_mensaje)) {
                $tots = false;
            }
        }
        if ($tots) {
            $mail->update(['estat_global' => 'cancellat']);
        }

        return response()->json(['ok' => $tots, 'mail' => $mail->fresh('recipients')]);
    }

    /** GET /api/v1/certified-mails/settings — configuració (sense exposar el token). */
    public function settingsShow()
    {
        $s = MensatekSetting::getSettings();

        return response()->json([
            'usuari_api' => $s->usuari_api,
            'has_token' => ! empty($s->api_token),
            'remitent' => $s->remitent,
            'last_test_at' => $s->last_test_at,
            'last_test_status' => $s->last_test_status,
        ]);
    }

    /** PUT /api/v1/certified-mails/settings — desa credencials i comprova el saldo. */
    public function settingsUpdate(Request $request, MensatekCertificat $svc)
    {
        $data = $request->validate([
            'usuari_api' => 'required|string|max:120',
            'api_token' => 'nullable|string|max:250',
            'remitent' => 'required|email',
        ]);
        $s = MensatekSetting::getSettings();
        $s->usuari_api = $data['usuari_api'];
        if (! empty($data['api_token'])) {
            $s->api_token = $data['api_token'];
        }
        $s->remitent = $data['remitent'];
        $s->save();

        $credits = (new MensatekCertificat)->credits();
        $s->update([
            'last_test_at' => now(),
            'last_test_status' => $credits !== null ? "ok · $credits crèdits" : 'error de connexió o credencials',
        ]);

        return response()->json(['ok' => $credits !== null, 'credits' => $credits]);
    }
}
