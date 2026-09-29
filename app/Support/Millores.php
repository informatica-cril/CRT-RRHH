<?php

namespace App\Support;

use App\Models\DisciplinaryCase;
use App\Services\IaLocal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Millores
{
    public const AMBITS = ['expedient', 'rlt', 'jornada', 'plantilla', 'programacio', 'assistencial', 'tecnic'];

    public static function senyalsRrhh(): array
    {
        $s = [];

        $s['expedients_oberts'] = DisciplinaryCase::whereNotIn('estat', ['resolt', 'executat', 'arxivat'])->count();
        $s['expedients_sense_moviment_15d'] = DisciplinaryCase::whereNotIn('estat', ['resolt', 'executat', 'arxivat'])
            ->where('updated_at', '<', now()->subDays(15))->count();
        $s['sancions_molt_greus_sense_informar_rlt'] = DisciplinaryCase::where('gravetat', 'molt_greu')
            ->whereNotNull('resolucio_tipus')->where('resolucio_tipus', '!=', 'arxiu')
            ->whereNull('rlt_informat_ts')->count();

        $s['escrits_rlt_esborrany'] = DB::table('rlt_reports')->where('estat', 'esborrany')->count();
        $s['escrits_rlt_signats_sense_lliurar'] = DB::table('rlt_reports')->where('estat', 'signat')->count();
        $s['mai_sha_lliurat_informe_previ'] = DB::table('rlt_reports')
            ->where('tipus', 'sistemes')->where('estat', 'lliurat')->count() === 0 ? 1 : 0;

        $s['registres_horaris_pendents'] = DB::table('work_logs')->where('status', 'pending')->count();
        $s['registres_pendents_mes_7d'] = DB::table('work_logs')->where('status', 'pending')
            ->where('date', '<', now()->subDays(7)->toDateString())->count();
        $s['jornades_sense_hores_efectives'] = DB::table('work_logs')
            ->whereNull('effective_hours')->whereNotNull('end_time')->count();

        $s['treballadors_sense_dni'] = DB::table('users')->where('role', 'worker')
            ->where(fn ($q) => $q->whereNull('dni')->orWhere('dni', ''))->count();
        $s['dni_duplicats'] = DB::table('users')->select('dni')->where('role', 'worker')
            ->whereNotNull('dni')->where('dni', '!=', '')
            ->groupBy('dni')->havingRaw('COUNT(*) > 1')->get()->count();

        $s['errors_aplicacio_7d'] = self::errorsRecents();

        return $s;
    }

    private static function errorsRecents(int $max = 8): array
    {
        $fitxer = storage_path('logs/laravel.log');
        if (! is_readable($fitxer)) {
            return [];
        }
        $mida = filesize($fitxer);
        $fh = fopen($fitxer, 'r');
        if (! $fh) {
            return [];
        }
        fseek($fh, max(0, $mida - 220000));
        $text = (string) fread($fh, 220000);
        fclose($fh);

        $comptes = [];
        foreach (explode("\n", $text) as $linia) {
            if (! preg_match('/\.(ERROR|CRITICAL):\s*(.{0,160})/', $linia, $m)) {
                continue;
            }
            $clau = preg_replace('/\d+/', 'N', trim($m[2]));
            $clau = preg_replace('/[\'"][^\'"]{0,80}[\'"]/', 'X', (string) $clau);
            $comptes[$clau] = ($comptes[$clau] ?? 0) + 1;
        }
        arsort($comptes);

        $out = [];
        foreach (array_slice($comptes, 0, $max, true) as $k => $n) {
            $out[] = ['error' => $k, 'vegades' => $n];
        }

        return $out;
    }

    public static function senyalsServeis(): array
    {
        $out = [];
        foreach (DB::table('servei_senyals')->orderBy('servei')->get() as $r) {
            $out[$r->servei][$r->clau] = json_decode($r->valor, true);
        }

        return $out;
    }

    public static function context(): array
    {
        return [
            'data' => now()->toDateString(),
            'rrhh' => self::senyalsRrhh(),
            'serveis' => self::senyalsServeis(),
        ];
    }

    public static function arquitectura(): array
    {
        return [
            'rrhh' => [
                'que_es' => 'Laravel 10 amb API a routes/api.php i SPA Vue 3 a src/ (atencio: app/src/ es una copia vella que NO es serveix).',
                'peces' => 'Controladors a app/Http/Controllers/Api, logica a app/Support i app/Services, vistes a src/views, menu a src/components/layout/Sidebar.vue, rutes de la SPA a src/router/index.js.',
                'taules' => 'users, work_logs, work_log_segments, absences, absence_types, holidays, documents, document_signatures, disciplinary_cases, disciplinary_elements, disciplinary_fault_types, rlt_reports, millores, servei_parametres, servei_senyals, domi_jornada_pla, domi_rendiment.',
                'canvis' => 'Un canvi de dades necessita migracio a database/migrations. Un canvi de pantalla toca src/views i cal recompilar amb vite build.',
            ],
            'domi' => [
                'que_es' => 'PHP 8 sense framework. Pantalles a modulos/<Perfil>/, logica compartida a includes/, endpoints a api/, tasques a cronjobsDomi/.',
                'peces' => 'includes/planificacio.php (planificador i rutes), includes/bossa_hores.php (jornada anual), includes/flexibilitat.php (article 74), includes/rendiment.php, includes/ia_planificador.php, api/routing.php (OSRM).',
                'taules' => 'derivaciones, pacientes, events, admin, crt_sessio_planificada, crt_disponibilitat, crt_registre_hores, crt_flexibilitat, crt_ruta_motor, crt_ia_proposta, crt_avis_coordinacio.',
                'canvis' => 'Les taules noves van a tools/*.sql i les executa informatica. Els menus son a php/<Perfil>.php. La base de dades es utf8mb4_spanish2_ci.',
            ],
            'desplegament' => 'Rama unica per repositori. Informatica fa git pull, composer install i php artisan migrate al servidor. Qualsevol push es desplegable.',
        ];
    }

    private static function sistema(): string
    {
        return "Ets l'observatori de millora de CRT, el conjunt d'aplicacions d'una empresa de rehabilitacio "
             . "de Catalunya: RRHH (modul laboral transversal), domiciliaria, logopedia domiciliaria i ambulatori.\n\n"
             . "Se't donen INDICADORS reals. Proposa millores CONCRETES del funcionament o del programari.\n\n"
             . "REGLES:\n"
             . "- Cada proposta ha de portar EVIDENCIA: l'indicador exacte i la xifra en que et bases. Sense xifra, "
             . "no la proposis.\n"
             . "- No proposis res sobre persones concretes ni sobre el seu rendiment: parla de processos, pantalles "
             . "i circuits.\n"
             . "- Si un indicador es zero o esta be, no facis soroll: val mes tornar poques propostes bones.\n"
             . "- Prioritza el que te consequencia legal o assistencial per damunt del que es comoditat.\n\n"
             . "Aquesta llista es una CUA DE FEINA per al departament d'informatica, que es qui escriura el codi. "
             . "Per tant cada proposta ha de servir per treballar-hi sense haver de preguntar res:\n"
             . "- 'proposta': que s'ha de fer, en llenguatge planer, per a qui decideix.\n"
             . "- 'detall_tecnic': el mateix en termes de codi, fent servir el MAPA que et donen: quins fitxers, "
             . "taules, pantalles o endpoints cal tocar i quin enfocament seguir. No inventis noms de fitxer ni de "
             . "taula que no surtin al mapa; si no ho saps, digues que cal localitzar-ho.\n"
             . "- 'hores_estimades': una estimacio realista d'hores de feina per a una persona que ja coneix el "
             . "codi. Un numero, amb decimals si cal.\n\n"
             . "Respon NOMES amb un array JSON. Cada element:\n"
             . "{\"servei\":\"rrhh|domi|logo_domi|ambu\",\"ambit\":\"" . implode('|', self::AMBITS) . "\","
             . "\"titol\":\"<max 90 caracters>\",\"problema\":\"<que passa>\",\"proposta\":\"<que fer, planer>\","
             . "\"detall_tecnic\":\"<que tocar, en termes de codi>\",\"evidencia\":\"<indicador i xifra>\","
             . "\"impacte\":\"alt|mitja|baix\",\"esforc\":\"petit|mitja|gran\",\"hores_estimades\":<numero>}\n"
             . "Maxim 6 propostes. Si no hi ha res que valgui la pena, torna [].";
    }

    public static function genera(int $max = 6): array
    {
        $ia = new IaLocal();
        if (! $ia->disponible()) {
            return ['ok' => false, 'motiu' => 'IA fora de servei', 'noves' => 0];
        }

        $ctx = self::context();
        $ctx['mapa_del_codi'] = self::arquitectura();
        $r = $ia->xat(self::sistema(), json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                      ['max_tokens' => 2000]);
        if (! $r['ok']) {
            return ['ok' => false, 'motiu' => $r['error'] ?? 'resposta buida', 'noves' => 0];
        }

        $llista = self::json($r['text']);
        if (! is_array($llista)) {
            Log::warning('[Millores] resposta no interpretable');

            return ['ok' => false, 'motiu' => 'Resposta no interpretable', 'noves' => 0];
        }

        $noves = 0;
        foreach (array_slice($llista, 0, $max) as $p) {
            if (! is_array($p) || empty($p['titol']) || empty($p['evidencia'])) {
                continue;
            }
            $existeix = DB::table('millores')->where('titol', mb_substr($p['titol'], 0, 200))
                ->whereIn('estat', ['nova', 'acceptada', 'preparada'])->exists();
            if ($existeix) {
                continue;
            }
            DB::table('millores')->insert([
                'servei' => in_array($p['servei'] ?? '', ['rrhh', 'domi', 'logo_domi', 'ambu'], true) ? $p['servei'] : 'rrhh',
                'ambit' => in_array($p['ambit'] ?? '', self::AMBITS, true) ? $p['ambit'] : 'tecnic',
                'titol' => mb_substr((string) $p['titol'], 0, 200),
                'problema' => (string) ($p['problema'] ?? ''),
                'proposta' => (string) ($p['proposta'] ?? ''),
                'detall_tecnic' => (string) ($p['detall_tecnic'] ?? ''),
                'evidencia' => (string) $p['evidencia'],
                'hores_ia' => isset($p['hores_estimades']) ? max(0, min(999, (float) $p['hores_estimades'])) : null,
                'impacte' => in_array($p['impacte'] ?? '', ['alt', 'mitja', 'baix'], true) ? $p['impacte'] : 'mitja',
                'esforc' => in_array($p['esforc'] ?? '', ['petit', 'mitja', 'gran'], true) ? $p['esforc'] : 'mitja',
                'estat' => 'nova',
                'model' => $r['model'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $noves++;
        }

        return ['ok' => true, 'motiu' => '', 'noves' => $noves, 'ms' => $r['ms']];
    }

    private static function json(string $text): ?array
    {
        $i = strpos($text, '[');
        $j = strrpos($text, ']');
        if ($i === false || $j === false || $j <= $i) {
            return null;
        }
        $d = json_decode(substr($text, $i, $j - $i + 1), true);

        return is_array($d) ? $d : null;
    }
}
