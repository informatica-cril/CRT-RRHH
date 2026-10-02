<?php

use App\Http\Controllers\Api\AbsenceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthCodeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DomiIntegrationController;
use App\Http\Controllers\Api\DocumentSignatureController;
use App\Http\Controllers\Api\ExcedenciaController;
use App\Http\Controllers\Api\GeolocationController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\WorkLocationController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\AmbulatoryCenterController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\SpecialtyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkLogController;
use App\Http\Controllers\Api\WorkLogSegmentController;
use App\Http\Controllers\Api\WorkLogModificationController;
use App\Http\Controllers\Api\WorkLogAlertController;
use App\Http\Controllers\Api\BreakSettingController;
use App\Http\Controllers\Api\MailSettingController;
use App\Http\Controllers\Api\CertifiedMailController;
use App\Http\Controllers\Api\WorkScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // Sonda de salut (health-check post-desplegament). Pública, sense dades: confirma
    // que l'API respon i que la BD contesta. La usa el workflow de deploy.
    Route::get('health', function () {
        try { \Illuminate\Support\Facades\DB::select('SELECT 1'); $db = true; }
        catch (\Throwable $e) { $db = false; }
        return response()->json(['ok' => $db, 'service' => 'crt-rrhh-api', 'ts' => now()->toIso8601String()],
            $db ? 200 : 503);
    });

    // Aprovisionament de dispositiu corporatiu (Hexnode): clau → token HMAC.
    // Públic: només canvia un secret pel seu HMAC; sense la clau no fa res.
    Route::post('device/enroll', [GeolocationController::class, 'deviceEnroll']);

    // ── Auth ──
    Route::prefix('auth')->group(function () {
        // ENS: throttle contra força bruta. login i reset són els vectors d'atac; es
        // limiten per IP+email. 6 intents/min encaixa amb l'ús humà i frena el guessing.
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
        Route::post('generate-random-password', [AuthController::class, 'generateRandomPassword'])->middleware('throttle:6,1');
        Route::middleware(['auth:sanctum', 'service.readonly'])->group(function () {
            // Route::get('me', [AuthController::class, 'me']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('geo-consent', [AuthController::class, 'geoConsent']);
            // Canvi propi: NO porta pwd.change (ha de ser accessible amb el flag actiu).
            Route::post('change-password', [AuthController::class, 'changePassword']);
            // Segon factor TOTP de l'usuari (un cop l'admin li ha assignat el mètode 'totp').
            // Estat, enrolament, còdis de recuperació i desactivació: tot sobre el compte PROPI.
            Route::get('2fa', [AuthController::class, 'twofaStatus']);
            Route::post('2fa/prepare', [AuthController::class, 'twofaPrepare']);
            Route::post('2fa/confirm', [AuthController::class, 'twofaConfirm']);
            Route::post('2fa/recovery-codes', [AuthController::class, 'twofaRecoveryCodes']);
            Route::delete('2fa', [AuthController::class, 'twofaDisable']);
        });
    });

    // ENS: pwd.change força el canvi d'una contrasenya temporal a TOTA la resta de rutes
    // autenticades (la ruta change-password i logout queden exemptes al propi middleware).
    Route::middleware(['auth:sanctum', 'service.readonly', 'pwd.change'])->group(function () {
        // ── Users ──
        Route::prefix('users')->group(function () {
            // Llistat complet de plantilla → només personal de gestió.
            Route::get('/', [UserController::class, 'index'])->middleware('role:admin,coordinator,hr');
            Route::get('me', [AuthController::class, 'me']);
            // Consum de plantilla (RRHH és font de veritat) — admin, coordinator o compte de servei (domi)
            Route::get('bulk-index', [UserController::class, 'bulkIndex'])->middleware('role:admin,coordinator,service,hr');
            // Plantilla d'alta massiva (Excel de mostra amb capçalera). Admin/HR.
            Route::get('import-template', [\App\Http\Controllers\Api\ImportTemplateController::class, 'download'])->middleware('role:admin,hr');
            // Importar l'Excel d'alta massiva (tot o res). Admin/HR. ?dry=1 només valida.
            Route::post('import', [\App\Http\Controllers\Api\ImportPersonalController::class, 'import'])->middleware('role:admin,hr');
            Route::get('worker-bulk-info', [UserController::class, 'workerBulkInfo']);
            Route::post('/', [UserController::class, 'store'])->middleware('role:admin,hr');
            Route::post('bulk', [UserController::class, 'bulkStore'])->middleware('role:admin,hr');
            /* Crear a domi tots els comptes que falten, des de la pantalla. Va per tandes:
               vegeu UserController::MAX_TANDA. Ha d'anar ABANS de {user} o «provisiona-domi-
               pendents» s'entendria com un identificador d'usuari. */
            Route::post('provisiona-domi-pendents', [UserController::class, 'provisionaDomiPendents'])->middleware('role:admin,hr');
            // Fitxa d'un usuari: el propi titular o personal de gestió.
            Route::get('{user}', [UserController::class, 'show'])->middleware('owner:user');
            // update: qualsevol autenticat, però el controlador limita què pot tocar
            // un no-staff (només el seu perfil bàsic; MAI role/password/altri).
            Route::put('{user}', [UserController::class, 'update']);
            Route::delete('{user}', [UserController::class, 'destroy'])->middleware('role:admin,hr');
            /* ENS: qui determina el segon factor d'un usuari (dispositiu|totp).
               Direcció (01-08-2026): «el 2FA ha de poder activar-lo l'admin O RRHH per a cada
               usuari». Fins ara només l'admin, i això volia dir que donar d'alta algú i
               deixar-lo operatiu eren dues persones diferents.
               Segueix fora de coordinació: decidir com entra algú a l'aplicació és
               administració de comptes, no direcció del dia a dia. */
            Route::put('{user}/2fa', [UserController::class, 'setSecondFactor'])->middleware('role:admin,hr');

            /* Alta sincronitzada a domi d'UNA persona, des de la seva fitxa (31-07-2026).
               L'alta individual ja aprovisiona sola, però la CÀRREGA MASSIVA no: 80 crides
               HTTP no caben dins d'una petició web. Sense això, l'única sortida era que algú
               entrés al servidor a executar `php artisan domi:provisiona-pendents --apply`.
               És idempotent: domi torna el compte que ja tingués. */
            Route::post('{user}/provisiona-domi', [UserController::class, 'provisionaDomi'])->middleware('role:admin,hr');
        });

        /* Política de segon factor (Direcció, 01-08-2026: «vull decidir-ho jo»). Vivia al
           .env i el comandament el tenia informàtica. Només ADMIN: no és administrar un
           compte, és decidir com entra tota la plantilla. */
        Route::get('seguretat/segon-factor', [\App\Http\Controllers\Api\SecondFactorPolicyController::class, 'show'])->middleware('role:admin');
        Route::put('seguretat/segon-factor', [\App\Http\Controllers\Api\SecondFactorPolicyController::class, 'update'])->middleware('role:admin');

        // Catàleg d'especialitats clíniques (per a la fitxa del treballador).
        Route::get('specialties', [SpecialtyController::class, 'index']);

        // Expedient del treballador: mètriques agregades read-only des de domi (API F1). Gestió.
        Route::get('expedient/{professional}', [\App\Http\Controllers\Api\ExpedientController::class, 'show'])->middleware('role:admin,hr');
        Route::get('expedient/{professional}/detall', [\App\Http\Controllers\Api\ExpedientController::class, 'detall'])->middleware('role:admin,hr');

        // Formació obligatòria (llegida de domi per API): estat d'acreditació + diploma/rebut. Gestió.
        Route::get('formacio/acreditacions', [\App\Http\Controllers\Api\FormacioController::class, 'acreditacions'])->middleware('role:admin,hr');
        Route::get('formacio/doc/{tipus}/{usuari}/{rol}', [\App\Http\Controllers\Api\FormacioController::class, 'doc'])->middleware('role:admin,hr')->where('usuari', '[^/]+');

        /* Quadre de disponibilitat setmanal (petició de Direcció, 31-07-2026): qui treballa
           cada dia i en quin horari, a partir de l'horari del contracte i de les absències
           APROVADES. Només admin i hr: la taula ensenya de tota la plantilla alhora el MOTIU
           de cada absència («Baixa mèdica», «Matrimoni»…), que és dada de salut i de vida
           privada. Coordinació necessita saber qui té l'agenda lliure, no per què no hi és. */
        Route::get('disponibilitat', [\App\Http\Controllers\Api\DisponibilitatController::class, 'index'])->middleware('role:admin,hr');
        Route::get('disponibilitat/filtres', [\App\Http\Controllers\Api\DisponibilitatController::class, 'filtres'])->middleware('role:admin,hr');

        // Portal del treballador: escrits disciplinaris notificats + acusament de recepció (in-app).
        Route::get('disciplinary/my-notifications', [\App\Http\Controllers\Api\DisciplinaryController::class, 'myNotifications']);
        Route::post('disciplinary/documents/{document}/acknowledge', [\App\Http\Controllers\Api\DisciplinaryController::class, 'acknowledgeDocument']);
        /* Tràmit d'audiència (ET 55.1, conveni 55.K.6). SENSE middleware de rol: són actes de
           DEFENSA i el controlador els autoritza per fila amb DisciplinaryCasePolicy (només la
           persona expedientada). Si passessin pel grup d'admin/hr, la part acusadora podria
           al·legar o renunciar al termini en nom del treballador, que és el que passava abans. */
        Route::get('disciplinary/my-cases', [\App\Http\Controllers\Api\DisciplinaryController::class, 'myCases']);
        Route::post('disciplinary/cases/{case}/alegacions', [\App\Http\Controllers\Api\DisciplinaryController::class, 'alegacions']);
        Route::post('disciplinary/cases/{case}/renuncia-termini', [\App\Http\Controllers\Api\DisciplinaryController::class, 'renunciaTermini']);

        // ── Conciliació d'identitats domi ↔ RRHH (suggereix; confirma una persona) ──
        Route::get('conciliacio', [\App\Http\Controllers\Api\ConciliacioController::class, 'index'])->middleware('role:admin,hr');
        Route::post('conciliacio', [\App\Http\Controllers\Api\ConciliacioController::class, 'store'])->middleware('role:admin,hr');

        // ── Procediment disciplinari (motor amb garanties; qualificació i signatura humanes) ──
        Route::get('disciplinary/fault-types', [\App\Http\Controllers\Api\DisciplinaryController::class, 'faultTypes'])->middleware('role:admin,hr,coordinator');
        Route::prefix('disciplinary')->middleware('role:admin,hr')->group(function () {
            Route::get('suggestions', [\App\Http\Controllers\Api\DisciplinaryController::class, 'suggestions']);
            Route::get('elements', [\App\Http\Controllers\Api\DisciplinaryController::class, 'elements']);
            Route::post('elements', [\App\Http\Controllers\Api\DisciplinaryController::class, 'storeElement']);
            Route::post('elements/bulk', [\App\Http\Controllers\Api\DisciplinaryController::class, 'bulkElements']);
            // Rehabilitació EXPRESSA d'una prova bloquejada per origen no acreditat: exigeix
            // motivació escrita i queda a l'auditoria. Només admin (no hr): és assumir per escrit
            // una prova que el sistema no pot acreditar.
            Route::post('elements/{element}/rehabilita', [\App\Http\Controllers\Api\DisciplinaryController::class, 'reinstateElement'])
                ->middleware('role:admin');
            Route::get('cases', [\App\Http\Controllers\Api\DisciplinaryController::class, 'index']);
            Route::post('cases', [\App\Http\Controllers\Api\DisciplinaryController::class, 'store']);
            Route::get('cases/{case}', [\App\Http\Controllers\Api\DisciplinaryController::class, 'show']);
            Route::put('cases/{case}', [\App\Http\Controllers\Api\DisciplinaryController::class, 'update']);
            Route::post('cases/{case}/transition', [\App\Http\Controllers\Api\DisciplinaryController::class, 'transition']);
            Route::post('cases/{case}/documents', [\App\Http\Controllers\Api\DisciplinaryController::class, 'generateDocument']);
            Route::put('documents/{document}', [\App\Http\Controllers\Api\DisciplinaryController::class, 'updateDocument']);
            Route::post('documents/{document}/sign', [\App\Http\Controllers\Api\DisciplinaryController::class, 'signDocument']);
            // Verificació de l'escrit signat: si el text s'ha tocat després de la signatura, o si el
            // que es va signar no era el que va generar el motor, aquí canta.
            Route::get('documents/{document}/verify', [\App\Http\Controllers\Api\DisciplinaryController::class, 'verifyDocument']);
        });

        // ── Integració bidireccional amb domi (compte de servei) ──
        // El treballador opera la jornada des de domi; RRHH segueix sent font de veritat.
        // RestrictServiceRole acota el compte de servei a aquestes rutes; role:service fa
        // la lectura inversa i OBLIGATÒRIA: ningú MÉS hi entra. Sense això, qualsevol
        // autenticat podia fitxar (o tancar la jornada) EN NOM d'un altre treballador
        // passant-ne user_id/dni al cos — falsejant el registre horari (art. 34.9 ET).
        /* Pacte d'hores complementàries (art. 12.5 ET). SENSE middleware de rol: l'accepta
           cada persona per a si mateixa, i el controlador agafa l'usuari de l'autenticació,
           no de la petició. No hi ha manera d'acceptar en nom d'un altre. */
        Route::prefix('pacte-complementaries')->group(function () {
            Route::get('estat',   [\App\Http\Controllers\Api\ComplementaryPactController::class, 'estat']);
            Route::post('accepta', [\App\Http\Controllers\Api\ComplementaryPactController::class, 'accepta']);
            Route::post('revoca',  [\App\Http\Controllers\Api\ComplementaryPactController::class, 'revoca']);
            // Modular el % de complementàries d'un treballador (fins al 50% del conveni),
            // amb preavís de 7 dies. Gestió: admin o RRHH.
            Route::post('ratio', [\App\Http\Controllers\Api\ComplementaryPactController::class, 'modulaRatio'])->middleware('role:admin,hr');
            Route::get('info/{user}', [\App\Http\Controllers\Api\ComplementaryPactController::class, 'infoTreballador'])->middleware('role:admin,hr');
        });

        /* Franges complementàries del mes natural següent (04-08-2026). Mateix criteri que el
           pacte: cadascú per a si mateix, l'usuari surt de l'autenticació. La retirada NO
           retira: obre un flux que resol coordinació a domi i torna per la ruta de servei. */
        Route::prefix('franges-complementaries')->group(function () {
            Route::get('estat',    [\App\Http\Controllers\Api\ComplementarySlotController::class, 'estat']);
            Route::post('declara', [\App\Http\Controllers\Api\ComplementarySlotController::class, 'declara']);
            Route::post('retirada', [\App\Http\Controllers\Api\ComplementarySlotController::class, 'retirada']);
        });

        /* Portal d'accés únic (CRT Accés, 04-08-2026): un sol login i un
           llançador amb les apps concedides. El salt es fa amb bitllet d'un sol
           ús; la validació del bitllet viu sota /domi/* pel compte de servei. */
        Route::prefix('portal')->group(function () {
            Route::get('apps', [\App\Http\Controllers\Api\PortalController::class, 'apps']);
            Route::post('launch', [\App\Http\Controllers\Api\PortalController::class, 'launch']);
            Route::get('grants', [\App\Http\Controllers\Api\PortalController::class, 'grants'])->middleware('role:admin,hr');
            Route::post('grants', [\App\Http\Controllers\Api\PortalController::class, 'storeGrant'])->middleware('role:admin,hr');
            Route::delete('grants', [\App\Http\Controllers\Api\PortalController::class, 'destroyGrant'])->middleware('role:admin,hr');
        });

        Route::prefix('domi')->middleware('role:service')->group(function () {
            Route::post('sso/valida', [\App\Http\Controllers\Api\PortalController::class, 'valida']);
            Route::post('jornada/start', [DomiIntegrationController::class, 'clockIn']);
            Route::post('jornada/stop', [DomiIntegrationController::class, 'clockOut']);
            Route::post('hito', [DomiIntegrationController::class, 'hito']);
            Route::post('geovalla', [DomiIntegrationController::class, 'geovalla']);
            /* Sectorització decidida a domi (11-08-2026): les zones/microzones es creen
               allà (CP sencers o subzones) i aquí només s'upserta l'entitat assignable. */
            Route::post('zones', [DomiIntegrationController::class, 'upsertZone']);
            Route::get('vacances', [DomiIntegrationController::class, 'vacancesPendents']);
            // Hores extraordinàries forçades des de Coordinació: crear (pendent) i consultar estat.
            Route::post('extraordinaries', [DomiIntegrationController::class, 'overtimeCreate']);
            Route::get('extraordinaries/{ref}', [DomiIntegrationController::class, 'overtimeStatus']);
            Route::post('complementaries', [DomiIntegrationController::class, 'complementaryCreate']);
            Route::get('complementaries/pacte/{ident}', [DomiIntegrationController::class, 'complementaryPacte']);
            Route::get('break-status/{ident}', [DomiIntegrationController::class, 'breakStatus']);
            Route::get('perfil/{ident}', [DomiIntegrationController::class, 'perfil']);
            Route::get('incidencies-obertes', [DomiIntegrationController::class, 'openIncidents']);
            Route::get('hores', [DomiIntegrationController::class, 'hores']);
            Route::get('festius', [DomiIntegrationController::class, 'festius']);
            Route::post('pla-jornada', [DomiIntegrationController::class, 'plaJornada']);
            Route::post('pausa-dia', [DomiIntegrationController::class, 'pausaDia']);
            Route::post('element-disciplinari', [DomiIntegrationController::class, 'elementDisciplinari']);
            Route::post('rendiment', [DomiIntegrationController::class, 'rendiment']);
            Route::get('sancions-rlt', [DomiIntegrationController::class, 'sancionsRlt']);

            /* Disponibilitat per a la PROGRAMACIÓ (31-07-2026). domi programa laborals I
               autònoms, i decideix les absències amb una taula seva; les absències aprovades
               dels laborals viuen aquí. Sense això, domi pot programar una visita a algú que
               RRHH té de vacances. Es dona el QUE (absent) i l'horari, mai el PER QUÈ. */
            Route::get('disponibilitat', [\App\Http\Controllers\Api\DisponibilitatController::class, 'perDomi']);
            Route::post('complementaries/resolucio', [\App\Http\Controllers\Api\ComplementarySlotController::class, 'resolucio']);
            Route::post('break/start', [DomiIntegrationController::class, 'startBreak']);
            Route::post('break/complete', [DomiIntegrationController::class, 'completeBreak']);
        });

        /* role:service explícit: sense això, /servei/* era l'única part del pont que
           qualsevol usuari autenticat podia escriure (el prefix /domi/* sí que el tenia). */
        Route::prefix('servei')->middleware('role:service')->group(function () {
            Route::post('parametres', [\App\Http\Controllers\Api\ServeiParametresController::class, 'store']);
            Route::post('senyals', [\App\Http\Controllers\Api\ServeiParametresController::class, 'senyals']);
        });

        // ── Work Schedules ──
        Route::prefix('work-schedules')->group(function () {
            Route::get('/', [WorkScheduleController::class, 'index']);
            Route::get('{workSchedule}', [WorkScheduleController::class, 'show']);
            Route::post('/', [WorkScheduleController::class, 'store']);
            Route::put('{workSchedule}', [WorkScheduleController::class, 'update']);
        });

        // ── Work Logs ──
        Route::prefix('work-logs')->group(function () {
            Route::get('/', [WorkLogController::class, 'index'])->middleware('role:admin,coordinator,hr');
            Route::get('user/{userId}', [WorkLogController::class, 'byUser'])->middleware('owner');
            // store/update: el controlador comprova propietat (el propi titular o staff).
            Route::post('/', [WorkLogController::class, 'store']);
            Route::put('{workLog}', [WorkLogController::class, 'update']);
        });

        // ── Location Tracking ── ELIMINAT (C1 EIPD: prohibició TÈCNICA del seguiment
        // continu — cap endpoint accepta punts de posició; la precisió del pilot
        // s'anota server-side al flux d'hitos, sense coordenades).

        // ── Absence Types ── (catàleg: lectura oberta, escriptura només gestió)
        Route::prefix('absence-types')->group(function () {
            Route::get('/', [AbsenceController::class, 'indexTypes']);
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::post('/', [AbsenceController::class, 'storeType']);
                Route::put('{absenceType}', [AbsenceController::class, 'updateType']);
                Route::delete('{absenceType}', [AbsenceController::class, 'deleteType']);
            });
        });

        // ── Absences ──
        Route::prefix('absences')->group(function () {
            Route::get('/', [AbsenceController::class, 'index'])->middleware('role:admin,coordinator,hr');
            Route::get('user/{userId}', [AbsenceController::class, 'byUser'])->middleware('owner');
            // store/bulk: el controlador permet al worker crear NOMÉS per a si mateix.
            Route::post('/', [AbsenceController::class, 'store']);
            Route::post('bulk', [AbsenceController::class, 'bulkStore'])->middleware('role:admin,coordinator,hr');
            // update = aprovació/gestió → només staff.
            Route::put('{absence}', [AbsenceController::class, 'update'])->middleware('role:admin,coordinator,hr');
            Route::get('used-days/{userId}/{typeId}/{year}', [AbsenceController::class, 'usedDays'])->middleware('owner');
            // Part mèdic / justificant: el puja i el baixa el titular o gestió (el
            // controlador comprova la propietat). Fitxer a disc PRIVAT: és dada de salut.
            Route::post('{absence}/justificant', [AbsenceController::class, 'pujaJustificant']);
            Route::get('{absence}/justificant', [AbsenceController::class, 'baixaJustificant']);
        });

        // ── Auth Codes ── (hores complementàries: gestió; 'use' el fa el treballador)
        Route::prefix('auth-codes')->group(function () {
            Route::get('/', [AuthCodeController::class, 'index'])->middleware('role:admin,coordinator,hr');
            Route::get('my', [AuthCodeController::class, 'myCodes']);
            Route::get('complementary-cap', [AuthCodeController::class, 'complementaryCapInfo'])->middleware('role:admin,coordinator,hr');
            Route::get('hours-control', [AuthCodeController::class, 'hoursControl'])->middleware('role:admin,coordinator,hr');
            Route::post('/', [AuthCodeController::class, 'store'])->middleware('role:admin,coordinator,hr');
            Route::post('{authCode}/revoke', [AuthCodeController::class, 'revoke'])->middleware('role:admin,coordinator,hr');
            Route::post('{authCode}/renew', [AuthCodeController::class, 'renew'])->middleware('role:admin,hr');
            Route::post('validate', [AuthCodeController::class, 'validateCode']);
            Route::post('use', [AuthCodeController::class, 'useCode']);
        });
        // Autorització d'hores extraordinàries forçades des de domi (compromís de pagament).
        Route::prefix('overtime-requests')->middleware('role:admin,hr')->group(function () {
            Route::get('/', [AuthCodeController::class, 'overtimeRequests']);
            Route::post('{overtimeRequest}/authorize', [AuthCodeController::class, 'authorizeOvertime']);
            Route::post('{overtimeRequest}/deny', [AuthCodeController::class, 'denyOvertime']);
        });

        // ── Work Locations ── (lectura oberta; escriptura només gestió)
        Route::prefix('work-locations')->group(function () {
            Route::get('/', [WorkLocationController::class, 'index']);
            Route::get('{id}', [WorkLocationController::class, 'show']);
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::post('/', [WorkLocationController::class, 'store']);
                Route::put('{id}', [WorkLocationController::class, 'update']);
                Route::delete('{id}', [WorkLocationController::class, 'destroy']);
                Route::post('{id}/assign', [WorkLocationController::class, 'assignWorker']);
                Route::post('{id}/remove-worker', [WorkLocationController::class, 'removeWorker']);
            });
        });

        // ── Ambulatory Centers ── (lectura oberta: el treballador necessita el catàleg;
        // assignar/desassignar personal és gestió, com a zones i work-locations)
        Route::prefix('ambulatory-centers')->group(function () {
            Route::get('/', [AmbulatoryCenterController::class, 'index']);
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::post('{id}/assign', [AmbulatoryCenterController::class, 'assignToUser']);
                Route::post('{id}/remove-worker', [AmbulatoryCenterController::class, 'removeWorker']);
            });
        });

        // ── Zones ── (lectura oberta; escriptura només gestió)
        Route::prefix('zones')->group(function () {
            Route::get('/', [ZoneController::class, 'index']);
            Route::get('{id}', [ZoneController::class, 'show']);
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::post('/', [ZoneController::class, 'store']);
                Route::put('{id}', [ZoneController::class, 'update']);
                Route::delete('{id}', [ZoneController::class, 'destroy']);
                Route::post('{id}/assign', [ZoneController::class, 'assignWorker']);
                Route::post('{id}/remove-worker', [ZoneController::class, 'removeWorker']);
            });
        });

        // ── Excedencias ──
        Route::prefix('excedencias')->group(function () {
            Route::get('/', [ExcedenciaController::class, 'index'])->middleware('role:admin,coordinator,hr');
            Route::get('types', [ExcedenciaController::class, 'indexTypes']);
            Route::get('user/{userId}', [ExcedenciaController::class, 'byUser'])->middleware('owner');
            Route::post('/', [ExcedenciaController::class, 'store']); // controlador: self o staff
            Route::put('{excedencia}', [ExcedenciaController::class, 'update'])->middleware('role:admin,coordinator,hr');
        });

        // ── Documents ──
        Route::prefix('documents')->group(function () {
            Route::get('user/{userId}', [DocumentController::class, 'forUser'])->middleware('owner');
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::get('/', [DocumentController::class, 'index']);
                Route::get('{document}', [DocumentController::class, 'show']);
                Route::post('/', [DocumentController::class, 'store']);
                Route::put('{document}', [DocumentController::class, 'update']);
                Route::delete('{document}', [DocumentController::class, 'destroy']);
                Route::get('{document}/pending-signatures', [DocumentController::class, 'pendingSignatures']);
            });
        });

        // ── Privadesa: text vigent i exercici de drets (RGPD art. 12 a 22) ──
        // El text i les sol·licituds pròpies són de qualsevol persona autenticada: informar-se i
        // exercir els drets propis no depèn del rol. Resoldre-les, sí.
        Route::prefix('privacy')->group(function () {
            Route::get('policy', [\App\Http\Controllers\Api\PrivacyController::class, 'policy']);
            Route::get('my-requests', [\App\Http\Controllers\Api\PrivacyController::class, 'myRequests']);
            Route::post('requests', [\App\Http\Controllers\Api\PrivacyController::class, 'storeRequest']);
            Route::middleware('role:admin,hr')->group(function () {
                Route::get('requests', [\App\Http\Controllers\Api\PrivacyController::class, 'index']);
                Route::post('requests/{sollicitud}/respond', [\App\Http\Controllers\Api\PrivacyController::class, 'respond']);
            });
        });

        // Recompte de la Safata per a la campana de la capçalera (només COUNTs).
        Route::get('safata/resum', [\App\Http\Controllers\Api\SafataController::class, 'resum'])->middleware('role:admin,coordinator,hr');

        // ── Representació legal: crèdit horari (art. 68.e ET) ──
        // Les hores les registra la persona representant (el controlador comprova el mandat);
        // validar-les i gestionar els mandats és d'admin/hr.
        Route::prefix('comite')->group(function () {
            Route::get('me', [\App\Http\Controllers\Api\ComiteController::class, 'me']);
            Route::get('me/hores', [\App\Http\Controllers\Api\ComiteController::class, 'mevesHores']);
            Route::post('hores', [\App\Http\Controllers\Api\ComiteController::class, 'store']);
            Route::delete('hores/{hora}', [\App\Http\Controllers\Api\ComiteController::class, 'destroy']);
            Route::post('hores/{hora}/justificant', [\App\Http\Controllers\Api\ComiteController::class, 'pujaJustificant']);
            Route::get('hores/{hora}/justificant', [\App\Http\Controllers\Api\ComiteController::class, 'baixaJustificant']);
            Route::middleware('role:admin,hr')->group(function () {
                Route::get('membres', [\App\Http\Controllers\Api\ComiteController::class, 'membres']);
                Route::post('membres', [\App\Http\Controllers\Api\ComiteController::class, 'storeMembre']);
                Route::put('membres/{membre}', [\App\Http\Controllers\Api\ComiteController::class, 'updateMembre']);
                Route::delete('membres/{membre}', [\App\Http\Controllers\Api\ComiteController::class, 'destroyMembre']);
                Route::get('hores', [\App\Http\Controllers\Api\ComiteController::class, 'hores']);
                Route::post('hores/{hora}/valida', [\App\Http\Controllers\Api\ComiteController::class, 'valida']);
                Route::post('hores/{hora}/rebutja', [\App\Http\Controllers\Api\ComiteController::class, 'rebutja']);
                Route::get('resum', [\App\Http\Controllers\Api\ComiteController::class, 'resumMensual']);
            });
        });

        // ── Compliment / Governança (constància interna: publicació + acusament) ──
        Route::prefix('compliance')->group(function () {
            // Treballador: documents pendents d'acusar + acusar-ne recepció (constància art. 90).
            Route::get('pending', [\App\Http\Controllers\Api\ComplianceController::class, 'pending']);
            Route::post('{document}/acknowledge', [\App\Http\Controllers\Api\ComplianceController::class, 'acknowledge']);
            // Gestió (admin/hr).
            Route::middleware('role:admin,hr')->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\ComplianceController::class, 'index']);
                Route::post('/', [\App\Http\Controllers\Api\ComplianceController::class, 'store']);
                Route::get('{document}', [\App\Http\Controllers\Api\ComplianceController::class, 'show']);
                Route::put('{document}', [\App\Http\Controllers\Api\ComplianceController::class, 'update']);
                Route::post('{document}/publish', [\App\Http\Controllers\Api\ComplianceController::class, 'publish']);
                Route::post('{document}/archive', [\App\Http\Controllers\Api\ComplianceController::class, 'archive']);
                Route::get('{document}/status', [\App\Http\Controllers\Api\ComplianceController::class, 'acknowledgementsStatus']);
            });
        });

        // ── Integritat / Segell FNMT (seguretat: només admin) ──
        Route::prefix('integrity')->middleware('role:admin')->group(function () {
            Route::get('seals', [\App\Http\Controllers\Api\IntegrityController::class, 'index']);
            Route::get('seals/{seal}', [\App\Http\Controllers\Api\IntegrityController::class, 'show']);
            Route::get('seals/{seal}/verify', [\App\Http\Controllers\Api\IntegrityController::class, 'verify']);
            Route::post('seal-now', [\App\Http\Controllers\Api\IntegrityController::class, 'sealNow']);
        });

        // ── Geolocation ──
        Route::prefix('geolocation')->group(function () {
            // Informe del pilot per a la validació del radi (EIPD §6.1.bis) — només admin
            Route::get('pilot-radi', [GeolocationController::class, 'pilotRadi'])->middleware('role:admin');
            Route::get('ambulatory-points/{userId}', [GeolocationController::class, 'ambulatoryPoints'])->middleware('owner');
            Route::get('municipal-assignment/{userId}', [GeolocationController::class, 'municipalAssignment'])->middleware('owner');
            Route::get('active-zone/{userId}', [GeolocationController::class, 'municipalAssignment'])->middleware('owner'); // Shim
        });

        // ── Compatibility Shims ──
        Route::get('cp-assignments/active/{userId}', [GeolocationController::class, 'municipalAssignment'])->middleware('owner');
        Route::get('municipal-assignments/active/{userId}', [GeolocationController::class, 'municipalAssignment'])->middleware('owner');

        // ── Document Signatures ──
        Route::prefix('document-signatures')->group(function () {
            Route::get('user/{userId}', [DocumentSignatureController::class, 'byUser'])->middleware('owner');
            Route::post('/', [DocumentSignatureController::class, 'store']); // el signant és el propi usuari
            Route::put('{signature}', [DocumentSignatureController::class, 'update']); // controlador: owner o staff
            Route::get('urgent-unsigned/{userId}', [DocumentSignatureController::class, 'urgentUnsigned'])->middleware('owner');
        });

        // ── Onboarding ──
        Route::prefix('onboarding')->group(function () {
            // Perfils d'onboarding (plantilles) → gestió.
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::get('profiles', [OnboardingController::class, 'indexProfiles']);
                Route::get('profiles/{profile}', [OnboardingController::class, 'showProfile']);
                Route::post('profiles', [OnboardingController::class, 'storeProfile']);
                Route::put('profiles/{profile}', [OnboardingController::class, 'updateProfile']);
                Route::delete('profiles/{profile}', [OnboardingController::class, 'destroyProfile']);
                Route::post('init', [OnboardingController::class, 'init']);
                Route::put('status/{status}', [OnboardingController::class, 'updateStatus']);
                Route::get('all-statuses', [OnboardingController::class, 'allStatuses']);
            });
            // L'alta del propi treballador: perfil, estat i NOMÉS els seus documents,
            // en una sola crida. Sense això, un treballador nou no pot entrar a l'app.
            Route::get('me', [OnboardingController::class, 'me']);
            // Estat propi de l'onboarding → titular o gestió.
            Route::get('status/{userId}', [OnboardingController::class, 'status'])->middleware('owner');
            Route::put('status-by-user/{userId}', [OnboardingController::class, 'updateStatusByUser'])->middleware('owner');
            Route::post('complete/{userId}', [OnboardingController::class, 'complete'])->middleware('owner');
        });

        // ── Payrolls ──
        // NÒMINES = admin + el titular. El rol 'hr' en queda FORA per decisió de producte
        // (mateix criteri que blockHr al router de Vue: RRHH gestiona persones, no retribució).
        // Les regles que depenen de la FILA (qui és el titular, qui pot signar) viuen a
        // PayrollPolicy, no en ifs dins del controlador.
        Route::get('payroll-files', [PayrollController::class, 'index'])->middleware('role:admin');
        Route::post('payroll-files', [PayrollController::class, 'store'])->middleware('role:admin');
        Route::post('payroll-files/bulk', [PayrollController::class, 'bulkStore'])->middleware('role:admin');
        Route::delete('payroll-files/{payroll}', [PayrollController::class, 'destroy'])->middleware('role:admin');
        Route::get('payroll-files/worker/{userId}', [PayrollController::class, 'workerPayrolls']); // policy: admin o titular
        Route::get('payroll-files/{payroll}', [PayrollController::class, 'show']); // policy: admin o titular
        Route::post('payroll-files/{payroll}/view', [PayrollController::class, 'markAsViewed']); // policy: admin o titular
        Route::post('payroll-files/{payroll}/sign', [PayrollController::class, 'sign']); // policy: NOMÉS el titular

        // ── Audit Logs ── (integritat ENS: només admin)
        Route::prefix('audit-logs')->middleware('role:admin')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::post('/', [AuditLogController::class, 'store']);
        });

        // ── Work Log Segments (tramos segmentados) ──
        Route::prefix('work-logs/{workLog}/segments')->group(function () {
            // Lectura i al·legació: el controlador comprova titular o staff.
            Route::get('/', [WorkLogSegmentController::class, 'index']);
            // Audiència prèvia (EIPD C6): al·legació del TITULAR del fichatge
            Route::post('{segment}/allegation', [WorkLogSegmentController::class, 'allegation']);
            // Categorització/validació de trams → només gestió.
            Route::middleware('role:admin,coordinator,hr')->group(function () {
                Route::post('/', [WorkLogSegmentController::class, 'store']);
                Route::put('{segment}', [WorkLogSegmentController::class, 'update']);
                Route::post('{segment}/reject', [WorkLogSegmentController::class, 'reject']);
                Route::post('{segment}/approve', [WorkLogSegmentController::class, 'approve']);
            });
        });

        // ── Work Log Detail (vista con tramos) ── controlador: titular o staff
        Route::get('work-logs/{workLog}/detail', [WorkLogController::class, 'detail']);

        // ── Work Log Modifications (trazabilidad) ── controlador: titular o staff
        Route::get('work-logs/{workLog}/modifications', [WorkLogModificationController::class, 'index']);

        // ── Break Settings ── (configuració de sistema: escriptura NOMÉS admin)
        // La LECTURA no es pot tancar al treballador: WorkerDashboard.vue
        // (startBreakCheckTimer) necessita aquesta configuració per armar el
        // temporitzador de la PAUSA OBLIGATÒRIA de conveni. Amb 403 el temporitzador
        // no es programava mai i la pausa no s'activava. La resposta va FILTRADA per
        // rol dins del controlador: gestió veu la config sencera, la resta només els
        // camps del seu temporitzador.
        Route::get('break-settings', [BreakSettingController::class, 'show'])->middleware('role:admin,coordinator,hr,worker');
        Route::put('break-settings', [BreakSettingController::class, 'update'])->middleware('role:admin');
        // La llista de TOTA la plantilla amb els seus overrides és pura gestió: no s'obre.
        Route::get('break-settings/workers', [BreakSettingController::class, 'workers'])->middleware('role:admin,coordinator');
        Route::put('break-settings/workers/{user}', [BreakSettingController::class, 'updateWorker'])->middleware('role:admin');

        // Configuració de correu (admin) + enviament de prova
        Route::middleware('role:admin')->group(function () {
            Route::get('mail-settings', [MailSettingController::class, 'show']);
            Route::put('mail-settings', [MailSettingController::class, 'update']);
            Route::post('mail-settings/test', [MailSettingController::class, 'sendTest']);
        });

        // Comunicació certificada (Mensatek): RRHH i admin
        Route::prefix('certified-mails')->middleware('role:admin,hr')->group(function () {
            Route::get('/', [CertifiedMailController::class, 'index']);
            Route::post('/', [CertifiedMailController::class, 'store']);
            Route::post('{mail}/refresh', [CertifiedMailController::class, 'refresh']);
            Route::post('{mail}/cancel', [CertifiedMailController::class, 'cancel']);
            Route::get('recipients/{recipient}/certificate', [CertifiedMailController::class, 'certificate']);
            Route::get('settings', [CertifiedMailController::class, 'settingsShow'])->middleware('role:admin');
            Route::put('settings', [CertifiedMailController::class, 'settingsUpdate'])->middleware('role:admin');
        });

        // ── Break Management ── controlador: titular o staff
        Route::post('work-logs/{workLog}/start-break', [WorkLogController::class, 'startBreak']);
        Route::post('work-logs/{workLog}/complete-break', [WorkLogController::class, 'completeBreak']);
        Route::post('work-logs/{workLog}/skip-break', [WorkLogController::class, 'skipBreak']);

        // ── Work Log Alerts ──
        Route::get('work-log-alerts/pending/{userId}', [WorkLogAlertController::class, 'pending'])->middleware('owner');
        Route::post('work-log-alerts/{alert}/dismiss', [WorkLogAlertController::class, 'dismiss']); // controlador: titular o staff

        // ── Coordinator/RRHH: gestionar zonas i horaris (disponibilitat i personal) ──
        Route::middleware('role:admin,coordinator,hr')->group(function () {
            Route::post('work-schedules', [WorkScheduleController::class, 'store']);
            Route::put('work-schedules/{workSchedule}', [WorkScheduleController::class, 'update']);
            Route::post('zones/{id}/assign', [ZoneController::class, 'assignWorker']);
            Route::post('zones/{id}/remove-worker', [ZoneController::class, 'removeWorker']);
            Route::post('work-locations/{id}/assign', [WorkLocationController::class, 'assignWorker']);
            Route::post('work-locations/{id}/remove-worker', [WorkLocationController::class, 'removeWorker']);
        });

        // ── Chat ──
        Route::prefix('chat')->group(function () {
            // Settings & Policy — la política/keywords forenses només les ESCRIU l'admin.
            // La lectura no es pot tancar al treballador: ChatView.vue en treu el TEXT
            // de la política que ha d'acceptar (amb 403 el modal d'acceptació sortia
            // buit: s'acceptava una política que no es mostrava). La resposta va
            // FILTRADA dins del controlador — fora d'admin/coordinador només viatja
            // 'chat_policy_text', mai 'forensic_keywords'.
            Route::get('/settings', [App\Http\Controllers\Api\ChatController::class, 'getSettings'])->middleware('role:admin,coordinator,hr,worker');
            Route::post('/settings', [App\Http\Controllers\Api\ChatController::class, 'saveSettings'])->middleware('role:admin');
            Route::get('/policy-status/{userId}', [App\Http\Controllers\Api\ChatController::class, 'hasPolicyAccepted'])->middleware('owner');
            Route::post('/accept-policy', [App\Http\Controllers\Api\ChatController::class, 'acceptPolicy']);

            // Presence
            Route::post('/presence', [App\Http\Controllers\Api\ChatController::class, 'setUserPresence']);
            // Presència de tothom en una sola crida (la pantalla en feia una per persona).
            Route::get('/presence', [App\Http\Controllers\Api\ChatController::class, 'presenceAll']);
            Route::get('/presence/{userId}', [App\Http\Controllers\Api\ChatController::class, 'getUserPresence']);
            Route::post('/heartbeat', [App\Http\Controllers\Api\ChatController::class, 'heartbeat']);

            // Alerts forenses i vista global de converses (VIGILÀNCIA del xat) → admin i
            // coordinador. 'hr' NO: el frontend ja li amaga /chat-admin i llegir totes les
            // converses de la plantilla no forma part de la seva funció.
            Route::middleware('role:admin,coordinator')->group(function () {
                Route::get('/alerts', [App\Http\Controllers\Api\ChatController::class, 'getAlerts']);
                Route::get('/alerts/unreviewed', [App\Http\Controllers\Api\ChatController::class, 'getUnreviewedAlerts']);
                Route::post('/alerts/{id}/review', [App\Http\Controllers\Api\ChatController::class, 'reviewAlert']);
                // Vista global de totes les converses → gestió.
                Route::get('/conversations/all', [App\Http\Controllers\Api\ChatController::class, 'getAllConversations']);
            });

            // Conversations (del propi usuari; el controlador comprova participació)
            Route::get('/conversations', [App\Http\Controllers\Api\ChatController::class, 'getConversationsForUser']);
            Route::post('/conversations', [App\Http\Controllers\Api\ChatController::class, 'addConversation']);
            Route::post('/conversations/dm', [App\Http\Controllers\Api\ChatController::class, 'getOrCreateDm']);
            Route::get('/conversations/{id}', [App\Http\Controllers\Api\ChatController::class, 'getConversation']);

            // Cerca global de missatges → vigilància (recorre TOTS els missatges): sense 'hr'.
            Route::get('/search', [App\Http\Controllers\Api\ChatController::class, 'searchMessages'])->middleware('role:admin,coordinator');
            Route::get('/conversations/{convId}/messages', [App\Http\Controllers\Api\ChatController::class, 'getMessages']);
            Route::post('/messages', [App\Http\Controllers\Api\ChatController::class, 'addMessage']);
            Route::get('/conversations/{convId}/unread/{userId}', [App\Http\Controllers\Api\ChatController::class, 'getUnreadCount']);
            Route::get('/conversations/{convId}/last-message', [App\Http\Controllers\Api\ChatController::class, 'getLastMessage']);
            Route::post('/conversations/{convId}/read', [App\Http\Controllers\Api\ChatController::class, 'markMessagesRead']);
            // El comptador global de no llegits és del titular (el frontend només demana el propi).
            Route::get('/unread-count/{userId}', [App\Http\Controllers\Api\ChatController::class, 'getTotalUnreadCount'])->middleware('owner');
        });

        // ── Holidays (calendari laboral, configurable cada any) ──
        Route::get('holidays', [HolidayController::class, 'index']);
        Route::post('holidays', [HolidayController::class, 'store'])->middleware('role:admin,hr');
        Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->middleware('role:admin,hr');

        // ── Bossa anual d'hores (1726 h prorratejades) ──
        Route::get('bossa-anual', [\App\Http\Controllers\Api\AnnualBagController::class, 'show']);
        Route::get('bossa-anual/tots', [\App\Http\Controllers\Api\AnnualBagController::class, 'index'])->middleware('role:admin,hr,coordinator');
        Route::get('rendiment', [\App\Http\Controllers\Api\PerformanceController::class, 'index'])->middleware('role:admin,hr');
        /* El rendiment PROPI: sense això, la persona mesurada era l'única que no podia veure la
           seva mesura. 'owner' deixa passar titular o staff; el controlador estreny la banda
           aliena a admin/hr, els mateixos perfils del panell de Direcció. */
        Route::get('rendiment/persona/{userId}', [\App\Http\Controllers\Api\PerformanceController::class, 'meu'])
            ->middleware('owner');
        // Rebatre una xifra: NOMÉS el titular (es comprova al controlador, no s'hi val staff).
        Route::post('rendiment/persona/{userId}/allegacio', [\App\Http\Controllers\Api\PerformanceController::class, 'allegacio'])
            ->middleware('owner');
        Route::post('rendiment/allegacions/{id}/resposta', [\App\Http\Controllers\Api\PerformanceController::class, 'resposta'])
            ->middleware('role:admin,hr');

        Route::prefix('millores')->middleware('role:admin,hr')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\MilloresController::class, 'index']);
            Route::post('generar', [\App\Http\Controllers\Api\MilloresController::class, 'generate']);
            Route::get('cua', [\App\Http\Controllers\Api\MilloresController::class, 'cua']);
            Route::put('{id}', [\App\Http\Controllers\Api\MilloresController::class, 'decide']);
        });

        Route::prefix('rlt')->middleware('role:admin,hr')->group(function () {
            Route::get('tipus', [\App\Http\Controllers\Api\RltReportController::class, 'tipus']);
            Route::get('informes', [\App\Http\Controllers\Api\RltReportController::class, 'index']);
            Route::get('fets', [\App\Http\Controllers\Api\RltReportController::class, 'fets']);
            Route::get('informes/{id}', [\App\Http\Controllers\Api\RltReportController::class, 'show']);
            Route::get('informes/{id}/document', [\App\Http\Controllers\Api\RltReportController::class, 'document']);
            Route::post('informes', [\App\Http\Controllers\Api\RltReportController::class, 'generate']);
            Route::put('informes/{id}', [\App\Http\Controllers\Api\RltReportController::class, 'update']);
            Route::post('informes/{id}/signar', [\App\Http\Controllers\Api\RltReportController::class, 'sign']);
            Route::post('informes/{id}/lliurar', [\App\Http\Controllers\Api\RltReportController::class, 'deliver']);
        });
    });
});

