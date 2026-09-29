# Revisión pre-producción — CRT RRHH

**Fecha:** 2026-07-27 · **Alcance:** seguridad backend, higiene de repo/despliegue, robustez operativa · **Método:** lectura de código (3 auditorías paralelas + verificación manual de los hallazgos ALTA). Nada se ha modificado.

## Estat de resolució (actualitzat 2026-07-27, després del treball)

Tots els bloquejants i les MEDIA de codi s'han tancat en 4 commits (tests → neteja
→ docs → autorització → enduriment), suite en verd (78 tests). Detall:

- **B1 Autorització** ✅ — `isStaff()`, middleware `owner`, trait `AuthorizesOwnership`,
  `role:` a totes les rutes de gestió, fix de l'apropiació de compte i IDOR del xat. 21 tests nous.
- **B2/B3 Scripts perillosos** ✅ — eliminats del repo (i `api/` obsolet, APKs).
- **B4 Bombes operatives** ✅ — documentades a `DESPLEGAMENT.md §0.bis` (cron `schedule:run`,
  prohibició de `key:generate`, `mysqldump` previ, `composer --no-dev`).
- **MEDIA** ✅ — CORS restringit, validació de pujada (PDF/10MB), mail en try/catch,
  timeouts de worldtimeapi, `.env.example` complet, `nginx.conf.example` reescrit, log rotatiu.

**Residus que NO són de codi (acció d'informàtica/admin, documentats):**
1. **~56 usuaris MD5**: cal resetejar-los la contrasenya abans/durant el desplegament
   (el login només accepta bcrypt). Acció operativa, no de codi.
2. **Documents al disc 'public'**: si s'executa `storage:link`, els fitxers de
   `documents/` són accessibles per URL sense auth a qui en conegui la ruta. Les rutes
   de gestió ja estan protegides; per a confidencialitat forta caldria moure'ls a disc
   privat i servir-los per un endpoint autenticat (canvi que trencaria la visualització
   actual — decisió de producte, fora d'aquest abast).

---

## Veredicto (diagnòstic original)

**NO desplegar todavía.** La app es funcionalmente rica y la infraestructura de despliegue (CI, health-check, docs) es mejor de lo esperado, pero hay **un defecto de diseño de autorización que afecta a casi toda la API** y **dos scripts destructivos sin autenticación en el árbol desplegable**. En una app ENS categoría ALTA con datos de salud, cualquiera de los dos es bloqueante por sí solo. Estimación: los bloqueantes son acotados y se pueden cerrar en unos días de trabajo enfocado; el resto es endurecimiento y documentación de despliegue.

La restricción dominante es la **autorización**: aunque se limpie todo lo demás, mientras el backend confíe en el `id` que envía el cliente sin verificar rol ni propiedad, un solo trabajador autenticado compromete el sistema entero.

---

## BLOQUEANTES (ALTA) — impiden el despliegue

### B1. La autorización vive en el frontend; el backend está abierto
El enforcement de rol solo se aplica en ~6 rutas de `routes/api.php`. **Todo lo demás bajo `auth:sanctum` queda accesible a cualquier usuario autenticado, incluido `role=worker`.** El backend confía en identificadores enviados por el cliente sin verificar propiedad. Consecuencias verificadas (un worker autenticado puede):

- **Apropiarse de cualquier cuenta**: `PUT /users/{id}` con `{"password":"..."}` — `UserController::update()` bloquea campos privilegiados para no-admin pero **no bloquea `password`** ni comprueba propiedad. Reseteo de la contraseña de cualquier admin. *(Verificado leyendo el código directamente.)*
- **Borrar/crear usuarios**: `DELETE /users/{id}`, `POST /users` sin check de rol.
- **Leer y borrar nóminas de toda la plantilla**: `PayrollController::index()` devuelve todos los PDFs (base64); `destroy()`/`store()` sin check.
- **Auto-aprobarse fichajes y ausencias**: `WorkLogSegmentController::approve/reject` y `AbsenceController::update` (fija `approved`/`approved_by`) sin rol — el comentario dice "coordinator/admin" pero no hay comprobación.
- **IDOR total en el Chat**: ninguna función valida que el usuario coincida con el `user_id`/`sender_id` recibido — leer conversaciones ajenas, falsear remitente, reescribir `forensic_keywords`.
- **Ver/inyectar auditoría ENS**: `AuditLogController::index/store` — leer toda la traza (IPs, logins fallidos) e inyectar entradas falsas (integridad del registro ENS comprometida).
- Menor pero del mismo patrón: `MailSettingController` (cambiar SMTP), `BreakSettingController`, `AuthCodeController` (emitir horas extra), catálogo de ausencias, onboarding.

**Naturaleza:** no son bugs sueltos, es un patrón único. La solución es sistémica (middleware de rol por grupo de rutas + verificación de propiedad donde el recurso es de un usuario), no parchear endpoint a endpoint.

### B2. Script destructivo sin autenticación en `public/` — `public/fix_onboarding_final.php`
Trackeado en git, dentro del document root natural de Laravel. Ejecuta **2 `delete()` sin ninguna autenticación**: borra `onboarding_status` y `document_signatures` del usuario 170 y lo resetea. Si informática apunta el docroot a `public/` (lo normal), es invocable en `https://host/fix_onboarding_final.php` por cualquiera y borra firmas de documentos en producción. *(Verificado: existe y hace los deletes.)* **Eliminar antes de desplegar.**

### B3. Scripts de mantenimiento con credenciales/deletes en la raíz del repo
Trackeados: `reset_xavier.php` (fija la contraseña del user 95 a `123456` y desactiva el cambio forzado), `fix_db.php`, `fix_doc.php`, `create_geo_doc.php`, `migrate_payrolls.php` (con ruta Windows, inservible), `check_*.php`, `test_onboarding.php`. Fuera del docroot no son web-accesibles bajo el nginx de ejemplo, pero no deben viajar al servidor: cualquiera con acceso al shell los ejecuta sobre datos reales. **Sacar del árbol desplegable** (borrar del repo o mover a un directorio no desplegado).

### B4. Dos bombas operativas silenciosas en el despliegue manual
- **Cron sin instalar**: el scheduler define recordatorios de jornada sin cerrar y **purga de coordenadas (RGPD/EIPD)**, pero ni `DESPLEGAMENT.md` ni el CI instalan `* * * * * php artisan schedule:run`. Sin ese cron, nunca se envían avisos y **nunca se purgan coordenadas** → incumplimiento de retención a partir del día 30, en silencio.
- **`key:generate` = coordenadas indescifrables**: lat/lng usan cast `encrypted` atado a `APP_KEY`. Si en el despliegue alguien ejecuta `php artisan key:generate` (reflejo habitual con `APP_KEY=` vacío en `.env.example`), **todas las coordenadas ya cifradas quedan permanentemente ilegibles** y el detalle de fichaje revienta con `DecryptException`. El CI no lo hace, pero la doc no lo prohíbe explícitamente.

---

## IMPORTANTE (MEDIA) — cerrar antes o justo después, con vigilancia

- **CORS comodín** (`config/cors.php`): `allowed_origins=['*']`. Impacto acotado (auth por Bearer, no cookies) pero cualquier web con un token roba respuestas. Restringir al dominio de la SPA.
- **Subida de documentos sin validar** (`DocumentController::store`): sin límite de tamaño ni mime; si se ejecuta `storage:link` quedarían servidos públicamente sin auth. `created_by` viene del cliente (falseable).
- **worldtimeapi.org síncrono en cada fichaje** (`WorkLogService::getAccurateTime`, timeout 3 s): no rompe (hay fallback), pero degrada — a las 8:00 con toda la plantilla fichando, y en cada caída de esa API (frecuentes) la hora "legal" cae al reloj del servidor. Considerar NTP local o timeout menor.
- **Mail síncrono sin try/catch** (`AuthCodeController:68`): si el SMTP va lento, crear un código de horas extra puede devolver 500 con el código ya creado. Envolver o encolar.
- **Repo obeso**: `api/vendor/` (8.283 ficheros de una app Laravel obsoleta y duplicada) trackeado; `.git` de 210 MB por APKs y node_modules en la historia. No rompe el deploy pero cada `git pull` arrastra 210 MB. Limpiar `api/`, dejar de versionar APKs.
- **`.env.example` incompleto**: faltan `DISP_CORP_KEY`, `DOMI_HEALTH_URL` (comentadas) y `VITE_API_URL` (ausente). Quien monte producción desde el ejemplo deja la integración domi y el enrolamiento Hexnode sin configurar.
- **`nginx.conf.example` desactualizado**: describe un layout (`app/dist`, `api/public`) que no coincide con el repo real. Puede inducir a informática a un docroot equivocado (y a exponer B2).
- **Migración de cifrado fila-a-fila** (`2026_07_17_000003`): recorre `work_logs`/`location_tracking` sin chunk; en tablas grandes bloquea. Ejecutar en ventana de baja actividad con `mysqldump` previo (el deploy manual "pull+migrate" no lo hace; el CI sí).
- **~56 usuarios con hash MD5**: el login solo acepta bcrypt (`Hash::check`) — **no hay camino legacy**. Esos usuarios no podrán entrar el día 1 salvo reseteo previo de contraseña. *(Verificado leyendo `AuthController::login`.)*
- **Log sin rotación + `LOG_LEVEL=debug`**: `stack→single` sin rotación; el fichero crece sin límite. Cablear canal `daily`, bajar a `warning`.

---

## MENOR (BAJA)

- `composer install` sin `--no-dev` instala Faker/PHPUnit/Ignition en producción (bajo riesgo con `APP_DEBUG=false`). Usar `--no-dev --optimize-autoloader`.
- Ficheros basura trackeados: `origin)` (0 bytes), `start-api.bat`, cache de framework en `api/`.
- `app/.env`, `app/.env.production` trackeados — solo contienen `VITE_API_URL`, **sin secretos**. Fuga histórica de `.env` limitada a esa URL pública; el `.env` backend con secretos nunca estuvo en git.

---

## Lo que está BIEN (no tocar)

- `CheckRole` y `RestrictServiceRole` son **fail-closed** y sólidos — el problema es que se aplican en pocas rutas, no que fallen.
- Zona horaria `Europe/Madrid` coherente con la convención UTC en BD.
- `APP_DEBUG=false` y `APP_ENV=production` en `.env.example`; `.env` real no trackeado.
- Sin inyección SQL: los raw usan bindings parametrizados.
- CI de despliegue robusto: `mysqldump` previo, `migrate --force`, health-check con rollback.
- Índices presentes en tablas calientes (`work_logs(user_id,date)`, FKs del chat).
- Los `try/catch` vacíos revisados son trazas de auditoría deliberadamente no bloqueantes; el cálculo de horas **no** está silenciado.
- La suite de tests pasa (49 tests tras el trabajo de hoy).

---

## Orden recomendado

1. **B2 + B3** (borrar scripts): horas de trabajo, riesgo cero, cierra la exposición web más grave.
2. **B4** (documentar cron + prohibir `key:generate` + `mysqldump` manual previo): es documentación de despliegue, no código.
3. **B1** (autorización): el grueso. Middleware de rol por grupo + verificación de propiedad en los endpoints por-usuario (chat, nóminas, ausencias, fichajes, onboarding). Requiere tests que fijen "worker no puede tocar recurso ajeno" — misma estrategia de caracterización que usamos hoy.
4. **MEDIA**: CORS, validación de subida, `.env.example`, limpieza de `api/`, worldtimeapi/mail.
5. Re-verificar con la suite en verde y desplegar en ventana de baja actividad, vigilando login (2FA + MD5) y latencia de fichaje.

**Pre-mortem:** si esto va a producción tal cual, el fallo más probable a 30 días no es un bug funcional sino (a) un incidente de datos por B1/B2, o (b) una no-conformidad RGPD por la purga que nunca corrió (B4). Ambos evitables.
