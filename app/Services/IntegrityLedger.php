<?php

namespace App\Services;

use App\Models\DailySeal;
use App\Models\IntegrityEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Llibre d'integritat de tota l'app: incorpora els esdeveniments del dia com a cadena de hash i,
 * en el tancament diari, en calcula l'arrel per segellar-la (1 sol segell FNMT per dia).
 *
 * Garanties: append-only, cadena global (cada hash inclou l'anterior), incorporació única per fila
 * d'origen, i tolerància a fonts absents (no trenca la resta).
 */
class IntegrityLedger
{
    public function __construct(private TsaClient $tsa)
    {
    }

    /** Hash canònic d'una fila (independent de l'ordre de columnes). */
    private function payloadHash(string $source, $id, array $row): string
    {
        ksort($row);
        return hash('sha256', $source . '|' . $id . '|' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Columnes que una font declara ESTABLES al catàleg, o null si no en declara (→ fila sencera).
     * Vegeu config/integrity.php: 'estables' substitueix l'antic 'mutable' => true, que renunciava a
     * rellegir la font i per tant deixava passar un canvi d'import després de segellar la nòmina.
     */
    private function campsEstables(string $table): ?array
    {
        foreach (config('integrity.sources', []) as $src) {
            if (($src['table'] ?? null) === $table) {
                $cols = $src['estables'] ?? null;

                return is_array($cols) && $cols !== [] ? $cols : null;
            }
        }

        return null;   // per defecte, append-only: es hashea i es rellegeix la fila sencera
    }

    /**
     * Deixa de la fila NOMÉS el que la font declara estable (mateix filtre en segellar i en
     * verificar: si no fossin idèntics, el llibre cantaria sol). Una columna declarada que no
     * existeix a la taula es registra: seria una reducció SILENCIOSA del que se segella.
     */
    private function filtraEstables(string $table, array $row): array
    {
        $cols = $this->campsEstables($table);
        if ($cols === null) {
            return $row;
        }
        $absents = array_diff($cols, array_keys($row));
        if ($absents !== []) {
            Log::warning("integrity: la font «{$table}» declara camps estables que no existeixen a la taula: "
                . implode(', ', $absents) . ' — reviseu config/integrity.php');
        }

        return array_intersect_key($row, array_flip($cols));
    }

    /** Últim hash de la cadena global (per encadenar-hi els nous). */
    private function lastHash(): ?string
    {
        return IntegrityEvent::orderByDesc('id')->value('hash');
    }

    /**
     * Incorpora al llibre les files noves de cada font amb data dins del dia indicat.
     * Retorna quantes s'han afegit. Idempotent: una fila d'origen s'incorpora un sol cop.
     */
    public function collect(Carbon $day): int
    {
        $ini = $day->copy()->startOfDay()->subDays(2); // retroalcance: recull cues no incorporades
        $fi  = $day->copy()->endOfDay();

        // 1) Reuneix candidats de totes les fonts en una llista ordenable globalment.
        $cands = [];
        foreach (config('integrity.sources', []) as $src) {
            $table = $src['table'] ?? null;
            $tsCol = $src['ts'] ?? 'created_at';
            if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $tsCol)) {
                continue; // font absent → s'omet sense trencar la resta
            }
            try {
                $rows = DB::table($table)
                    ->whereBetween($tsCol, [$ini, $fi])
                    ->orderBy('id')->get();
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $r) {
                $row = (array) $r;
                $id  = $row['id'] ?? null;
                $cands[] = [
                    'source'      => $table,
                    'source_id'   => $id,
                    'occurred_at' => $row[$tsCol] ?? $day->toDateTimeString(),
                    'payload'     => $this->payloadHash($table, $id, $this->filtraEstables($table, $row)),
                ];
            }
        }
        if (empty($cands)) {
            return 0;
        }

        // 2) Ordre global determinista (data, font, id) perquè la cadena sigui estable.
        usort($cands, fn ($a, $b) => [$a['occurred_at'], $a['source'], (int) $a['source_id']]
                                 <=> [$b['occurred_at'], $b['source'], (int) $b['source_id']]);

        // 3) Descarta els ja incorporats i encadena els nous.
        $added = 0;
        $prev  = $this->lastHash();
        foreach ($cands as $c) {
            $exists = IntegrityEvent::where('source', $c['source'])
                ->where('source_id', $c['source_id'])->exists();
            if ($exists) {
                continue;
            }
            $hash = hash('sha256', ($prev ?? '') . '|' . $c['source'] . '|' . $c['source_id'] . '|' . $c['occurred_at'] . '|' . $c['payload']);
            IntegrityEvent::create([
                'occurred_at'  => $c['occurred_at'],
                'source'       => $c['source'],
                'source_id'    => $c['source_id'],
                'payload_hash' => $c['payload'],
                'prev_hash'    => $prev,
                'hash'         => $hash,
                'created_at'   => now(),
            ]);
            $prev = $hash;
            $added++;
        }

        return $added;
    }

    /** Incorpora les firmes remotes (altres apps: domi, logopedia…) del dia via API. */
    public function collectRemote(Carbon $day): int
    {
        $added = 0;
        $prev  = $this->lastHash();
        foreach (config('integrity.remote_sources', []) as $src) {
            $name = $src['name'] ?? null;
            $url  = $src['url'] ?? null;
            if (! $name || ! $url) {
                continue;
            }
            try {
                $req = Http::timeout(config('integrity.tsa.timeout', 20));
                if (! empty($src['token'])) {
                    $req = $req->withToken($src['token']);
                }
                $resp = $req->get($url, ['desde' => $day->toDateString(), 'fins' => $day->toDateString()]);
                if (! $resp->ok()) {
                    /* Les firmes es tanquen a l'app d'origen i reben el segell FNMT aqui, de nit i
                       totes juntes. Si aquella nit l'origen no contesta i el fallit es queda mut,
                       aquelles firmes es queden fora del segell per sempre i ningu se n'assabenta. */
                    Log::warning("[integritat] {$name} no ha contestat per al dia {$day->toDateString()}: HTTP "
                               . $resp->status() . '. Les seves firmes queden pendents de recuperar.');
                    continue;
                }
                $events = $resp->json('events') ?? [];
            } catch (\Throwable $e) {
                Log::warning("[integritat] {$name} no accessible per al dia {$day->toDateString()}: "
                           . $e->getMessage() . '. Les seves firmes queden pendents de recuperar.');
                continue; // app remota no accessible → no trenca la resta del segell
            }
            foreach ($events as $ev) {
                $id  = $ev['id'] ?? null;
                $h   = $ev['hash'] ?? null;
                $occ = $ev['occurred_at'] ?? $day->toDateTimeString();
                if ($id === null || ! $h) {
                    continue;
                }
                if (IntegrityEvent::where('source', $name)->where('source_id', $id)->exists()) {
                    continue;
                }
                $payload = hash('sha256', $name . '|' . $id . '|' . $h); // digest de la firma remota
                $hash = hash('sha256', ($prev ?? '') . '|' . $name . '|' . $id . '|' . $occ . '|' . $payload);
                IntegrityEvent::create([
                    'occurred_at'  => $occ, 'source' => $name, 'source_id' => $id,
                    'payload_hash' => $payload, 'prev_hash' => $prev, 'hash' => $hash, 'created_at' => now(),
                ]);
                $prev = $hash;
                $added++;
            }
        }

        return $added;
    }

    /**
     * Tanca i segella el dia: incorpora (local + firmes remotes de totes les apps), calcula l'arrel,
     * l'encadena amb el segell anterior i, si hi ha TSA i esdeveniments, hi demana el segell FNMT
     * (un sol crèdit per dia). Segella TOT el que estigui sense segellar fins al final del dia.
     */
    /** Dies enrere que es tornen a demanar a les apps d'origen a cada tancament. */
    private const RECUPERACIO_DIES = 7;

    public function sealDay(Carbon $day, bool $withTsa = true): DailySeal
    {
        $this->collect($day);

        /* Recuperacio de les nits en que una app d'origen no va contestar. La incorporacio
           es unica per fila d'origen, de manera que tornar-hi no duplica res; i el tancament
           segella TOT el que estigui sense segellar, aixi que una firma recuperada avui entra
           al segell d'avui amb la seva data real. Sense aixo, una sola nit de xarxa dolenta
           deixava firmes fora del llibre per sempre. */
        for ($i = self::RECUPERACIO_DIES; $i >= 1; $i--) {
            $this->collectRemote($day->copy()->subDays($i));
        }

        $this->collectRemote($day);

        $fi = $day->copy()->endOfDay();

        $events = IntegrityEvent::whereNull('seal_id')
            ->where('occurred_at', '<=', $fi)
            ->orderBy('id')->get();

        $prevSeal     = DailySeal::orderByDesc('seal_date')->where('seal_date', '<', $day->toDateString())->first();
        $prevSealHash = $prevSeal?->root_hash;

        $lastHash = $events->last()?->hash;
        $root     = $lastHash
            ? hash('sha256', ($prevSealHash ?? '') . '|' . $lastHash . '|' . $events->count())
            : ($prevSealHash ?? hash('sha256', 'empty|' . $day->toDateString()));

        $seal = $this->segellDelDia($day) ?? new DailySeal(['seal_date' => $day->toDateString()]);
        $seal->events_count   = $events->count();
        $seal->first_event_id = $events->first()?->id;
        $seal->last_event_id  = $events->last()?->id;
        $seal->prev_seal_hash = $prevSealHash;
        $seal->root_hash      = $root;
        $seal->sealed_at      = now();
        if ($seal->tsa_status !== 'segellat') {
            $seal->tsa_status = 'pendent';
        }
        $seal->save();

        // Marca els esdeveniments com a segellats.
        if ($events->isNotEmpty()) {
            IntegrityEvent::whereIn('id', $events->pluck('id'))->update(['seal_id' => $seal->id]);
        }

        // Segell de temps extern: NOMÉS si cal, hi ha events i encara no s'ha segellat (no malgastar crèdit).
        if ($withTsa && $events->isNotEmpty() && $seal->tsa_status !== 'segellat') {
            $r = $this->tsa->stamp($root);
            $seal->tsa_provider = $r['provider'] ?? config('integrity.tsa.provider');
            $seal->tsa_status   = $r['status'];
            $seal->tsa_token    = $r['token'] ?? null;
            $seal->tsa_time     = $r['time'] ?? null;
            $seal->tsa_error    = $r['error'] ?? null;
            $seal->save();
        }

        return $seal;
    }

    /**
     * Segell d'un dia, sigui quin sigui el motor de BD. Es busca pel DIA (whereDate) i no per
     * igualtat exacta: els segells escrits abans de normalitzar `seal_date` poden portar l'hora
     * enganxada ('2026-05-20 00:00:00') i una cerca per igualtat no els trobaria mai fora de MySQL.
     */
    private function segellDelDia(Carbon $day): ?DailySeal
    {
        return DailySeal::whereDate('seal_date', $day->toDateString())->first();
    }

    /**
     * Recomputa el payload d'un esdeveniment contra la seva FONT REAL (no contra el que hi ha desat).
     * Cap font local queda exempta: les que canvien legítimament declaren 'estables' al catàleg i es
     * rellegeixen igualment, però només per aquestes columnes.
     * Retorna ['verificable' => false] quan la font no és local (firmes d'altres apps) o la taula no
     * existeix; ['existeix' => false] quan la fila d'origen ha desaparegut.
     */
    private function payloadDeLaFont(IntegrityEvent $ev): array
    {
        if (! $ev->source || ! Schema::hasTable($ev->source)) {
            return ['verificable' => false];
        }
        try {
            $row = DB::table($ev->source)->where('id', $ev->source_id)->first();
        } catch (\Throwable $e) {
            return ['verificable' => false];
        }
        if (! $row) {
            return ['verificable' => true, 'existeix' => false];
        }

        return [
            'verificable' => true,
            'existeix'    => true,
            'hash'        => $this->payloadHash($ev->source, $ev->source_id, $this->filtraEstables($ev->source, (array) $row)),
        ];
    }

    /**
     * Verifica la cadena d'un dia, baula a baula. Per a cada esdeveniment comprova, en aquest ordre:
     *   (a) que el contingut segellat encara es correspon amb la FILA D'ORIGEN (es rellegeix la font:
     *       editar l'acusament original després de segellar-lo ha de cantar);
     *   (b) que el hash de la fila quadra amb el seu propi contingut;
     *   (c) que la cadena és CADENA: prev_hash[n] == hash[n-1]. Sense aquesta comprovació, qui pot
     *       escriure a la BD altera una fila del mig, li recalcula el hash i el llibre diu "ok".
     * Finalment comprova que l'arrel del segell hi lliga. Retorna un informe que diu QUINA fila falla.
     */
    public function verify(Carbon $day): array
    {
        $seal = $this->segellDelDia($day);
        if (! $seal) {
            return ['ok' => false, 'motiu' => 'No hi ha segell per a aquest dia'];
        }
        $events = $seal->events()->orderBy('id')->get();

        $trencat = null;
        $motiu   = null;

        // La cadena és GLOBAL: la primera baula del dia enllaça amb l'últim esdeveniment anterior.
        $prevEsperat = $events->isEmpty() ? null
            : IntegrityEvent::where('id', '<', $events->first()->id)->orderByDesc('id')->value('hash');

        foreach ($events as $ev) {
            $font = $this->payloadDeLaFont($ev);
            if (($font['verificable'] ?? false) && ! ($font['existeix'] ?? false)) {
                $trencat = $ev->id;
                $motiu = "La fila d'origen {$ev->source}#{$ev->source_id} ja no existeix: el segell acredita una cosa que s'ha esborrat";
                break;
            }
            if (($font['verificable'] ?? false) && ! hash_equals((string) $ev->payload_hash, (string) $font['hash'])) {
                $trencat = $ev->id;
                $estables = $this->campsEstables($ev->source);
                $motiu = "La fila d'origen {$ev->source}#{$ev->source_id} s'ha modificat després de segellar-se"
                    . ($estables ? ' (camps segellats: ' . implode(', ', $estables) . ')' : '');
                break;
            }
            $recompute = hash('sha256', ($ev->prev_hash ?? '') . '|' . $ev->source . '|' . $ev->source_id . '|' . $ev->occurred_at . '|' . $ev->payload_hash);
            if (! hash_equals((string) $ev->hash, $recompute)) {
                $trencat = $ev->id;
                $motiu = "El hash de l'esdeveniment #{$ev->id} no es correspon amb el seu contingut";
                break;
            }
            if (! hash_equals((string) ($ev->prev_hash ?? ''), (string) ($prevEsperat ?? ''))) {
                $trencat = $ev->id;
                $motiu = "L'esdeveniment #{$ev->id} no enllaça amb l'anterior (prev_hash): la cadena s'ha trencat o s'hi ha intercalat/tret una fila";
                break;
            }
            $prevEsperat = $ev->hash;
        }

        $lastHash = $events->last()?->hash;
        $rootEsperat = $lastHash
            ? hash('sha256', ($seal->prev_seal_hash ?? '') . '|' . $lastHash . '|' . $events->count())
            : ($seal->prev_seal_hash ?? hash('sha256', 'empty|' . $day->toDateString()));
        $arrelOk = hash_equals((string) $seal->root_hash, $rootEsperat);

        return [
            'ok'            => $trencat === null && $arrelOk,
            'esdeveniments' => $events->count(),
            'cadena_trencada_a' => $trencat,
            'motiu'             => $motiu ?? (! $arrelOk ? 'L\'arrel del segell no es correspon amb la cadena del dia' : null),
            'arrel_coincideix'  => $arrelOk,
            'tsa_status'    => $seal->tsa_status,
            'tsa_time'      => $seal->tsa_time,
        ];
    }
}
