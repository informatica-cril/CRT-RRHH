<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Alta massiva de personal des de l'Excel (la plantilla d'ImportTemplateController).
 * RRHH crea/actualitza la fitxa i desa quin usuari/perfil tindrà a domi; domi crea la
 * compte llegint-ho per bulk-index. Creuament pel DNI: si ja existeix, s'ACTUALITZA
 * (no es duplica). Vegeu docs/DESPLEGAMENT-INFORMATICA.md.
 *
 * POST /api/v1/users/import        (Excel a 'file')  -> valida i importa (tot o res)
 * POST /api/v1/users/import?dry=1  -> només valida i retorna l'informe, sense escriure
 */
class ImportPersonalController extends Controller
{
    private const ENUMS = [
        'privilegi_domi' => ['Domiciliaria', 'Coordinacio', 'Administrador', 'Direccio', 'Medico', 'Codificador', 'AtencioClient', 'tpo', 'Valorador'],
        'rol_rrhh'       => ['worker', 'coordinator', 'admin', 'hr'],
        'ambit'          => ['DOMICILIARIA', 'DOMICILIARIA_VALLES', 'AMBULATORIA'],
        'relacio'        => ['laboral', 'autonom'],
        'segon_factor'   => ['dispositiu', 'totp'],
    ];
    // Ordre de columnes de la plantilla (clau tècnica per posició).
    private const COLS = ['nom_complet', 'dni', 'email', 'telefon', 'data_naixement', 'usuari_domi',
        'privilegi_domi', 'rol_rrhh', 'ambit', 'categoria', 'jornada_setmanal_h', 'relacio',
        'disponibilitat_h', 'segon_factor', 'municipis', 'especialitat',
        /* Afegides el 31-07-2026 AL FINAL a posta: les columnes es llegeixen per POSICIÓ,
           i posar-les al mig desplaçaria totes les següents en qualsevol Excel ja repartit.
           Sense lot ni departament, una persona no surt a cap filtre del quadre de
           disponibilitat i queda invisible per a qui reparteix la feina. */
        'lot', 'departament'];

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);
        $dry = (bool) $request->query('dry');

        try {
            $sheet = IOFactory::load($request->file('file')->getRealPath())->getSheetByName('Altes')
                   ?? IOFactory::load($request->file('file')->getRealPath())->getSheet(0);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'No s\'ha pogut llegir l\'Excel: ' . $e->getMessage()], 422);
        }

        $rows = $sheet->toArray(null, true, false, false);
        array_shift($rows);   // capçalera

        $errors = []; $valides = []; $nFila = 1;
        foreach ($rows as $raw) {
            $nFila++;
            if (count(array_filter($raw, fn($v) => trim((string) $v) !== '')) === 0) continue;   // fila buida
            $r = [];
            foreach (self::COLS as $i => $k) $r[$k] = trim((string) ($raw[$i] ?? ''));

            $errs = $this->validaFila($r);
            if ($errs) { $errors[] = ['fila' => $nFila, 'valors' => $r['dni'] ?: $r['nom_complet'], 'errors' => $errs]; }
            else { $valides[] = $r; }
        }

        // Duplicats de DNI/usuari DINS del propi fitxer.
        foreach (['dni' => 'DNI', 'usuari_domi' => 'usuari domi'] as $camp => $etq) {
            $vals = array_column($valides, $camp);
            $dups = array_unique(array_diff_assoc($vals, array_unique($vals)));
            foreach ($dups as $d) if ($d !== '') $errors[] = ['fila' => '-', 'valors' => $d, 'errors' => ["$etq repetit dins del fitxer"]];
        }

        if ($errors) {
            return response()->json(['ok' => false, 'importats' => 0, 'total' => count($valides) + count($errors),
                'message' => 'No s\'ha importat res: corregeix els errors i torna a provar (tot o res).',
                'errors' => $errors], 422);
        }
        if ($dry) {
            return response()->json(['ok' => true, 'dry_run' => true, 'validades' => count($valides),
                'message' => count($valides) . ' files vàlides. Cap canvi (dry-run).']);
        }

        // Import real, tot dins d'una transacció.
        $creats = 0; $actualitzats = 0;
        DB::transaction(function () use ($valides, &$creats, &$actualitzats) {
            foreach ($valides as $r) {
                $existent = User::where('dni', $r['dni'])->first();
                $dades = [
                    'name' => $r['nom_complet'], 'email' => $r['email'], 'dni' => $r['dni'],
                    'device_phone' => $r['telefon'] ?: null,
                    'role' => $r['rol_rrhh'] ?: 'worker',
                    'work_type' => $r['ambit'] ?: null, 'job_profile' => $r['categoria'] ?: null,
                    'relacio' => $r['relacio'] ?: null,
                    'disponibilitat_setmanal' => $r['disponibilitat_h'] !== '' ? (float) $r['disponibilitat_h'] : null,
                    'second_factor' => $r['segon_factor'] ?: 'dispositiu',
                    'domi_username' => $r['usuari_domi'] ?: null, 'domi_privilege' => $r['privilegi_domi'] ?: null,
                    'active' => true,
                ];
                if ($existent) { $existent->update($dades); $usuari = $existent; $actualitzats++; }
                else {
                    // Contrasenya temporal amb canvi obligatori; MAI ve de l'Excel.
                    $dades['password'] = Hash::make(PasswordPolicy::genera());
                    $dades['must_change_password'] = true;
                    $usuari = User::create($dades);
                    $creats++;
                }

                /* Lot territorial i departament. Es reescriuen només si la cel·la porta
                   alguna cosa: una cel·la BUIDA vol dir «no ho toquis», no «treu-li'ls».
                   En una reimportació parcial, entendre-ho al revés desassignaria mitja
                   plantilla sense que ningú ho demanés. */
                if ($r['lot'] !== '') {
                    $usuari->lots()->sync(
                        \App\Models\Lot::whereIn('code', self::llista($r['lot']))->pluck('id')
                    );
                }
                if ($r['departament'] !== '') {
                    $usuari->departments()->sync(
                        \App\Models\Department::whereIn('code', self::llista($r['departament']))->pluck('id')
                    );
                }
            }
        });

        /* QUANTS ES QUEDEN SENSE COMPTE A DOMI. L'import NO aprovisiona: vuitanta crides
           HTTP no caben dins d'una petició web i qualsevol timeout deixaria la càrrega a
           mitges. Però callar-ho seria pitjor: es tancaria la pantalla creient que la
           plantilla ja hi és a les dues apps, i a domi no hi hauria ningú. */
        $pendents = User::where('role', 'worker')
            ->where('work_type', 'like', 'DOMICILIARIA%')
            ->whereNull('domi_provisioned_at')
            ->where('active', true)->count();

        $avis = $pendents > 0
            ? " ATENCIÓ: $pendents professional(s) domiciliari(s) encara NO tenen compte a domi. "
              . 'Creeu-los des de la fitxa de cadascú (botó «Crear compte a domi») o tots de cop amb '
              . '`php artisan domi:provisiona-pendents --apply`.'
            : '';

        return response()->json(['ok' => true, 'creats' => $creats, 'actualitzats' => $actualitzats,
            'pendents_domi' => $pendents,
            'message' => "Importació correcta: $creats altes, $actualitzats actualitzacions. "
                . 'Les contrasenyes es generen al reinici/alta i s\'envien per email.' . $avis]);
    }

    /** Cel·la amb valors separats per ';' -> llista neta i en majúscules (els codis ho són). */
    private static function llista(string $cel): array
    {
        return array_values(array_filter(array_map(
            fn ($x) => strtoupper(trim($x)), explode(';', $cel)
        ), fn ($x) => $x !== ''));
    }

    /** Valida una fila. @return string[] llista d'errors (buida si és vàlida). */
    private function validaFila(array $r): array
    {
        $e = [];
        if ($r['nom_complet'] === '') $e[] = 'falta el nom';
        if ($r['dni'] === '') $e[] = 'falta el DNI';
        if ($r['email'] === '' || ! filter_var($r['email'], FILTER_VALIDATE_EMAIL)) $e[] = 'email invàlid';
        if (mb_strlen($r['usuari_domi']) > 15) $e[] = 'usuari domi > 15 caràcters';

        /* Un codi de lot o departament mal escrit NO es pot ignorar en silenci: la persona
           entraria sense assignació i quedaria fora de tots els filtres del quadre de
           disponibilitat, o sigui invisible per a qui reparteix la feina. Val més que
           l'import falli i es corregeixi l'Excel. */
        foreach ([['lot', \App\Models\Lot::class], ['departament', \App\Models\Department::class]] as [$camp, $model]) {
            if ($r[$camp] === '') { continue; }
            $demanats = self::llista($r[$camp]);
            $existents = $model::whereIn('code', $demanats)->pluck('code')->all();
            $desconeguts = array_diff($demanats, $existents);
            if ($desconeguts) {
                $e[] = $camp . ' desconegut: ' . implode(', ', $desconeguts)
                     . ' (vàlids: ' . implode(' · ', $model::orderBy('sort')->pluck('code')->all()) . ')';
            }
        }
        if ($r['data_naixement'] !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['data_naixement'])) $e[] = 'data naixement ha de ser AAAA-MM-DD';
        foreach (self::ENUMS as $camp => $valids) {
            if ($r[$camp] !== '' && ! in_array($r[$camp], $valids, true)) $e[] = "$camp='{$r[$camp]}' no és vàlid (opcions: " . implode(', ', $valids) . ')';
        }
        if ($r['relacio'] === 'autonom' && $r['disponibilitat_h'] === '') $e[] = 'un autònom necessita disponibilitat (h/set)';
        if ($r['disponibilitat_h'] !== '' && ! is_numeric($r['disponibilitat_h'])) $e[] = 'disponibilitat ha de ser un número';
        if ($r['jornada_setmanal_h'] !== '' && ! is_numeric($r['jornada_setmanal_h'])) $e[] = 'jornada ha de ser un número';
        return $e;
    }
}
