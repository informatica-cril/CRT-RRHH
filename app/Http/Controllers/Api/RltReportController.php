<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\IaLocal;
use App\Support\RltFets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RltReportController extends Controller
{
    public function tipus()
    {
        return response()->json([
            'tipus' => RltFets::TIPUS,
            'ia_disponible' => (new IaLocal())->disponible(),
        ]);
    }

    public function index(Request $request)
    {
        $q = DB::table('rlt_reports as r')
            ->leftJoin('users as g', 'g.id', '=', 'r.generat_per')
            ->leftJoin('users as s', 's.id', '=', 'r.signat_per')
            ->orderByDesc('r.id')
            ->limit(40)
            ->get([
                'r.id', 'r.tipus', 'r.periode_desde', 'r.periode_fins', 'r.estat',
                'r.generat_ts', 'r.signat_ts', 'r.lliurat_ts', 'r.lliurat_a',
                'g.name as generat_per', 's.name as signat_per',
            ]);

        return response()->json(['informes' => $q]);
    }

    public function fets(Request $request)
    {
        $data = $request->validate([
            'tipus' => 'required|string|in:' . implode(',', array_keys(RltFets::TIPUS)),
            'desde' => 'required|date_format:Y-m-d',
            'fins' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);

        return response()->json(['fets' => RltFets::per($data['tipus'], $data['desde'], $data['fins'])]);
    }

    public function show(Request $request, int $id)
    {
        $r = DB::table('rlt_reports')->find($id);
        abort_if(! $r, 404);
        $r->fets = json_decode((string) $r->fets, true);
        $r->serveis = json_decode((string) $r->serveis, true);

        return response()->json($r);
    }

    public function document(Request $request, int $id)
    {
        $r = DB::table('rlt_reports')->find($id);
        abort_if(! $r, 404);

        $tipus = RltFets::TIPUS[$r->tipus] ?? null;
        $signant = null;
        if ($r->signat_per) {
            $signant = optional(\App\Models\User::find($r->signat_per))->name;
        }

        $html = \App\Support\DocumentCorporatiu::html([
            'referencia' => 'Escrit a la representació legal · ref. RLT-' . str_pad((string) $r->id, 5, '0', STR_PAD_LEFT),
            'titol' => $tipus['nom'] ?? $r->tipus,
            'subtitol' => $tipus['articles'] ?? '',
            'meta' => array_filter([
                'Període' => $r->periode_desde . ' a ' . $r->periode_fins,
                'Estat' => $r->estat,
                'Tràmit' => ($tipus['natura'] ?? '') === 'informe_previ'
                    ? 'Informe previ · 15 dies hàbils (art. 55.K.7)' : 'Informació',
                'Lliurat a' => $r->lliurat_a,
            ]),
            'cos' => (string) $r->text,
            'signant' => $signant,
            'signat_ts' => $r->signat_ts ? substr((string) $r->signat_ts, 0, 16) : null,
            'hash' => $r->text_hash,
            'peu' => 'CRT · CRT RRHH · document generat el ' . now()->format('d/m/Y H:i')
                   . ($r->estat === 'esborrany' ? ' · ESBORRANY PENDENT DE REVISIÓ I SIGNATURA' : ''),
        ]);

        return response($html)->header('Content-Type', 'text/html; charset=utf-8');
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'tipus' => 'required|string|in:' . implode(',', array_keys(RltFets::TIPUS)),
            'desde' => 'required|date_format:Y-m-d',
            'fins' => 'required|date_format:Y-m-d|after_or_equal:desde',
        ]);

        $ia = new IaLocal();
        if (! $ia->disponible()) {
            return response()->json(['message' => 'El servidor d\'IA no respon. Els fets es poden consultar '
                . 'igualment i l\'escrit es pot redactar a mà.'], 503);
        }

        $fets = RltFets::per($data['tipus'], $data['desde'], $data['fins']);
        $r = $ia->xat(self::sistema($data['tipus']), self::usuari($fets));
        if (! $r['ok']) {
            return response()->json(['message' => 'La IA no ha tornat text: ' . ($r['error'] ?? 'resposta buida')], 424);
        }

        $id = DB::table('rlt_reports')->insertGetId([
            'tipus' => $data['tipus'],
            'periode_desde' => $data['desde'],
            'periode_fins' => $data['fins'],
            'serveis' => json_encode($fets['serveis'] ?? [], JSON_UNESCAPED_UNICODE),
            'fets' => json_encode($fets, JSON_UNESCAPED_UNICODE),
            'text' => $r['text'],
            'estat' => 'esborrany',
            'generat_per' => $request->user()->id,
            'generat_ts' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'RLT_INFORME_GENERAT',
            'entity_type' => 'rlt_report',
            'entity_id' => $id,
            'description' => "Esborrany d'informe a la RLT ({$data['tipus']}) generat amb {$r['model']} en {$r['ms']} ms",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true, 'id' => $id, 'ms' => $r['ms']]);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate(['text' => 'required|string']);
        $r = DB::table('rlt_reports')->find($id);
        abort_if(! $r, 404);
        if ($r->estat !== 'esborrany') {
            return response()->json(['message' => 'Un escrit signat o lliurat ja no es pot modificar.'], 422);
        }

        DB::table('rlt_reports')->where('id', $id)->update([
            'text' => $data['text'],
            'revisat_per' => $request->user()->id,
            'revisat_ts' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function sign(Request $request, int $id)
    {
        $r = DB::table('rlt_reports')->find($id);
        abort_if(! $r, 404);
        if ($r->estat !== 'esborrany') {
            return response()->json(['message' => 'Aquest escrit ja està signat.'], 422);
        }
        if (! in_array($request->user()->role, ['admin', 'hr'], true)) {
            return response()->json(['message' => 'Només Direcció o RRHH poden signar un escrit a la representació.'], 403);
        }
        if (trim((string) $r->text) === '') {
            return response()->json(['message' => 'No es pot signar un escrit buit.'], 422);
        }

        $textHash = hash('sha256', (string) $r->text);
        $payload = json_encode([
            'informe' => $id, 'tipus' => $r->tipus,
            'periode' => [$r->periode_desde, $r->periode_fins],
            'text_sha256' => $textHash,
            'signant' => $request->user()->name,
            'ts' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE);

        DB::table('rlt_reports')->where('id', $id)->update([
            'estat' => 'signat',
            'signat_per' => $request->user()->id,
            'signat_ts' => now(),
            'text_hash' => $textHash,
            'signatura_hash' => hash('sha256', $payload),
            'updated_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'RLT_INFORME_SIGNAT',
            'entity_type' => 'rlt_report',
            'entity_id' => $id,
            'description' => "Escrit a la RLT #{$id} ({$r->tipus}) signat per {$request->user()->name}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function deliver(Request $request, int $id)
    {
        $data = $request->validate([
            'lliurat_a' => 'required|string|min:3|max:160',
            'nota' => 'required|string|min:10|max:500',
        ]);
        $r = DB::table('rlt_reports')->find($id);
        abort_if(! $r, 404);
        if ($r->estat !== 'signat') {
            return response()->json(['message' => 'Primer s\'ha de signar l\'escrit; després es registra el lliurament.'], 422);
        }

        DB::table('rlt_reports')->where('id', $id)->update([
            'estat' => 'lliurat',
            'lliurat_ts' => now(),
            'lliurat_a' => $data['lliurat_a'],
            'nota_lliurament' => $data['nota'],
            'updated_at' => now(),
        ]);

        if ($r->tipus === 'sancions') {
            $fets = json_decode((string) $r->fets, true);
            if (! empty($fets['sancions'])) {
                \App\Models\DisciplinaryCase::where('gravetat', 'molt_greu')
                    ->whereNull('rlt_informat_ts')
                    ->whereBetween('updated_at', [$r->periode_desde . ' 00:00:00', $r->periode_fins . ' 23:59:59'])
                    ->update(['rlt_informat_ts' => now()]);
            }
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'RLT_INFORME_LLIURAT',
            'entity_type' => 'rlt_report',
            'entity_id' => $id,
            'description' => "Escrit a la RLT #{$id} lliurat a {$data['lliurat_a']}: {$data['nota']}",
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['ok' => true]);
    }

    private static function sistema(string $tipus): string
    {
        $t = RltFets::TIPUS[$tipus];
        $previ = $t['natura'] === 'informe_previ';

        return "Ets el redactor de comunicacions laborals de CRT, empresa de rehabilitació de Catalunya amb "
            . "serveis de domiciliària, logopèdia i ambulatori. Redactes en CATALÀ, en registre formal d'escrit "
            . "adreçat a la representació legal de les persones treballadores.\n\n"
            . "REGLA ABSOLUTA: només pots escriure el que consti als FETS. No afegeixis cap xifra, paràmetre ni "
            . "afirmació que no hi sigui. Si als fets hi ha un avís que falta informació, recull-lo com a punt "
            . "pendent en comptes d'inventar-la.\n\n"
            . "OBJECTE: {$t['nom']}. Base: {$t['articles']}. Periodicitat: {$t['periodicitat']}\n\n"
            . ($previ
                ? "Aquest escrit obre el tràmit d'INFORME PREVI: la representació ha de poder examinar-ho i emetre "
                . "informe ABANS que la mesura s'executi, i té 15 dies hàbils per fer-ho (article 55.K.7). Digues-ho "
                . "expressament i no donis per fet el tràmit.\n\n"
                : "Aquest escrit compleix un deure d'INFORMACIÓ, no obre cap consulta prèvia. No diguis que la "
                . "representació ha d'emetre informe.\n\n")
            . "TO: informatiu i complet, sense autobombo ni justificacions defensives. No amaguis cap dada ni la "
            . "suavitzis. Si una xifra és dolenta per a l'empresa, es diu igual.\n\n"
            . "PROTECCIÓ DE DADES: no identifiquis persones concretes encara que apareguin als fets; agrega per "
            . "grups. L'excepció és si els fets diuen expressament que cal el detall nominal.\n\n"
            . "ESTRUCTURA: encapçalament amb objecte i període; el cos amb les dades en llistes, no en prosa; un "
            . "apartat final amb el que queda pendent de completar i les dades de contacte a omplir. Acaba SEMPRE "
            . "amb una línia dient que és un esborrany pendent de revisió i signatura per la Direcció.\n\n"
            . "No usis markdown ni asteriscos: text pla, títols en majúscules i guions per a les llistes.";
    }

    private static function usuari(array $fets): string
    {
        return "FETS (no n'afegeixis cap altre):\n\n" . json_encode($fets, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
