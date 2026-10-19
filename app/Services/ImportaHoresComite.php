<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ComiteHora;
use App\Models\ComiteMembre;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Hores sindicals de l'Excel de RRHH (fulla «hores sindicals») al Comitè d'empresa (RRHH,
 * 20-10-2026: l'Excel ja tenia qui és del comitè i les hores que ha fet, i a l'app no hi eren).
 *
 * - Cada fila amb nom, data i durada és un ús del crèdit (tipus «Gestions de representació»).
 * - Estat: «concedit» = validada per qui importa, «petició» = pendent, la resta = rebutjada.
 * - Qui no és membre del comitè aquell dia es dona d'alta (comitè, vocal, 15 h al mes, des de la
 *   primera data que surt a l'Excel). RRHH ho pot ajustar després a «Comitè d'empresa».
 * - L'Excel no porta la franja, només la durada: es desa a partir de les 00:00 (la pantalla no la
 *   mostra per a les importades). Tornar a importar el mateix Excel no duplica res (origen_ref).
 * - Les dades personals no són al codi: arriben amb l'Excel que puja RRHH.
 */
class ImportaHoresComite
{
    public const FULL = 'hores sindicals';
    public const CREDIT_PER_DEFECTE = 15;

    /**
     * @param array $files    sortida d'ExcelRrhhAbsencies::llegeix
     * @param array $persones de la previsualització d'absències: [['nom' => ..., 'user_id' => ...], ...]
     */
    public function previsualitza(array $files, array $persones): array
    {
        $uidDe = collect($persones)->pluck('user_id', 'nom')->all();
        $registres = [];
        $vegades = [];
        foreach ($files as $f) {
            if ($f['full'] !== self::FULL || ! $f['ini'] || ! $f['minuts'] || $f['nom'] === '') continue;
            $motiu = trim(($f['obs'] ?? '') ?: ($f['motiu'] ?: 'Hores sindicals'));
            // Dues files idèntiques (mateixa persona, dia, durada i motiu) són dos usos: es numeren.
            $base = implode('|', [ImportaAbsenciesRrhh::clauNom($f['nom']), $f['ini'], $f['minuts'], mb_strtolower($motiu)]);
            $vegades[$base] = ($vegades[$base] ?? 0) + 1;
            $registres[] = [
                'clau' => sha1('comite|' . $base . '|' . $vegades[$base]),
                'nom' => $f['nom'], 'data' => $f['ini'], 'minuts' => (int) $f['minuts'],
                'estat' => $f['estat'], 'motiu' => mb_substr($motiu, 0, 255), 'fila' => $f['fila'],
                'user_id' => $uidDe[$f['nom']] ?? null,
            ];
        }

        $jaImportades = ComiteHora::whereIn('origen_ref', array_column($registres, 'clau'))->pluck('origen_ref')->flip();
        $resum = [];
        foreach ($registres as $r) {
            $p = &$resum[$r['nom']];
            $p ??= ['nom' => $r['nom'], 'user_id' => $r['user_id'], 'hores' => 0, 'files' => 0, 'noves' => 0];
            $p['hores'] = round($p['hores'] + $r['minuts'] / 60, 2);
            $p['files']++;
            if (! isset($jaImportades[$r['clau']])) $p['noves']++;
            unset($p);
        }
        $membres = ComiteMembre::whereIn('user_id', array_filter(array_column($resum, 'user_id')))->pluck('user_id')->flip();
        foreach ($resum as &$p) $p['es_membre'] = $p['user_id'] && isset($membres[$p['user_id']]);

        return ['registres' => $registres, 'persones' => array_values($resum)];
    }

    /** @param array $assignacions nom de l'Excel => user_id (la mateixa tria que per a les absències) */
    public function aplica(array $registres, array $assignacions, User $autor): array
    {
        $fet = ['hores_creades' => 0, 'hores_actualitzades' => 0, 'hores_iguals' => 0, 'hores_saltades' => 0, 'membres_nous' => 0];
        $usuarisValids = User::pluck('id')->flip();

        DB::transaction(function () use ($registres, $assignacions, $autor, $usuarisValids, &$fet) {
            // Alta al comitè de qui no hi és, des de la seva primera data a l'Excel.
            $primera = [];
            foreach ($registres as $r) {
                $uid = $this->uid($r, $assignacions);
                if ($uid && isset($usuarisValids[$uid])) $primera[$uid] = min($primera[$uid] ?? $r['data'], $r['data']);
            }
            foreach ($primera as $uid => $des) {
                if (ComiteMembre::where('user_id', $uid)->exists()) continue;
                ComiteMembre::create([
                    'user_id' => $uid, 'tipus_representacio' => 'comite', 'carrec' => 'vocal',
                    'data_alta' => $des, 'credit_hores_mensual' => self::CREDIT_PER_DEFECTE,
                    'notes' => "Alta des de l'Excel de RRHH (fulla «hores sindicals»).", 'creat_per' => $autor->id,
                ]);
                $fet['membres_nous']++;
            }

            foreach ($registres as $r) {
                $uid = $this->uid($r, $assignacions);
                $membre = $uid ? ComiteMembre::where('user_id', $uid)->vigentEl($r['data'])->first()
                    ?? ComiteMembre::where('user_id', $uid)->orderBy('data_alta')->first() : null;
                if (! $membre || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['data']) || $r['minuts'] < 1 || $r['minuts'] > 1439) {
                    $fet['hores_saltades']++;
                    continue;
                }
                $estat = match (ImportaAbsenciesRrhh::aprovacio($r['estat'])) { true => 'validada', null => 'pendent', false => 'rebutjada' };
                $dades = [
                    'membre_id' => $membre->id, 'user_id' => $uid, 'data' => $r['data'],
                    'hora_inici' => '00:00:00', 'hora_fi' => sprintf('%02d:%02d:00', intdiv($r['minuts'], 60), $r['minuts'] % 60),
                    'minuts' => $r['minuts'], 'tipus' => 'gestio', 'consumeix_credit' => true, 'motiu' => $r['motiu'],
                ];
                $hora = ComiteHora::where('origen_ref', $r['clau'])->first();
                if (! $hora) {
                    $hora = new ComiteHora($dades);
                    $hora->forceFill(['origen_ref' => $r['clau'], 'estat' => $estat] + $this->resolucio($estat, $autor))->save();
                    $fet['hores_creades']++;
                } elseif ($hora->estat !== $estat || (int) $hora->minuts !== $r['minuts']) {
                    // Mana l'Excel, com a les absències.
                    $hora->fill($dades)->forceFill(['estat' => $estat] + $this->resolucio($estat, $autor))->save();
                    $fet['hores_actualitzades']++;
                } else {
                    $fet['hores_iguals']++;
                }
            }

            AuditLog::create([
                'user_id' => $autor->id, 'action' => 'COMITE_HORES_IMPORTA_EXCEL', 'entity_type' => 'comite_hora', 'entity_id' => null,
                'description' => "Excel de RRHH al comitè: {$fet['hores_creades']} hores creades, {$fet['hores_actualitzades']} actualitzades, "
                    . "{$fet['hores_iguals']} ja hi eren, {$fet['hores_saltades']} saltades; {$fet['membres_nous']} membres nous.",
            ]);
        });

        return $fet;
    }

    private function uid(array $r, array $assignacions): ?int
    {
        $uid = array_key_exists($r['nom'], $assignacions) ? $assignacions[$r['nom']] : ($r['user_id'] ?? null);

        return $uid ? (int) $uid : null;
    }

    private function resolucio(string $estat, User $autor): array
    {
        return $estat === 'pendent'
            ? ['validat_per' => null, 'validat_ts' => null]
            : ['validat_per' => $autor->id, 'validat_ts' => now()];
    }
}
