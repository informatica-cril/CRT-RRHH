<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChatSetting;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatAlert;
use App\Models\ChatPolicyAcceptance;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    use \App\Http\Controllers\Concerns\AuthorizesOwnership;

    /** Rols que veuen la configuració del xat SENCERA (inclou la vigilància forense). */
    private const ROLS_GESTIO = ['admin', 'coordinator'];

    /**
     * Únic camp de la configuració de xat que surt cap al treballador: el text de
     * la política que ha d'acceptar (src/views/ChatView.vue).
     *
     * ALLOWLIST deliberada. 'forensic_keywords' NO pot sortir mai per aquí:
     * publicar les paraules clau vigilades buida de sentit la vigilància.
     */
    private const CAMPS_POLITICA = ['chat_policy_text'];

    /**
     * Autorització de conversa: VIGILÀNCIA (admin/coordinació) O membre de la conversa.
     * Base contra l'IDOR (llegir/escriure en converses alienes).
     *
     * 'hr' NO hi entra. isStaff() l'hi feia entrar i RRHH podia llegir converses
     * privades de les quals no és part, just el contrari del que diu el disseny
     * (vegeu routes/api.php: la supervisió del xat li està expressament negada).
     */
    private function guardParticipant(Request $request, $convId): void
    {
        $user = $request->user();
        if ($user && $user->canManageZonesAndSchedules()) {
            return;
        }
        $esMembre = $user && ChatConversation::where('id', $convId)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->exists();
        abort_unless($esMembre, 403, 'No autoritzat');
    }

    // ── Settings & Policy ──
    
    /**
     * GET /api/v1/chat/settings
     *
     * Gestió (admin/coordinator) → configuració sencera, keywords forenses incloses.
     * La resta (worker, hr) → només el text de la política. No es pot negar del tot:
     * el treballador ha de poder LLEGIR la política abans d'acceptar-la.
     */
    public function getSettings(Request $request)
    {
        $settings = ChatSetting::first();

        // El filtre s'aplica també quan encara no hi ha fila configurada: si no,
        // el valor per defecte de forensic_keywords s'escaparia per la porta del fons.
        $payload = $settings ? $settings->toArray() : [
            'forensic_keywords' => ['acoso', 'amenaza', 'robo', 'demanda'],
            'chat_policy_text' => 'POLÍTICA D\'ÚS DEL XAT CORPORATIU — CRT',
            'require_policy_acceptance' => true,
        ];

        if (in_array($request->user()->role, self::ROLS_GESTIO, true)) {
            return response()->json($payload);
        }

        return response()->json(Arr::only($payload, self::CAMPS_POLITICA));
    }

    public function saveSettings(Request $request)
    {
        $setting = ChatSetting::first() ?? new ChatSetting();
        $setting->fill($request->all());
        
        if (!$setting->chat_policy_text) {
            $setting->chat_policy_text = 'POLÍTICA D\'ÚS DEL XAT CORPORATIU — CRT...';
        }
        if (!is_array($setting->forensic_keywords)) {
            $setting->forensic_keywords = [];
        }
        
        $setting->save();
        return response()->json($setting);
    }

    public function hasPolicyAccepted($userId)
    {
        $accepted = ChatPolicyAcceptance::where('user_id', $userId)->exists();
        return response()->json(['accepted' => $accepted]);
    }

    /**
     * L'acceptació de la política de xat és una CONSTÀNCIA amb valor probatori: la
     * identitat surt SEMPRE del token, mai del cos de la petició (abans s'agafava
     * $request->user_id i qualsevol podia acceptar-la en nom d'un altre).
     */
    public function acceptPolicy(Request $request)
    {
        ChatPolicyAcceptance::firstOrCreate([
            'user_id' => $request->user()->id
        ], [
            'accepted_at' => now()
        ]);
        return response()->json(['success' => true]);
    }

    // ── Presence ──

    /** La presència és pròpia: identitat del token, no del cos (evita suplantació). */
    public function setUserPresence(Request $request)
    {
        $request->validate(['status' => 'required']);
        $user = $request->user();
        $user->chat_status = $request->status;
        $user->last_chat_heartbeat = now();
        $user->save();
        return response()->json(['success' => true]);
    }

    /* Qui no publica presència. La disponibilitat de Direcció no és dada de servei
       per a la resta de la plantilla; segueix veient la dels altres, perquè ha de
       saber a qui pot escriure ara. */
    private const PRESENCIA_ROLS_OCULTS = ['admin'];

    /* Finestra del batec. Ha de ser més gran que el període de batec de la pantalla
       (30 s) o la gent parpellejaria entre verd i gris. */
    private const PRESENCIA_FINESTRA_MIN = 3;

    public function getUserPresence($userId)
    {
        $user = User::find($userId);
        // Un identificador que no existeix retornava un error del servidor.
        if (! $user) return response()->json(['status' => 'offline']);

        return response()->json(['status' => $this->estatPublicable($user)]);
    }

    /**
     * GET /api/v1/chat/presence
     *
     * Presència de tothom en UNA crida. La pantalla en demanava una per persona i
     * ho repetia a cada refresc, de manera que amb la plantilla sencera eren desenes
     * de peticions cada mig minut per a una cosa que ni tan sols es pintava.
     *
     * Retorna només connectat sí/no: l'hora exacta de l'última activitat d'un company
     * no li cal a ningú per escriure-li, i sí que és una dada de control.
     */
    public function presenceAll()
    {
        $usuaris = User::query()
            ->select(['id', 'role', 'chat_status', 'last_chat_heartbeat'])
            ->where('active', true)
            ->get();

        return response()->json(
            $usuaris->mapWithKeys(fn($u) => [$u->id => $this->estatPublicable($u) === 'online'])
        );
    }

    /** L'estat que es pot ensenyar a un tercer. */
    private function estatPublicable(User $u): string
    {
        if (in_array($u->role, self::PRESENCIA_ROLS_OCULTS, true)) return 'offline';

        if ($u->chat_status === 'offline') return 'offline';

        /* El batec mana sobre l'estat declarat: tancar la pestanya no envia cap
           «offline», i sense això qui marxava a casa es quedava en verd per sempre.
           El camp no està tipat com a data al model i arriba com a text. */
        $batec = $u->last_chat_heartbeat;
        $ts = $batec instanceof \DateTimeInterface ? $batec->getTimestamp() : strtotime((string) $batec);
        if (! $ts || $ts < now()->subMinutes(self::PRESENCIA_FINESTRA_MIN)->getTimestamp()) return 'offline';

        return $u->chat_status === 'paused' ? 'paused' : 'online';
    }

    /** Batec del propi usuari: identitat del token, no del cos. */
    public function heartbeat(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->last_chat_heartbeat = now();
            // Auto online si estaba offline/ausente? opcional.
            $user->save();
        }
        return response()->json(['success' => true]);
    }

    // ── Conversations ──

    public function getAllConversations()
    {
        $conversations = ChatConversation::with('users:id,name')->get();
        foreach ($conversations as $conv) {
            $conv->members = $conv->users->pluck('id');
            unset($conv->users);
        }
        return response()->json($conversations);
    }

    public function getConversationsForUser(Request $request)
    {
        $userId = $request->query('user_id');
        if (!$userId) return response()->json([]);
        // Només les converses PRÒPIES (o vigilància: admin/coordinació, mai 'hr').
        $me = $request->user();
        abort_unless($me && ($me->canManageZonesAndSchedules() || (int) $me->id === (int) $userId),
            403, 'No autoritzat');

        $user = User::find($userId);
        if (!$user) return response()->json([]);

        $conversations = $user->chatConversations()->with('users:id,name,chat_status')->get();
        // Append members as simple array to match frontend expects: conv.members = [id1, id2...]
        foreach ($conversations as $conv) {
            $conv->members = $conv->users->pluck('id');
            unset($conv->users);
        }

        return response()->json($conversations);
    }

    public function getConversation(Request $request, $id)
    {
        $this->guardParticipant($request, $id);
        $conv = ChatConversation::with('users:id,name')->findOrFail($id);
        $conv->members = $conv->users->pluck('id');
        unset($conv->users);
        return response()->json($conv);
    }

    public function addConversation(Request $request)
    {
        $request->validate([
            'type' => 'required',
            'name' => 'nullable|string',
            'created_by' => 'required|exists:users,id'
        ]);

        $conv = ChatConversation::create([
            'type' => $request->type,
            'name' => $request->name,
            'created_by' => $request->created_by,
            'pinned' => $request->pinned ?? false
        ]);

        if ($request->has('members')) {
            $conv->users()->sync($request->members);
        } else {
            // Ensure creator is a member
            $conv->users()->sync([$request->created_by]);
        }

        $conv->members = $conv->users->pluck('id');
        return response()->json($conv);
    }

    public function getOrCreateDm(Request $request)
    {
        $u1 = $request->user1;
        $u2 = $request->user2;
        // Un no-staff només pot obrir un DM en què ell mateix participa.
        $me = $request->user();
        abort_unless($me && ($me->isStaff() || (int) $me->id === (int) $u1 || (int) $me->id === (int) $u2),
            403, 'No autoritzat');

        // Find existing DM via raw query or eloquent
        $conversation = ChatConversation::where('type', 'dm')
            ->whereHas('users', function ($q) use ($u1) { $q->where('users.id', $u1); })
            ->whereHas('users', function ($q) use ($u2) { $q->where('users.id', $u2); })
            ->first();

        if (!$conversation) {
            $conversation = ChatConversation::create([
                'type' => 'dm',
                'created_by' => $u1
            ]);
            $conversation->users()->sync([$u1, $u2]);
        }

        $conversation->members = $conversation->users()->pluck('users.id');
        return response()->json($conversation);
    }

    // ── Messages ──

    public function getMessages(Request $request, $convId)
    {
        $this->guardParticipant($request, $convId);
        // Limitar a los últimos 200 mensajes para evitar respuestas enormes.
        // Se ordena desc, se limita y luego se reordena asc para el frontend.
        $messages = ChatMessage::where('conversation_id', $convId)
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get()
            ->sortBy('created_at')
            ->values();

        return response()->json($messages);
    }

    public function addMessage(Request $request)
    {
        $request->validate([
            'conversation_id' => 'required|exists:chat_conversations,id',
            'sender_id' => 'required|exists:users,id',
            'content' => 'nullable|string'
        ]);

        // ANTI-IDOR: no es pot enviar en nom d'un altre ni en una conversa aliena.
        // El remitent és SEMPRE l'usuari autenticat (staff inclòs).
        abort_unless((int) $request->sender_id === (int) $request->user()->id, 403, 'No autoritzat');
        $this->guardParticipant($request, $request->conversation_id);

        $msg = ChatMessage::create($request->only(['conversation_id', 'sender_id', 'content']));

        // Autoevaluación Forense
        //
        // TRACTAMENT DECLARAT (14-08-2026). Aquesta comparació amb la llista de paraules i l'alerta
        // amb extracte que en surt són un tractament de dades personals i, fins ara, no constaven
        // en cap text acceptat per la persona treballadora. Queden declarats a:
        //   · documents · "Política de Protecció de Dades (RGPD) — v2.0", punt 4 (text signable);
        //   · compliance_documents · info_art90 v3.0, punts 4 i 7 (art. 90 LOPDGDD);
        //   · chat_settings.chat_policy_text, punts 2 a 4 (política del xat).
        // La lògica NO s'ha tocat. Si algun dia canvia (nou abast, nova longitud d'extracte, nous
        // perfils amb accés), els tres textos s'han d'actualitzar amb ella o el tractament torna a
        // quedar sense informació prèvia.
        $settings = ChatSetting::first();
        if ($settings && $settings->forensic_keywords && $msg->content) {
            $keywords = is_array($settings->forensic_keywords) ? $settings->forensic_keywords : json_decode($settings->forensic_keywords, true);
            foreach ($keywords as $kw) {
                if (stripos($msg->content, $kw) !== false) {
                    ChatAlert::create([
                        'message_id' => $msg->id,
                        'keyword' => $kw,
                        'reviewed' => false,
                        'content_excerpt' => substr($msg->content, 0, 100)
                    ]);
                    break;
                }
            }
        }

        return response()->json($msg);
    }

    public function getLastMessage(Request $request, $convId)
    {
        $this->guardParticipant($request, $convId);
        $msg = ChatMessage::where('conversation_id', $convId)
            ->orderBy('created_at', 'desc')
            ->first();
        return response()->json($msg);
    }

    public function getUnreadCount(Request $request, $convId, $userId)
    {
        $this->guardParticipant($request, $convId);
        // El comptador és del titular: no es demana el d'un altre.
        $userId = $request->user()->id;
        // Contamos los mensajes de la conversación creados después del último mensaje leído guardado en chat_message_reads
        $lastReadMsgId = DB::table('chat_message_reads')
            ->where('user_id', $userId)
            // No podemos asociar convId directamente a chat_message_reads sin join, pero simplifiquemos:
            ->join('chat_messages', 'chat_message_reads.message_id', '=', 'chat_messages.id')
            ->where('chat_messages.conversation_id', $convId)
            ->max('chat_message_reads.message_id') ?? 0;

        $count = ChatMessage::where('conversation_id', $convId)
            ->where('id', '>', $lastReadMsgId)
            ->where('sender_id', '!=', $userId)
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markMessagesRead(Request $request, $convId)
    {
        $this->guardParticipant($request, $convId);
        // La marca de llegit és del qui llegeix: identitat del token, no del cos.
        $userId = $request->user()->id;
        $lastMsg = ChatMessage::where('conversation_id', $convId)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($lastMsg) {
            DB::table('chat_message_reads')->updateOrInsert(
                ['user_id' => $userId, 'message_id' => $lastMsg->id],
                ['created_at' => now(), 'updated_at' => now()]
            );
            // Optionally clear old reads to keep table small
        }

        return response()->json(['success' => true]);
    }

    public function searchMessages(Request $request)
    {
        $q = $request->query('q');
        if (!$q) {
            return response()->json([]);
        }

        // Limitar resultados para evitar escaneos completos de la tabla.
        $messages = ChatMessage::where('content', 'LIKE', "%{$q}%")
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($messages);
    }

    public function getTotalUnreadCount($userId)
    {
        $convIds = DB::table('conversation_user')
            ->where('user_id', $userId)
            ->pluck('conversation_id');

        if ($convIds->isEmpty()) {
            return response()->json(['count' => 0]);
        }

        $total = (int) DB::table('chat_messages as cm')
            ->whereIn('cm.conversation_id', $convIds)
            ->where('cm.sender_id', '!=', $userId)
            ->whereRaw('cm.id > COALESCE((
                SELECT MAX(r.message_id) FROM chat_message_reads r
                INNER JOIN chat_messages m ON r.message_id = m.id
                WHERE r.user_id = ? AND m.conversation_id = cm.conversation_id
            ), 0)', [$userId])
            ->count();

        return response()->json(['count' => $total]);
    }

    // ── Alerts ──

    public function getAlerts()
    {
        $alerts = ChatAlert::with(['message', 'message.conversation', 'message.sender:id,name'])->get();
        return response()->json($alerts);
    }

    public function getUnreviewedAlerts()
    {
        $alerts = ChatAlert::with(['message', 'message.conversation', 'message.sender:id,name'])
            ->where('reviewed', false)
            ->get();
        return response()->json($alerts);
    }

    public function reviewAlert(Request $request, $id)
    {
        $alert = ChatAlert::findOrFail($id);
        $alert->reviewed = true;
        // Se podría guardar user_id de quien revisó
        $alert->save();
        return response()->json(['success' => true]);
    }
}
