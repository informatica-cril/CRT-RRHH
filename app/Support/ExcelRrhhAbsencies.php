<?php

namespace App\Support;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Llegeix les fulles d'absències de l'Excel de RRHH («RRHH 2026.xlsx»): permisos, vacances,
 * incapacitats, peticions de vacances i hores sindicals.
 *
 * ── PER QUÈ NO PHPSPREADSHEET ────────────────────────────────────────────────────
 * La fulla «hores sindicals» té format fins a la fila 1.048.268: 178 MB d'XML per 35 files de
 * dades. PhpSpreadsheet carrega el llibre sencer a memòria i no acaba. Aquí es llegeix l'XML en
 * streaming, només les fulles que calen, i es para quan hi ha 200 files buides seguides.
 *
 * Les dates són números de sèrie d'Excel (46155 = 2026-05-14) i les hores, fraccions de dia
 * (0,5 = 12:00). Algunes hores estan escrites a mà («14:45h», «11H», «?¿»): es llegeix el que es pot.
 */
final class ExcelRrhhAbsencies
{
    /** fulla => [nom, motiu, inici, fi, estat, extres] (lletres de columna) */
    private const FULLES = [
        'permisos' => ['nom' => 'A', 'motiu' => 'B', 'ini' => 'C', 'fi' => 'D', 'estat' => 'I', 'hini' => 'F', 'hfi' => 'G', 'total' => 'H', 'obs' => 'L'],
        'vacances' => ['nom' => 'B', 'motiu' => 'C', 'ini' => 'D', 'fi' => 'E', 'estat' => 'G', 'obs' => 'K'],
        'incapacitats' => ['nom' => 'A', 'motiu' => 'B', 'ini' => 'C', 'fi' => 'D', 'estat' => 'F', 'obs' => 'I', 'codi' => 'incapacitat'],
        'Petició vacances' => ['nom' => 'B', 'motiu' => 'C', 'ini' => 'D', 'fi' => 'E', 'estat' => 'G', 'obs' => 'K'],
        'Petició vacances DOMICILIARIA' => ['nom' => 'A', 'motiu' => 'B', 'ini' => 'C', 'fi' => 'D', 'estat' => 'F'],
        'hores sindicals' => ['nom' => 'B', 'motiu' => 'C', 'ini' => 'D', 'fi' => 'E', 'estat' => 'G', 'durada' => 'F', 'obs' => 'J'],
    ];

    /** @return array<int, array> una fila per absència, amb el full i la fila d'origen */
    public static function llegeix(string $fitxer): array
    {
        $zip = new ZipArchive();
        if ($zip->open($fitxer) !== true) {
            throw new RuntimeException("No s'ha pogut obrir el fitxer: no sembla un Excel (.xlsx).");
        }
        $textos = self::textosCompartits($zip);
        $fulles = self::rutesFulles($zip);
        $zip->close();

        $files = [];
        foreach (self::FULLES as $full => $col) {
            if (! isset($fulles[$full])) continue;
            foreach (self::filesDe($fitxer, $fulles[$full], $textos) as $num => $v) {
                if ($num === 1) continue; // capçalera
                $nom = trim(preg_replace('/\s+/u', ' ', (string) ($v[$col['nom']] ?? '')));
                $motiu = trim((string) ($v[$col['motiu']] ?? ''));
                $ini = self::data($v[$col['ini']] ?? null);
                if ($nom === '' && $motiu === '' && ! $ini) continue;

                $fila = [
                    'full' => $full, 'fila' => $num, 'nom' => $nom,
                    'motiu' => $motiu,
                    'codi' => $col['codi'] ?? self::codi($motiu),
                    'ini' => $ini,
                    'fi' => self::data($v[$col['fi']] ?? null) ?? $ini,
                    'estat' => mb_strtolower(trim((string) ($v[$col['estat']] ?? ''))),
                    'hora_inici' => null, 'hora_fi' => null, 'minuts' => null,
                    'obs' => isset($col['obs']) ? (trim((string) ($v[$col['obs']] ?? '')) ?: null) : null,
                ];
                if (isset($col['hini'])) {
                    $fila['hora_inici'] = self::hora($v[$col['hini']] ?? null);
                    $fila['hora_fi'] = self::hora($v[$col['hfi']] ?? null);
                    $total = (float) ($v[$col['total']] ?? 0);
                    if ($total > 0 && $total < 1) {
                        $fila['minuts'] = (int) round($total * 1440);
                    } elseif ($fila['hora_inici'] && $fila['hora_fi'] && $fila['hora_fi'] > $fila['hora_inici']) {
                        [$h1, $m1] = explode(':', $fila['hora_inici']);
                        [$h2, $m2] = explode(':', $fila['hora_fi']);
                        $fila['minuts'] = ($h2 * 60 + $m2) - ($h1 * 60 + $m1);
                    }
                }
                if (isset($col['durada'])) {
                    $d = (float) ($v[$col['durada']] ?? 0);
                    if ($d > 0 && $d < 1) $fila['minuts'] = (int) round($d * 1440);
                }
                $files[] = $fila;
            }
        }

        return $files;
    }

    /** Motiu de l'Excel tal com es guarda a absence_types.codi_rrhh. */
    public static function codi(string $motiu): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $motiu)));
    }

    private static function data($v): ?string
    {
        if (! is_numeric($v) || (float) $v < 30000 || (float) $v > 80000) return null;

        return gmdate('Y-m-d', ((int) floor((float) $v) - 25569) * 86400);
    }

    private static function hora($v): ?string
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v) && (float) $v >= 0 && (float) $v < 1) {
            $min = (int) round((float) $v * 1440);

            return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
        }
        // «14:45h», «11H», «8:00h», «9.30»
        if (preg_match('/^\s*(\d{1,2})(?:[:.,](\d{2}))?\s*h?\s*$/iu', (string) $v, $m) && (int) $m[1] < 24) {
            return sprintf('%02d:%02d', (int) $m[1], (int) ($m[2] ?? 0));
        }

        return null;
    }

    private static function textosCompartits(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $r = new XMLReader();
        $r->XML($xml);
        $textos = [];
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT && $r->name === 'si') {
                $si = simplexml_load_string($r->readOuterXml());
                $t = $si ? $si->xpath('.//*[local-name()="t"]') : [];
                $textos[] = implode('', array_map('strval', $t ?: []));
            }
        }

        return $textos;
    }

    /** nom de la fulla => ruta dins del zip */
    private static function rutesFulles(ZipArchive $zip): array
    {
        $wb = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        if (! $wb || ! $rels) throw new RuntimeException("L'Excel no té el format esperat.");
        $desti = [];
        foreach ($rels->Relationship as $rel) $desti[(string) $rel['Id']] = (string) $rel['Target'];
        $rutes = [];
        foreach ($wb->sheets->sheet as $sh) {
            $rid = (string) $sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($desti[$rid])) $rutes[(string) $sh['name']] = 'xl/' . ltrim(str_replace('/xl/', '', $desti[$rid]), '/');
        }

        return $rutes;
    }

    /** @return iterable<int, array<string,string>> número de fila => [columna => valor] */
    private static function filesDe(string $fitxer, string $ruta, array $textos): iterable
    {
        $r = new XMLReader();
        if (! $r->open('zip://' . $fitxer . '#' . $ruta)) return;
        $buides = 0;
        while ($r->read()) {
            if ($r->nodeType !== XMLReader::ELEMENT || $r->name !== 'row') continue;
            $row = simplexml_load_string($r->readOuterXml());
            $vals = [];
            foreach ($row->c as $c) {
                $v = isset($c->v) ? (string) $c->v : (isset($c->is) ? (string) $c->is->t : '');
                if ((string) $c['t'] === 's') $v = $textos[(int) $v] ?? '';
                if ($v === '') continue;
                $vals[preg_replace('/\d+/', '', (string) $c['r'])] = $v;
            }
            if (! $vals) {
                if (++$buides > 200) break;
                continue;
            }
            $buides = 0;
            yield (int) $row['r'] => $vals;
        }
        $r->close();
    }
}
