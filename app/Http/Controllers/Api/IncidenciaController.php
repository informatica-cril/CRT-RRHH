<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incidencia;
use App\Models\IncidenciaMissatge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Incidències: qualsevol persona amb accés n'envia (a RRHH o a Informàtica) i en segueix la conversa.
 * Les de RRHH les veuen, responen i resolen RRHH i Superior; les d'Informàtica, l'equip d'informàtica
 * (users.es_informatica) i Superior. Tot passa dins de l'app (notificacions i comptadors), sense correu.
 */
class IncidenciaController extends Controller
{
    private const RELACIONS = ['user:id,name,email', 'resolutor:id,name', 'missatges.user:id,name,role'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'tipus' => ['required', Rule::in(Incidencia::TIPUS)],
            'destinatari' => ['nullable', Rule::in(Incidencia::DESTINATARIS)],
            'assumpte' => 'required|string|max:150',
            'descripcio' => 'required|string|min:10|max:5000',
        ]);
        $inc = Incidencia::create(array_merge($data, ['destinatari' => $data['destinatari'] ?? 'rrhh']) + ['user_id' => $request->user()->id, 'estat' => 'oberta', 'estat_gestio' => 'nova', 'vist_treballador_at' => now()]);

        $qui = $inc->destinatari === 'informatica' ? 'Informàtica' : 'RRHH';

        return response()->json(['id' => $inc->id, 'missatge' => "Incidència enviada. {$qui} se n'encarregarà."], 201);
    }

    /** Pot gestionar (veure, respondre, resoldre) aquesta incidència? */
    public static function gestiona(User $u, Incidencia $i): bool
    {
        if ($u->role === 'admin') return true;
        if ($i->destinatari === 'informatica') return (bool) $u->es_informatica;

        return $u->role === 'hr';
    }

    /** Destinataris que una persona gestiona (cap = no és de gestió). */
    public static function destinatarisDe(User $u): array
    {
        if ($u->role === 'admin') return Incidencia::DESTINATARIS;
        $d = [];
        if ($u->role === 'hr') $d[] = 'rrhh';
        if ($u->es_informatica) $d[] = 'informatica';

        return $d;
    }

    /** Gestió: les del seu àmbit (o per estat). */
    public function index(Request $request)
    {
        $destinataris = self::destinatarisDe($request->user());
        abort_if($destinataris === [], 403, 'No autoritzat');
        $q = Incidencia::with(self::RELACIONS)->whereIn('destinatari', $destinataris)->latest();
        if (in_array($request->query('estat'), ['oberta', 'resolta'], true)) $q->where('estat', $request->query('estat'));

        return response()->json($q->limit(500)->get());
    }

    /** Les del treballador, amb la conversa i si hi ha respostes que encara no ha vist. */
    public function meves(Request $request)
    {
        $llista = Incidencia::with(self::RELACIONS)->where('user_id', $request->user()->id)->latest()->limit(100)->get();
        // L'estat de gestió és intern de RRHH: el treballador només veu oberta / resolta.
        $llista->each(fn ($i) => $i->makeHidden('estat_gestio')->setAttribute('resposta_nova', $this->respostaNova($i)));

        return response()->json($llista);
    }

    /** Comptador per al menú del treballador: incidències amb resposta que no ha vist. */
    public function noLlegides(Request $request)
    {
        $n = Incidencia::with('missatges')->where('user_id', $request->user()->id)->get()
            ->filter(fn ($i) => $this->respostaNova($i))->count();

        return response()->json(['n' => $n]);
    }

    /** El treballador ha obert les seves incidències: les respostes deixen de ser noves. */
    public function marcaVistes(Request $request)
    {
        Incidencia::where('user_id', $request->user()->id)->update(['vist_treballador_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function missatge(Request $request, Incidencia $incidencia)
    {
        $jo = $request->user();
        $gestio = self::gestiona($jo, $incidencia);
        abort_unless($gestio || $incidencia->user_id === $jo->id, 403);
        $data = $request->validate(['text' => 'required|string|min:2|max:5000']);

        IncidenciaMissatge::create(['incidencia_id' => $incidencia->id, 'user_id' => $jo->id, 'text' => trim($data['text'])]);
        $canvis = ['updated_at' => now()];
        if ($incidencia->user_id === $jo->id) {
            $canvis['vist_treballador_at'] = now();
            // Si contesta una incidència tancada o que esperava la seva resposta, torna a ser cosa de RRHH.
            if ($incidencia->estat === 'resolta') $canvis += ['estat' => 'oberta', 'resolta_per' => null, 'resolta_at' => null];
            if ($incidencia->estat === 'resolta' || $incidencia->estat_gestio === 'esperant') $canvis['estat_gestio'] = 'en_curs';
        } elseif ($incidencia->estat_gestio === 'nova') {
            $canvis['estat_gestio'] = 'en_curs';   // RRHH hi ha respost: ja s'hi està treballant
        }
        $incidencia->forceFill($canvis)->save();

        $fresca = $incidencia->fresh(self::RELACIONS);
        if (! $gestio) $fresca->makeHidden('estat_gestio');

        return response()->json($fresca);
    }

    public function resol(Request $request, Incidencia $incidencia)
    {
        abort_unless(self::gestiona($request->user(), $incidencia), 403);
        $data = $request->validate(['nota' => 'nullable|string|max:2000']);
        $incidencia->update(['estat' => 'resolta', 'estat_gestio' => 'resolta', 'resolta_per' => $request->user()->id, 'resolta_at' => now(),
            'nota_resolucio' => $data['nota'] ?? null]);

        return response()->json($incidencia->fresh(self::RELACIONS));
    }

    public function reobre(Request $request, Incidencia $incidencia)
    {
        abort_unless(self::gestiona($request->user(), $incidencia), 403);
        $incidencia->update(['estat' => 'oberta', 'estat_gestio' => 'en_curs', 'resolta_per' => null, 'resolta_at' => null]);

        return response()->json($incidencia->fresh(self::RELACIONS));
    }

    /**
     * Passa la incidència a l'altre àmbit (RRHH <-> Informàtica) quan s'ha enviat a qui no tocava. Torna a ser «nova»
     * per a qui la rep, perquè li surti als avisos. Qui la redirigeix pot deixar de veure-la (si només gestiona l'àmbit d'origen).
     */
    public function redirigeix(Request $request, Incidencia $incidencia)
    {
        abort_unless(self::gestiona($request->user(), $incidencia), 403);
        $data = $request->validate(['destinatari' => ['required', Rule::in(Incidencia::DESTINATARIS)]]);
        abort_if($incidencia->destinatari === $data['destinatari'], 422, 'La incidència ja és d\'aquest àmbit.');

        $abans = $incidencia->destinatari;
        $incidencia->forceFill(['destinatari' => $data['destinatari'], 'estat' => 'oberta', 'estat_gestio' => 'nova',
            'resolta_per' => null, 'resolta_at' => null, 'updated_at' => now()])->save();
        \Illuminate\Support\Facades\Log::info('Incidència redirigida', ['id' => $incidencia->id, 'de' => $abans, 'a' => $data['destinatari'], 'per' => $request->user()->id]);

        return response()->json($incidencia->fresh(self::RELACIONS));
    }

    /** Canvi d'estat de gestió. Resolta i descartada la tanquen (el treballador la veu «Resolta»). */
    public function estat(Request $request, Incidencia $incidencia)
    {
        abort_unless(self::gestiona($request->user(), $incidencia), 403);
        $data = $request->validate(['estat' => ['required', Rule::in(Incidencia::ESTATS_GESTIO)]]);
        $tanca = in_array($data['estat'], Incidencia::ESTATS_TANCATS, true);
        $incidencia->update($tanca
            ? ['estat_gestio' => $data['estat'], 'estat' => 'resolta', 'resolta_per' => $incidencia->resolta_per ?? $request->user()->id,
               'resolta_at' => $incidencia->resolta_at ?? now()]
            : ['estat_gestio' => $data['estat'], 'estat' => 'oberta', 'resolta_per' => null, 'resolta_at' => null]);

        return response()->json($incidencia->fresh(self::RELACIONS));
    }

    /** Hi ha un missatge de gestió (o la resolució) posterior a l'última vegada que el treballador ho va veure? */
    private function respostaNova(Incidencia $i): bool
    {
        $vist = $i->vist_treballador_at;
        $ultim = $i->missatges->where('user_id', '!=', $i->user_id)->max('created_at');
        if ($i->resolta_at && (! $ultim || $i->resolta_at->gt($ultim))) $ultim = $i->resolta_at;

        return $ultim && (! $vist || $ultim->gt($vist));
    }
}
