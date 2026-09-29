<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Purga dels INDICADORS DE RENDIMENT ASSISTENCIAL que arriben mensualment del pont amb domi.
 *
 * El termini no se'l inventa aquesta comanda: el declaren els textos que la plantilla signa i acusa.
 *   · Política de Protecció de Dades v2.x, punt 3.7: «els indicadors es conserven durant la relació
 *     laboral i, un cop finalitzada, mentre puguin derivar-se responsabilitats; en tot cas, com a
 *     MÀXIM QUATRE ANYS DES DEL TANCAMENT DEL PERÍODE, per coherència amb el termini de conservació
 *     del registre de jornada».
 *   · Informació de l'art. 90 LOPDGDD v3.x, punt 6: mateixa redacció.
 * El termini es compta, doncs, des de `periode_fins` (el tancament del període), no des de la
 * tramesa ni des de l'última consulta. Fins ara era un termini declarat i no executat, que és pitjor
 * que no declarar-ne cap: promet a la persona una supressió que no arribava mai.
 *
 * QUÈ NO TOCA AQUESTA PURGA (i per què) — l'exclusió és tan important com la supressió:
 *   · work_logs, work_log_segments i la resta del REGISTRE HORARI: 4 anys per obligació pròpia de
 *     l'art. 34.9 ET, i els governa `locations:purge`. Són una altra dada amb una altra base
 *     jurídica; que comparteixin xifra de termini no els fa la mateixa cosa.
 *   · disciplinary_cases i disciplinary_elements: l'expedient té el seu propi cicle (prescripció
 *     de l'ET 60.2 i terminis d'impugnació). Un indicador es purga; el fet que algú en va elevar a
 *     l'expedient, no: allà ja no és un indicador, és una prova amb contradicció.
 *
 * QUÈ QUEDA EXEMPT MENTRE HI HAGI INCIDÈNCIA OBERTA (mateix criteri que `locations:purge`):
 *   · files de qui té un expedient disciplinari VIU (instrucció, comunicat o al·legacions): mentre
 *     s'hi discuteix, la xifra pot ser la prova de càrrec —o la de descàrrec de la persona—;
 *   · files amb una AL·LEGACIÓ SENSE RESPONDRE per aquell període: l'empresa deu una resposta
 *     escrita (política, punt 3.9) i no la pot deure sobre una dada que ha esborrat.
 * Un cop tancada la incidència, la purga següent se les emporta.
 *
 * Per defecte DRY-RUN. Amb --apply executa.
 */
class PurgeRendimentData extends Command
{
    const RETENTION_YEARS = 4;

    /** Estats en què l'expedient encara es discuteix (DisciplinaryEngine::potTransicio). */
    const CASOS_OBERTS = ['instruccio', 'comunicat', 'alegacions'];

    /** Traça d'accés al rendiment que escriu PerformanceController. */
    const ACCIONS_AUDITORIA = ['RENDIMENT_CONSULTA', 'RENDIMENT_PROPI'];

    protected $signature = 'rendiment:purge {--apply : Executa la purga (per defecte, dry-run)}';

    protected $description = 'Purga els indicadors de rendiment assistencial als 4 anys del tancament del període (política 3.7)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $cutoff = now()->subYears(self::RETENTION_YEARS)->toDateString();

        $this->info(($apply ? '⚠️  PURGA' : '🔎 DRY-RUN') . " — rendiment amb periode_fins anterior a {$cutoff}");

        // ── Persones amb incidència oberta: les seves files no es toquen ───────────────────
        $ambCasObert = DB::table('disciplinary_cases')
            ->whereIn('estat', self::CASOS_OBERTS)
            ->whereNotNull('user_id')
            ->distinct()->pluck('user_id');

        // Al·legacions sense resposta: bloquegen NOMÉS el seu període, no tota la persona.
        $allegacionsObertes = DB::table('rendiment_allegacions')
            ->whereNull('resposta_ts')
            ->get(['user_id', 'periode_desde', 'periode_fins'])
            ->map(fn ($a) => $a->user_id . '|' . $a->periode_desde . '|' . $a->periode_fins)
            ->flip();

        // ── 1. Indicadors per professional ────────────────────────────────────────────────
        $candidates = DB::table('domi_rendiment')
            ->where('periode_fins', '<', $cutoff)
            ->whereNotIn('user_id', $ambCasObert)
            ->get(['id', 'user_id', 'periode_desde', 'periode_fins']);

        $purgables = $candidates->reject(fn ($f) => $allegacionsObertes->has(
            $f->user_id . '|' . $f->periode_desde . '|' . $f->periode_fins
        ));

        $exemptsCas = DB::table('domi_rendiment')
            ->where('periode_fins', '<', $cutoff)
            ->whereIn('user_id', $ambCasObert)->count();
        $exemptsAlleg = $candidates->count() - $purgables->count();

        $nRend = $purgables->count();
        if ($apply && $nRend > 0) {
            foreach ($purgables->pluck('id')->chunk(500) as $chunk) {
                DB::table('domi_rendiment')->whereIn('id', $chunk)->delete();
            }
        }

        // ── 2. Al·legacions ja RESPOSTES del mateix venciment ─────────────────────────────
        // Les obertes no hi entren mai (les filtra whereNotNull): una disconformitat sense resposta
        // no es tanca esborrant-la. Les respostes marxen amb l'indicador que rebatien, perquè un
        // escrit que impugna una xifra suprimida ja no impugna res i sí que segueix identificant qui
        // el va presentar.
        $allegQ = DB::table('rendiment_allegacions')
            ->where('periode_fins', '<', $cutoff)
            ->whereNotNull('resposta_ts')
            ->whereNotIn('user_id', $ambCasObert);
        $nAlleg = $allegQ->count();
        if ($apply && $nAlleg > 0) {
            $allegQ->delete();
        }

        // ── 3. Mitjana del servei del període ─────────────────────────────────────────────
        // És l'agregat que dona sentit a la xifra individual ("88% de mitjana del servei"). Un cop
        // purgades les files individuals del període no compara res, i es va calcular a partir
        // d'elles: se'n va amb el mateix venciment.
        $mitjQ = DB::table('domi_rendiment_mitjana')->where('periode_fins', '<', $cutoff);
        $nMitj = $mitjQ->count();
        if ($apply && $nMitj > 0) {
            $mitjQ->delete();
        }

        // ── 4. Traça de qui va consultar el rendiment de qui ──────────────────────────────
        // La política (punt 3.5 i 11) promet aquest registre com a garantia de la persona, no com a
        // arxiu perpetu: viu el mateix que la dada que documenta. `locations:purge` no l'abasta
        // —filtra per ACCESS_LOCATION_DATA i DOMI_%— i per això fins ara no vencia mai.
        $audQ = DB::table('audit_logs')
            ->where('created_at', '<', now()->subYears(self::RETENTION_YEARS))
            ->whereIn('action', self::ACCIONS_AUDITORIA);
        $nAud = $audQ->count();
        if ($apply && $nAud > 0) {
            $audQ->delete();
        }

        $this->table(['Operació', 'Files'], [
            ['Indicadors de rendiment suprimits (4 anys)', $nRend],
            ['  ↳ exempts: professional amb expedient obert', $exemptsCas],
            ['  ↳ exempts: al·legació del període sense resposta', $exemptsAlleg],
            ['Al·legacions respostes suprimides (4 anys)', $nAlleg],
            ['Mitjanes del servei suprimides (4 anys)', $nMitj],
            ['Auditoria de consultes de rendiment suprimida (4 anys)', $nAud],
            ['Registre horari (art. 34.9 ET)', 'INTACTE — el governa locations:purge'],
            ['Expedient disciplinari', 'INTACTE — cicle propi (ET 60.2)'],
        ]);

        if (! $apply) {
            $this->warn('DRY-RUN: cap canvi aplicat. Executa amb --apply.');
        }

        return self::SUCCESS;
    }
}
