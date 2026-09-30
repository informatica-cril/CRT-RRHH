<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Conciliació d'identitats: comptes HISTÒRICS de domi (creats a mà, sense DNI) ↔ usuaris de RRHH.
 *
 * El sistema SUGGEREIX parelles per semblança de nom; **una persona confirma** cada vinculació. Mai
 * s'auto-vincula res per semblança: un fals positiu aquí significaria atribuir l'activitat (i, en
 * última instància, faltes disciplinàries) a la persona equivocada.
 */
class ConciliacioController extends Controller
{
    /** Parelles candidates: comptes domi sense vincle × treballadors RRHH sense domi_username. */
    public function index()
    {
        $comptes = $this->comptesDomi();
        if (! is_array($comptes)) {
            // 424 (Failed Dependency), no 502: Nginx intercepta i substitueix
            // qualsevol resposta 502 -encara que la generi la propia app- per
            // la seva pagina d'error generica, ocultant el JSON real.
            return response()->json(['ok' => false, 'error' => $comptes ?: 'domi no accessible'], 424);
        }

        $workers = User::whereNull('domi_username')
            ->whereIn('role', ['worker'])
            ->where('active', true)
            ->orderBy('name')->get(['id', 'name', 'email', 'dni', 'work_type']);

        $out = [];
        foreach ($comptes as $c) {
            $cands = [];
            foreach ($workers as $w) {
                $score = $this->similitud($c['UserName'], $c['Nombre'] ?? '', $w->name);
                if ($score > 0) {
                    $cands[] = ['user_id' => $w->id, 'name' => $w->name, 'email' => $w->email,
                                'te_dni' => (bool) $w->dni, 'score' => $score];
                }
            }
            usort($cands, fn ($a, $b) => $b['score'] <=> $a['score']);
            $out[] = [
                'username'   => $c['UserName'],
                'nom_domi'   => $c['Nombre'] ?? '',
                'privilegio' => $c['privilegio'] ?? '',
                'te_dni'     => (bool) ($c['te_dni'] ?? false),
                'candidats'  => array_slice($cands, 0, 5),
            ];
        }
        // Primer els que tenen una sola coincidència forta (feina ràpida per a l'usuari).
        usort($out, fn ($a, $b) => (($b['candidats'][0]['score'] ?? 0) <=> ($a['candidats'][0]['score'] ?? 0)));

        return ['ok' => true, 'pendents' => $out, 'treballadors_sense_vincle' => $workers->count()];
    }

    /** Confirma una vinculació (acte humà): desa a RRHH i la propaga a domi. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'username' => 'required|string|max:15',
        ]);
        $user = User::find($data['user_id']);
        if ($user->domi_username && $user->domi_username !== $data['username']) {
            return response()->json(['ok' => false, 'error' => "Aquest usuari ja està vinculat a «{$user->domi_username}»."], 422);
        }
        // Primer domi (pot rebutjar per conflicte); només si accepta, es desa a RRHH.
        $url   = str_replace('rrhh_expedient.php', 'rrhh_conciliacio.php', (string) env('DOMI_EXPEDIENT_URL'));
        $token = (string) env('DOMI_EXPEDIENT_TOKEN');
        try {
            $resp = Http::withToken($token)->timeout(8)
                ->post($url, ['username' => $data['username'], 'rrhh_user_id' => $user->id]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'domi no accessible'], 424);
        }
        $body = $resp->json() ?: [];
        if (! $resp->successful() || empty($body['ok'])) {
            return response()->json(['ok' => false, 'error' => $body['error'] ?? 'domi ha rebutjat la vinculació'], 422);
        }
        $user->update(['domi_username' => $data['username']]);

        return ['ok' => true, 'user_id' => $user->id, 'name' => $user->name, 'username' => $data['username']];
    }

    /** @return array|string llista de comptes o missatge d'error */
    private function comptesDomi()
    {
        $url   = str_replace('rrhh_expedient.php', 'rrhh_conciliacio.php', (string) env('DOMI_EXPEDIENT_URL'));
        $token = (string) env('DOMI_EXPEDIENT_TOKEN');
        if ($token === '') {
            return 'DOMI_EXPEDIENT_TOKEN no configurat';
        }
        try {
            $resp = Http::withToken($token)->timeout(10)->get($url, ['accio' => 'llista']);
        } catch (\Throwable $e) {
            return 'domi no accessible';
        }

        return $resp->ok() ? ($resp->json('comptes') ?? []) : 'error de domi ' . $resp->status();
    }

    /** Normalitza: sense accents, minúscules, només lletres i espais. */
    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s) ?: $s;

        return trim(preg_replace('/[^a-z ]+/', ' ', $s));
    }

    /**
     * Puntuació de semblança entre un compte domi i un nom de RRHH.
     *   100 = inicial del nom + cognom coincideixen (patró «S.Martin» ↔ «Sonia Martin …»)
     *    70 = el cognom del username apareix als cognoms de RRHH (sense quadrar la inicial)
     *    60 = el nom complet de la fitxa de domi coincideix bastant amb el de RRHH
     *     0 = cap indici (no es mostra)
     */
    private function similitud(string $username, string $nomDomi, string $nomRrhh): int
    {
        $toksRrhh = array_values(array_filter(explode(' ', $this->norm($nomRrhh))));
        if (! $toksRrhh) {
            return 0;
        }
        $score = 0;

        // Patró I.Cognom
        if (preg_match('/^([a-zA-Z])\.?(.+)$/', $username, $m)) {
            $ini = $this->norm($m[1]);
            $cog = $this->norm($m[2]);
            $cog = preg_replace('/\d+$/', '', $cog); // treu sufixos numèrics (S.Martin2)
            if ($cog !== '' && in_array($cog, array_slice($toksRrhh, 1), true)) {
                $score = ($ini !== '' && str_starts_with($toksRrhh[0], $ini)) ? 100 : 70;
            }
        }
        // Coincidència pel nom de la fitxa de domi (tokens compartits)
        if ($score === 0 && trim($nomDomi) !== '') {
            $toksDomi = array_values(array_filter(explode(' ', $this->norm($nomDomi))));
            $comuns = count(array_intersect($toksDomi, $toksRrhh));
            if ($comuns >= 2) {
                $score = 60;
            }
        }

        return $score;
    }
}
