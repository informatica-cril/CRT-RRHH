<?php
/* tools/usuaris-sintetics.php — substitueix les persones de la base de desenvolupament per
 * comptes ficticis coherents amb els de CRT Domiciliària.
 *
 * ENCÀRREC (Direcció, 03-08-2026): «Aixeca CRT RRHH amb un quadre de credencials
 * coherent.» L'avaluació d'impacte afirma que **les aplicacions no contenen cap dada
 * personal**, i això inclou aquesta: la taula `users` tenia 80 persones reals amb nom, correu
 * corporatiu i DNI.
 *
 * ── PER QUÈ HA DE SER COHERENT AMB DOMI, I NO UN JOC A PART ─────────────────────────
 *   Les dues aplicacions s'integren: domi demana a RRHH els dies de no disponibilitat de cada
 *   professional, i la correspondència es fa pel **nom d'usuari**. Amb dos jocs de comptes
 *   independents la crida respon 200 i **no retorna ningú**, que és una manera de fallar que
 *   sembla que funcioni.
 *
 *   Per això els noms d'usuari són exactament els mateixos que a domi —`fisio1`, `coord1`,
 *   `direccio`…— i la contrasenya també. Un sol quadre de credencials per a les dues.
 *
 * ⚠️ Abans d'executar-ho cal una còpia de `portal_empleado`. La de referència és
 *    `~/arxiu-crt/bd-abans-de-buidar/portal_empleado-COMPLETA-*.sql.gz`.
 *
 * Ús:  php tools/usuaris-sintetics.php            assaig
 *      php tools/usuaris-sintetics.php --executa  substitueix
 */

if (php_sapi_name() !== 'cli') { http_response_code(403); exit("Només CLI.\n"); }

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

$executa = in_array('--executa', $argv, true);

/** Mateixa contrasenya que a CRT Domiciliària: un sol quadre per a les dues. */
const CONTRASENYA = 'Proves2026!';

/**
 * Els comptes. El nom d'usuari coincideix amb el de domi, que és el que fa que la
 * sincronització de disponibilitat trobi la persona.
 *
 * `relacio` distingeix laboral d'autònom: l'autònom no fitxa, no té límit d'hores
 * complementàries i autogestiona la seva disponibilitat. Se'n posa un a posta perquè aquell
 * camí també quedi cobert per les proves.
 */
/**
 * `work_type` no és decoratiu: **l'API que consulta domi filtra per
 * `work_type LIKE 'DOMICILIARIA%'`**. Un fisio sense aquest valor no surt a la plantilla, la
 * crida respon 200 amb zero treballadors i domi diu que l'usuari «no s'ha pogut vincular».
 * És l'error que va aparèixer la primera vegada.
 */
const COMPTES = [
    /* usuari, càrrec, rol, relació, work_type, codis postals */
    ['direccio',   'Direcció',            'admin',   'laboral', 'ESTRUCTURA',   null],
    ['admin1',     'Administració',       'admin',   'laboral', 'ESTRUCTURA',   null],
    ['rrhh1',      'Recursos Humans',     'hr',      'laboral', 'ESTRUCTURA',   null],
    ['coord1',     'Coordinació',         'worker',  'laboral', 'ESTRUCTURA',   null],
    ['valorador1', 'Valoració',           'worker',  'laboral', 'ESTRUCTURA',   null],
    ['codi1',      'Codificació',         'worker',  'laboral', 'ESTRUCTURA',   null],
    ['atencio1',   'Atenció al client',   'worker',  'laboral', 'ESTRUCTURA',   null],
    ['tpo1',       'Teràpia ocupacional', 'worker',  'laboral', 'ESTRUCTURA',   null],
    /* `job_profile` ha de ser EXACTAMENT 'Fisioterapeuta': domi filtra la plantilla amb una
       comparació estricta contra aquesta constant (RRHH_JOB a includes/rrhh_client.php).
       Qualsevol altre text —encara que sigui el mateix ofici escrit d'una altra manera— deixa
       la persona fora i domi diu que no s'ha pogut vincular. */
    ['fisio1',     'Fisioterapeuta',      'worker',  'laboral', 'DOMICILIARIA', '08001,08004'],
    ['fisio2',     'Fisioterapeuta',      'worker',  'laboral', 'DOMICILIARIA', '08014,08028'],
    ['fisio3',     'Fisioterapeuta',      'worker',  'autonom', 'DOMICILIARIA', '08191,08172'],
    /* Compte de SERVEI: no és una persona. És el que autentica les crides que CRT
       Domiciliària fa a l'API de disponibilitat (middleware `role:service`). Sense ell, la
       integració respon 401 i domi es queda sense saber qui està absent —i com que la taula
       pròpia de domi queda buida, programaria visites a gent de vacances. */
    ['integracio', 'Integració domi',     'service', 'laboral', 'ESTRUCTURA',   null],
];

const NOMS = ['Antoni Vidal Rodríguez', 'Núria Rodríguez Costa', 'Marta Bonet Grau',
              'Francesc Serra Torres', 'Cristina Oliva Solé', 'Francesc Vidal Camps',
              'Antoni Roca Roig', 'Sílvia Mas Roca', 'Antoni Prat Pujol',
              'Anna Roca Sánchez', 'Àngels Mas Puig', 'Integració CRT Domiciliària'];

/** DNI fictici amb la lletra calculada: el rang 9xxxxxxx no s'assigna a persones. */
function dni_fictici(int $i): string
{
    $n = 90000000 + $i;
    return $n . substr('TRWAGMYFPDXBNJZSQVHLCKE', $n % 23, 1);
}

echo "\nUSUARIS SINTÈTICS · CRT RRHH\n" . str_repeat('═', 78) . "\n";
echo $executa ? "Mode: EXECUTA\n\n" : "Mode: assaig. Res es toca. Afegeix --executa.\n\n";

$actuals = DB::table('users')->count();
$reals   = DB::table('users')->where('email', 'like', '%@crtbcn.cat')->count();
printf("Usuaris actuals: %d  ·  amb correu corporatiu real: %d\n", $actuals, $reals);
printf("Es crearan: %d comptes ficticis, contrasenya «%s»\n", count(COMPTES), CONTRASENYA);

if (!$executa) {
    echo "\n⚠️  Això ESBORRA els usuaris actuals. Comprova que tens la còpia de\n";
    echo "    portal_empleado abans d'executar-ho.\n\n";
    exit(0);
}

$hash = Hash::make(CONTRASENYA);

DB::transaction(function () use ($hash) {
    /* Es desactiven les claus foranes: `users` la referencien moltes taules i endevinar
       l'ordre de buidatge amb 53 taules és una font d'errors sense contrapartida. */
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    DB::table('users')->delete();

    /* Una jornada real de la taula: sense `work_schedule_id` la plantilla surt sense horari
       i l'enrutador de domi no pot programar res. */
    $jornada = DB::table('work_schedules')->value('id');

    foreach (COMPTES as $i => [$user, $carrec, $rol, $relacio, $workType, $cps]) {
        DB::table('users')->insert([
            'id'                    => $i + 1,
            'name'                  => NOMS[$i] ?? ('Usuari ' . ($i + 1)),
            'email'                 => $user . '@exemple.invalid',
            'dni'                   => dni_fictici(100 + $i),
            'password'              => $hash,
            'role'                  => $rol,
            'relacio'               => $relacio,
            'job_profile'           => $carrec,
            'work_type'             => $workType,
            'postal_code_assigned'  => $cps,
            /* L'autònom no té jornada assignada: no fitxa i autogestiona la disponibilitat. */
            'work_schedule_id'      => ($workType === 'DOMICILIARIA' && $relacio === 'laboral') ? $jornada : null,
            'active'                => 1,
            'must_change_password'  => 0,
        ]);
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
});

/* ── ZONES ASSIGNADES ────────────────────────────────────────────────────────────
   Les zones de treball no són un adorn: **limiten quins pacients pot rebre cada
   professional**. domi les llegeix de RRHH (rrhh_sync_perfils) i les fa servir tant a
   l'atribució de pacient com a l'enrutador.

   El camp `postal_code_assigned` de la fitxa NO és la font: la font és la taula `zones`
   amb la seva assignació a `zone_worker`. Deixar-les òrfenes fa que domi sincronitzi
   «3 fisios» i insereixi zero zones, i llavors l'atribució no pot acotar res. */
$zones = DB::table('zones')->where('active', true)->get();
DB::table('zone_worker')->delete();

/* ── Repartiment SENSE SOLAPAMENT ────────────────────────────────────────────────
   Cada zona té UN titular. Assignar-ne diverses al mateix lloc faria que l'atribució
   pogués triar qualsevol dels dos i que la limitació per zona no limités res: el que es
   vol provar és precisament que un pacient d'una zona **no** pot anar a un professional
   que no la cobreix. */
$avui  = now()->toDateString();
$fisios = [];
foreach (COMPTES as $i => [$user, , , , $workType]) {
    if ($workType === 'DOMICILIARIA') { $fisios[] = ['id' => $i + 1, 'user' => $user]; }
}

$nZones = 0;
foreach ($zones->values() as $k => $z) {
    $f = $fisios[$k % count($fisios)];          // repartiment rodó: un titular per zona
    DB::table('zone_worker')->insert([
        'user_id'    => $f['id'],
        'zone_id'    => $z->id,
        'valid_from' => $avui,
        'valid_to'   => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $nZones++;
}
echo "  $nZones zones assignades als fisios\n";

/* ── Token del compte de servei ──────────────────────────────────────────────────
   Es regenera aquí perquè els tokens antics apuntaven a usuaris que ja no existeixen. El
   valor ha de coincidir amb RRHH_TOKEN d'includes/rrhh_secret.php a domi. */
DB::table('personal_access_tokens')->delete();
$servei = DB::table('users')->where('role', 'service')->first();
if ($servei) {
    /* Format de Sanctum: `<id de la fila>|<text pla>`. Es desa el hash NOMÉS de la part de
       després de la barra, i la fila ha de tenir exactament aquell id: Sanctum busca primer
       per id i després compara el hash. Inserir-lo sencer, o amb un id qualsevol, dona un 401
       que sembla un problema de permisos i no ho és. */
    $tokenPla = getenv('RRHH_TOKEN_NOU') ?: null;
    if ($tokenPla && strpos($tokenPla, '|') !== false) {
        [$tokenId, $tokenSecret] = explode('|', $tokenPla, 2);
        DB::table('personal_access_tokens')->insert([
            'id'             => (int) $tokenId,
            'tokenable_type' => 'App\\Models\\User',
            'tokenable_id'   => $servei->id,
            'name'           => 'domi-sync',
            'token'          => hash('sha256', $tokenSecret),
            /* Mateix abast i mateixa caducitat que domi:service-account. Amb ["*"] i
               sense caducitat, cada re-sembra tornava a obrir el bitllet de bat a bat. */
            'abilities'      => json_encode(\App\Support\DomiScopes::permisos()),
            'expires_at'     => now()->addDays(\App\Console\Commands\DomiServiceAccount::VIDA_DIES),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        echo "  token del compte de servei regenerat (id $tokenId, acotat i amb caducitat)\n";
    } else {
        echo "  ⚠️  passa RRHH_TOKEN_NOU=<token> per regenerar el token del compte de servei\n";
    }
}

/* ── Comprovació: no n'hi ha prou d'haver inserit sense error ───────────────────── */
$n      = DB::table('users')->count();
$corp   = DB::table('users')->where('email', 'like', '%@crtbcn.cat')->count();
$fora   = DB::table('users')->where('email', 'not like', '%@exemple.invalid')->count();
$provaP = Hash::check(CONTRASENYA, (string) DB::table('users')->where('email', 'fisio1@exemple.invalid')->value('password'));

echo "\n── Comprovació ──────────────────────────────────────────────────────────\n";
printf("  %d usuaris\n", $n);
printf("  %d amb correu corporatiu real (ha de ser 0)\n", $corp);
printf("  %d amb correu fora del domini fictici (ha de ser 0)\n", $fora);
printf("  contrasenya verificada contra el hash: %s\n", $provaP ? 'correcte' : 'NO COINCIDEIX');

$ok = ($n === count(COMPTES) && $corp === 0 && $fora === 0 && $provaP);
echo "\n" . str_repeat('═', 78) . "\n";
echo $ok ? "✔ Usuaris sintètics creats i verificats.\n\n"
         : "✗ La comprovació NO passa. Revisa-ho abans de fer-ho servir.\n\n";
exit($ok ? 0 : 1);
