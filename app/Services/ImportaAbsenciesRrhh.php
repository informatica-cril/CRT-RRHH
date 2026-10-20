<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bolcat de l'Excel de RRHH a les absències de l'app (RRHH, 16-10-2026: «manda lo que diga el
 * excel»). En dos passos: previsualitzar (no escriu res) i aplicar.
 *
 * - Persona: es casa pel nom amb les paraules en qualsevol ordre (l'Excel posa «Cognoms Nom»
 *   i l'app «Nom Cognoms»). Si no és clar, RRHH la tria a la pantalla.
 * - Tipus: pel motiu de l'Excel (absence_types.codi_rrhh). Les incapacitats van a «Baixa IT».
 * - Estat: «concedit» = aprovada, «petició» = pendent, qualsevol altra cosa = denegada.
 * - Duplicats: la fulla «Petició vacances DOMICILIARIA» repeteix la de «Petició vacances»; una
 *   mateixa persona, motiu i dates només es crea una vegada.
 * - Tornar a importar el mateix Excel no duplica res: cada absència importada guarda la seva
 *   clau (origen_ref). Si l'Excel ha canviat l'estat, l'absència s'actualitza: mana l'Excel.
 */
class ImportaAbsenciesRrhh
{
    public const ORIGEN = 'excel_rrhh';

    /** @param array $files sortida d'ExcelRrhhAbsencies::llegeix */
    public function previsualitza(array $files): array
    {
        $tipus = AbsenceType::whereNotNull('codi_rrhh')->get()->keyBy('codi_rrhh');
        $usuaris = User::where('role', '<>', 'service')->get(['id', 'name', 'active']);
        $index = [];
        foreach ($usuaris as $u) $index[self::clauNom($u->name)][] = $u;

        $persones = [];
        $registres = [];
        $vistos = [];
        $senseData = [];
        $senseTipus = [];
        $duplicats = 0;

        foreach ($files as $f) {
            if (! $f['ini']) { $senseData[] = array_intersect_key($f, array_flip(['full', 'fila', 'nom', 'motiu'])); continue; }
            if (! isset($persones[$f['nom']])) $persones[$f['nom']] = $this->casaPersona($f['nom'], $index, $usuaris) + ['files' => 0];
            $persones[$f['nom']]['files']++;
            $t = $tipus[$f['codi']] ?? null;
            if (! $t) { $senseTipus[$f['motiu'] ?: '(sense motiu)'] = ($senseTipus[$f['motiu'] ?: '(sense motiu)'] ?? 0) + 1; continue; }

            $clau = sha1(implode('|', [self::clauNom($f['nom']), $f['codi'], $f['ini'], $f['fi'], $f['hora_inici'] ?? '']));
            if (isset($vistos[$clau])) { $duplicats++; continue; }
            $vistos[$clau] = true;

            $registres[] = [
                'clau' => $clau, 'full' => $f['full'], 'fila' => $f['fila'], 'nom' => $f['nom'],
                'absence_type_id' => $t->id, 'tipus' => $t->name, 'motiu' => $f['motiu'],
                'ini' => $f['ini'], 'fi' => $f['fi'], 'estat' => $f['estat'],
                'hora_inici' => $f['hora_inici'], 'hora_fi' => $f['hora_fi'], 'minuts' => $f['minuts'],
                'obs' => $f['obs'], 'es_baixa' => $t->category === 'baja',
            ];
        }

        $this->marcaQueFara($registres, $persones);

        $compte = fn ($que) => count(array_filter($registres, fn ($r) => $r['accio'] === $que));

        return [
            'resum' => [
                'registres' => count($registres),
                'duplicats_excel' => $duplicats,
                'a_crear' => $compte('crear'),
                'a_actualitzar' => $compte('actualitzar'),
                'ja_hi_son' => $compte('igual'),
                'sense_persona' => $compte('sense_persona'),
                'sense_data' => count($senseData),
                'sense_tipus' => array_sum($senseTipus),
            ],
            'persones' => array_values(array_map(fn ($nom, $p) => ['nom' => $nom] + $p, array_keys($persones), $persones)),
            'sense_data' => $senseData,
            'sense_tipus' => $senseTipus,
            'registres' => $registres,
        ];
    }

    /**
     * @param array $registres els de la previsualització
     * @param array $assignacions nom de l'Excel => user_id (o null per no importar-lo)
     */
    public function aplica(array $registres, array $assignacions, bool $ambMotiuBaixes, User $autor): array
    {
        $tipusValids = AbsenceType::pluck('id')->flip();
        $usuarisValids = User::pluck('id')->flip();
        $fet = ['creades' => 0, 'actualitzades' => 0, 'iguals' => 0, 'saltades' => 0];

        DB::transaction(function () use ($registres, $assignacions, $ambMotiuBaixes, $autor, $tipusValids, $usuarisValids, &$fet) {
            foreach ($registres as $r) {
                $uid = array_key_exists($r['nom'], $assignacions) ? $assignacions[$r['nom']] : ($r['user_id'] ?? null);
                if (! $uid || ! isset($usuarisValids[$uid]) || ! isset($tipusValids[$r['absence_type_id']])
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['ini']) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $r['fi'])) {
                    $fet['saltades']++;
                    continue;
                }
                $dades = $this->dadesAbsencia($r, (int) $uid, $ambMotiuBaixes, $autor);
                $existent = $this->existent($r, (int) $uid);
                if (! $existent) {
                    Absence::create($dades);
                    $fet['creades']++;
                    continue;
                }
                $canvis = array_diff_assoc(
                    array_map('strval', array_intersect_key($dades, array_flip(['approved', 'hora_inici', 'hora_fi', 'minuts', 'reason']))),
                    array_map('strval', array_intersect_key($this->actual($existent), array_flip(['approved', 'hora_inici', 'hora_fi', 'minuts', 'reason'])))
                );
                if ($canvis || $existent->origen_ref !== $r['clau']) {
                    // Mana l'Excel: estat, hores i observacions. Si ja estava decidida igual, no es toca qui la va decidir.
                    if (! isset($canvis['approved'])) unset($dades['approved_by'], $dades['approved_at']);
                    $existent->update(array_diff_key($dades, array_flip(['user_id', 'absence_type_id', 'start_date', 'end_date'])));
                    $fet[$canvis ? 'actualitzades' : 'iguals']++;
                } else {
                    $fet['iguals']++;
                }
            }

            \App\Models\AuditLog::create([
                'user_id' => $autor->id, 'action' => 'IMPORTA_ABSENCIES_EXCEL', 'entity_type' => 'absence', 'entity_id' => null,
                'description' => "Excel de RRHH: {$fet['creades']} creades, {$fet['actualitzades']} actualitzades, {$fet['iguals']} ja hi eren, {$fet['saltades']} saltades.",
            ]);
        });

        return $fet;
    }

    /** Què farà cada registre (crear / actualitzar / igual / sense_persona), per a la previsualització. */
    private function marcaQueFara(array &$registres, array $persones): void
    {
        foreach ($registres as &$r) {
            $uid = $persones[$r['nom']]['user_id'] ?? null;
            $r['user_id'] = $uid;
            if (! $uid) { $r['accio'] = 'sense_persona'; continue; }
            $e = $this->existent($r, $uid);
            if (! $e) { $r['accio'] = 'crear'; continue; }
            $esperat = self::aprovacio($r['estat']);
            $r['accio'] = ($e->approved === $esperat && (int) $e->minuts === (int) $r['minuts']) ? 'igual' : 'actualitzar';
        }
        unset($r);
    }

    private function existent(array $r, int $uid): ?Absence
    {
        return Absence::where('origen_ref', $r['clau'])->where('user_id', $uid)->first()
            // les que ja hi havia abans d'importar (entrades a mà): mateixa persona, tipus i dates
            ?? Absence::where('user_id', $uid)->where('absence_type_id', $r['absence_type_id'])
                ->whereDate('start_date', $r['ini'])->whereDate('end_date', $r['fi'])
                ->when($r['hora_inici'], fn ($q) => $q->where(fn ($q2) => $q2->whereNull('hora_inici')->orWhere('hora_inici', 'like', $r['hora_inici'] . '%')))
                ->first();
    }

    private function dadesAbsencia(array $r, int $uid, bool $ambMotiuBaixes, User $autor): array
    {
        $aprovada = self::aprovacio($r['estat']);
        $motiu = array_filter([
            $r['es_baixa'] && $ambMotiuBaixes ? $r['motiu'] : null,
            $r['obs'],
        ]);

        return [
            'user_id' => $uid,
            'absence_type_id' => (int) $r['absence_type_id'],
            'start_date' => $r['ini'],
            'end_date' => $r['fi'],
            'hora_inici' => $r['hora_inici'] ?: null,
            'hora_fi' => $r['hora_fi'] ?: null,
            'minuts' => $r['minuts'] !== null ? (int) $r['minuts'] : null,
            'reason' => $motiu ? implode(' · ', $motiu) : null,
            'approved' => $aprovada,
            'approved_by' => $aprovada === null ? null : $autor->id,
            'approved_at' => $aprovada === null ? null : now(),
            'origen' => self::ORIGEN,
            'origen_ref' => $r['clau'],
        ];
    }

    private function actual(Absence $a): array
    {
        return [
            'approved' => $a->approved, 'reason' => $a->reason, 'minuts' => $a->minuts,
            'hora_inici' => $a->hora_inici ? substr((string) $a->hora_inici, 0, 5) : null,
            'hora_fi' => $a->hora_fi ? substr((string) $a->hora_fi, 0, 5) : null,
        ];
    }

    /** «concedit» = aprovada, «petició» (o buit) = pendent, la resta = denegada. */
    public static function aprovacio(?string $estat): ?bool
    {
        $e = mb_strtolower(trim((string) $estat));
        if (str_starts_with($e, 'concedi')) return true;
        if ($e === '' || str_starts_with($e, 'petici') || str_starts_with($e, 'pendent')) return null;

        return false;
    }

    /** Paraules del nom sense accents i en ordre: «Garcia Pérez Anna» = «Anna Garcia Perez». */
    public static function clauNom(string $nom): string
    {
        $n = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nom)));
        $n = strtr($n, ['à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'í' => 'i', 'ï' => 'i', 'ò' => 'o', 'ó' => 'o',
                        'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', '·' => '']);
        $p = array_filter(explode(' ', preg_replace('/\(.*?\)/', '', $n)));
        sort($p);

        return implode(' ', $p);
    }

    private function casaPersona(string $nom, array $index, $usuaris): array
    {
        $k = self::clauNom($nom);
        if (isset($index[$k]) && count($index[$k]) === 1) {
            return ['user_id' => $index[$k][0]->id, 'nom_app' => $index[$k][0]->name, 'casament' => 'exacte', 'suggeriments' => []];
        }
        // Aproximat: comparteixen almenys dues paraules i una conté l'altra (falta un 2n cognom),
        // o només hi ha una lletra de diferència en alguna paraula («Talayedo» / «Talayero»).
        $pe = explode(' ', $k);
        $cands = [];
        foreach ($usuaris as $u) {
            $pu = explode(' ', self::clauNom($u->name));
            $iguals = count(array_intersect($pe, $pu));
            $semblants = 0;
            foreach ($pe as $a) foreach ($pu as $b) if ($a !== $b && strlen($a) > 3 && levenshtein($a, $b) <= 2) { $semblants++; break; }
            $punts = $iguals * 2 + $semblants;
            if ($iguals >= 2 && ($iguals === count($pu) || $iguals === count($pe))) $punts += 3;
            if ($iguals + $semblants >= min(count($pe), count($pu)) && $iguals >= 1) $punts += 2;
            if ($punts >= 5) $cands[] = ['id' => $u->id, 'name' => $u->name, 'actiu' => (bool) $u->active, 'punts' => $punts];
        }
        usort($cands, fn ($a, $b) => $b['punts'] <=> $a['punts']);
        $millor = $cands[0] ?? null;
        $clar = $millor && (count($cands) === 1 || $cands[1]['punts'] < $millor['punts']);

        return [
            'user_id' => $clar ? $millor['id'] : null,
            'nom_app' => $clar ? $millor['name'] : null,
            'casament' => $clar ? 'aproximat' : 'cap',
            'suggeriments' => array_slice(array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'actiu' => $c['actiu']], $cands), 0, 5),
        ];
    }
}
