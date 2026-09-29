# PROMPT DEFINITIVO para IA legal — EIPD/DPIA integrada CRT RRHH + CRT Domiciliària (a presentar al Comité de Empresa)

## Rol

Actúas como jurista especializado en protección de datos y derecho laboral español (RGPD, LOPDGDD 3/2018, ET, doctrina AEPD, jurisprudencia TS/TC sobre control empresarial y geolocalización). Trabajas por encargo de **CRT** (responsable del tratamiento); tu producto será revisado y firmado por su DPD y **se presentará al Comité de Empresa**.

## Guías AEPD de obligado seguimiento

Elaborarás la EIPD siguiendo EXACTAMENTE estos tres documentos de la AEPD (el usuario los adjunta junto a este prompt):

1. **"Modelo de informe de EIPD para el sector privado"** (versión marzo 2022): la EIPD seguirá su estructura de capítulos **I a XV** (Resumen ejecutivo · Índice · Datos básicos · Metodología · Bases jurídicas · Descripción del tratamiento · Factores de riesgo · Obligación de EIPD · Controles · Necesidad y proporcionalidad · Consulta previa · Conclusiones · Memorando del DPD · Referencias · Anexos), con bloque final de firmas.
2. **"Guía Gestión del riesgo y evaluación de impacto en tratamientos de datos personales"**: metodología de referencia. Aplica sus capítulos según los referencia el Modelo (descripción V.A-V.E, factores de riesgo VI, riesgo intrínseco VII, controles VIII, reevaluación IX, juicios XIII, consulta previa XVI).
3. **"Lista de verificación EIPD y consulta previa"** (Instrucción 1/2021): entregarás la lista **cumplimentada punto por punto** (columnas CHECK y COMENTARIOS con remisión al apartado de la EIPD que lo acredita) como anexo de la EIPD.

Contexto sancionador que debes tener presente y citar donde proceda: arts. 73.t, 73.u, 73.w (graves) y 74.o (leve) LOPDGDD; art. 83.4 RGPD.

## Entregables, en este orden

1. **EIPD/DPIA formal** conforme al Modelo AEPD (capítulos I–XV), lista para firma del responsable y del DPD. En el capítulo XI concluirás motivadamente sobre la **consulta previa** (art. 36 RGPD): solo procede si el riesgo residual no puede mitigarse; si concluyes que no procede, dilo expresamente con la justificación.
2. **Lista de verificación cumplimentada** (anexo de la EIPD).
3. **Documento de presentación al Comité de Empresa** (arts. 64 ET y 90.2 LOPDGDD): resumen ejecutivo no técnico de la EIPD + garantías, en tono informativo y verificable, anticipando y respondiendo desde los hechos los tres ataques probables (desproporción, vigilancia encubierta, falta de información previa). Castellano y catalán.
4. **Versión castellana espejo de la comunicación individual al trabajador** (el original catalán está en el Anexo I de la base fáctica; no alteres su contenido sustantivo).
5. **Lista de brechas**: cualquier insuficiencia jurídica que detectes, con la corrección concreta exigida.

## Reglas de trabajo

- **Base fáctica cerrada**: la memoria técnica que sigue es el ÚNICO estado del sistema. No inventes funcionalidades ni medidas. Lo que falte, decláralo "hecho a verificar".
- **Rigor pro-empresa sin complacencia**: el objetivo es que el tratamiento sea inatacable; si algo es débil, dilo y da la corrección. Una EIPD indulgente es peor que ninguna.
- **Cita siempre** artículo y norma (y doctrina AEPD/sentencia cuando exista). El Modelo exige en su cap. XIV una relación exhaustiva de referencias realmente utilizadas — no rellenes con citas no usadas.
- Distingue: **CRT** = empresa responsable; **CRT RRHH / CRT Domiciliària** = sistemas del tratamiento. No hay corresponsables ni encargados externos: ambos sistemas son del mismo responsable; el hosting (IONOS) actúa como encargado de infraestructura (hazlo constar en III.A y VI).
- Los apartados §10 y §11 de la base fáctica declaran pendientes reales: intégralos como **condiciones del dictamen** (en particular: EIPD previa a activación, información a RLT, piloto con la tablet corporativa).
- Idioma: castellano; los textos destinados a trabajadores, además, en catalán.

## Crosswalk — dónde encontrar en la base fáctica lo que cada capítulo del Modelo exige

| Capítulo del Modelo AEPD | Fuente en la base fáctica |
|---|---|
| III. Datos básicos (responsable, tratamiento, fechas) | Cabecera, §1, §10 (fecha de inicio condicionada a EIPD y RLT) |
| V. Bases jurídicas | §3 (por finalidad; art. 9 pacientes NO entra: no se comunican) |
| VI. Descripción del tratamiento (alto nivel, estructurado, ciclo de vida, activos, casos de uso) | §1, §2, §5 (flujo y plataformas), §4 (ciclo de vida del dato: captura→veredicto→cifrado→purga 48m); casos de uso = Modo A / Modo B / supuestos tasados §5 |
| VII. Factores de riesgo y riesgo intrínseco | §7 (tabla riesgo→mitigación→residual) + geolocalización de trabajadores como factor (art. 35.3 y listas CEPD) |
| VIII. Obligación de EIPD | Evaluación sistemática de aspectos personales + colectivo trabajadores + geolocalización → EIPD obligatoria; motívalo con las listas del art. 35.4 |
| IX. Controles | §6 (tabla de medidas verificadas), §6.1 (radio mínimo dinámico), §6.2 (audiencia) |
| X. Juicios de idoneidad/necesidad/proporcionalidad | §8 (incluye alternativas evaluadas y descartadas) |
| XI. Consulta previa | §7 (residuales bajos) — concluye motivadamente |
| XIII. Memorando del DPD | A cumplimentar por el DPD humano; deja la estructura preparada |
| XV. Anexos | Anexo I (comunicación individual), lista de verificación, §11 (verificación empírica) |

---

# BASE FÁCTICA — MEMORIA TÉCNICA (estado verificado a 17/07/2026)

# Memoria técnica para EIPD integrada — CRT RRHH + CRT Domiciliària

**Responsable del tratamiento:** CRT (Centre de Rehabilitació Terapèutica) · DPD: dpd@crtbcn.cat
**Sistemas:** CRT RRHH (registro de jornada y gestión laboral) y CRT Domiciliària (gestión asistencial domiciliaria)
**Fecha del estado técnico descrito:** 17/07/2026 · Ramas: `crt-rrhh-actualizaciones` (RRHH), `integracio-rrhh` (Domiciliària)
**Finalidad de este documento:** base técnica objetiva para que el DPD elabore la EIPD (art. 35 RGPD). Describe lo implementado y verificado, lo pendiente y los riesgos residuales. No sustituye a la EIPD.

---

## 1. Objeto y alcance

Dos aplicaciones del mismo responsable, con **repositorios, despliegues y bases de datos separados**, que se comunican **exclusivamente por API** autenticada:

| Sistema | Función | Datos dominantes |
|---|---|---|
| CRT RRHH | **Registro legal único de jornada** (art. 34.9 ET) de toda la plantilla; ausencias, nóminas, documentación laboral | Datos laborales de trabajadores; geolocalización puntual del trabajador |
| CRT Domiciliària | Gestión de tratamientos de fisioterapia a domicilio (pacientes CatSalut) | Datos de salud de pacientes (art. 9 RGPD); operativa de visitas |

**Principio rector de la integración:** cada categoría de dato permanece en su sistema. Los datos de pacientes **no salen nunca** de Domiciliària; los datos laborales viven solo en RRHH.

## 2. Tratamientos cubiertos

1. **Registro de jornada** (RRHH): entrada/salida, pausas, horas efectivas por tramos aprobables. Obligación legal (art. 34.9 ET, RDL 8/2019).
2. **Verificación de presencia por hitos de servicio** (integración): en cada visita domiciliaria, el fisioterapeuta marca llegada/salida; se captura su posición GPS **solo en ese instante**.
3. **Sincronización de plantilla** (RRHH→Domiciliària): qué fisioterapeutas domiciliarios están activos, sus zonas y horarios. RRHH es fuente de verdad.
4. **Pausa obligatoria**: la lógica vive en RRHH; Domiciliària solo muestra el aviso en la app que el trabajador tiene abierta.

## 3. Bases jurídicas

| Tratamiento | Base |
|---|---|
| Registro de jornada | Art. 6.1.c RGPD (obligación legal: art. 34.9 ET) |
| Geolocalización puntual en hitos | Art. 6.1.b y 6.1.f RGPD (ejecución del contrato; control empresarial art. 20.3 ET), con las garantías del **art. 90 LOPDGDD** (información previa expresa a trabajadores y representantes) |
| Sincronización de plantilla | Art. 6.1.b RGPD (organización del servicio) |
| Datos de salud de pacientes | **No se comunican entre sistemas** — permanecen en Domiciliària bajo su base propia (art. 9.2.h RGPD) |

## 4. Minimización por diseño — qué se registra y qué NO

**Se registra (RRHH):**
- Hora de cada hito y de entrada/salida de jornada.
- Coordenadas del **trabajador** solo en el instante del hito (no trayectoria).
- El **veredicto** de verificación: `verificat` / `fora_radi` / `no_disponible`, y distancia en metros.
- Una **referencia opaca** de visita (hash SHA-256 truncado del identificador interno de tratamiento): permite auditoría cruzada sin revelar qué paciente es.

**NO se registra:**
- ❌ Seguimiento continuo del trabajador (eliminado el tracking en segundo plano que existía; verificado en código: `WorkerDashboard.vue` ya no arranca `startBackgroundTracking`).
- ❌ Identidad, dirección o coordenadas del **paciente** en RRHH: la distancia fisio↔domicilio se calcula **dentro de Domiciliària** (geo-stack autoalojado) y solo viaja el resultado.
- ❌ Hitos retroactivos: el servidor rechaza (HTTP 422) cualquier hito a más de ±15 min de la hora del servidor. Un registro tardío no es prueba de presencia y no se acepta como tal.
- ❌ Posiciones fuera de jornada: la bandera `out_of_schedule` respeta las horas complementarias autorizadas por código.

## 5. Arquitectura y flujos

**Naturaleza de las plataformas (precisión técnica):**
- **CRT Domiciliària** es una **aplicación web/PWA**: el fisioterapeuta la usa en el navegador de su dispositivo. En una aplicación web **no existe posibilidad técnica de "bloqueo nativo" del GPS**; las garantías son **de servidor**: captura solo al pulsar el hito, ventana de tiempo real ±15 min, veredictos en lugar de trayectorias y ausencia total de captura fuera de hitos.
- **CRT RRHH** dispone además de **app híbrida (Capacitor)** con envoltorio nativo Android/iOS; solo en esa app es técnicamente posible un control nativo del sensor (previsto como Fase 2, ver §10.4).
Esta distinción debe mantenerse en toda comunicación al Comité de Empresa para no atribuir a la empresa capacidades de control que no tiene.

**Dispositivo de trabajo previsto: tablet corporativa (Blackview Zeno 5, 11", Android 16, GNSS multiconstelación GPS/GLONASS/Beidou/Galileo, 4G LTE).** Consecuencias:
- **Jurídica**: al ser un **dispositivo facilitado por la empresa** (art. 87 LOPDGDD), decae la objeción de invasión del dispositivo personal. La voluntariedad del dispositivo propio se mantiene solo como **alternativa opcional** para quien prefiera su teléfono; quien no, usa la tablet corporativa sin aportar ningún medio personal.
- **Técnica**: GNSS multiconstelación real (no triangulación de red), y el radio dinámico (§6.1) absorbe la menor calidad del receptor de gama media: si el sensor reporta más error, el radio crece hasta el tope; la precisión real medida en el piloto calibrará los parámetros.
- **Gestión MDM (Hexnode) en modo kiosco**: las tablets corporativas van enroladas en Hexnode con modo kiosco (dispositivo limitado a las aplicaciones de trabajo) y **política de ubicación activa forzada** — en el dispositivo corporativo el trabajador no puede desactivar el GPS, igual que no puede desinstalar la app. Consecuencias:
  - La **denegación de GPS solo puede darse en el dispositivo personal** (uso voluntario): en ese caso la app deriva automáticamente al registro manual con justificación (§6.4), sin bloquear jamás la labor asistencial.
  - El "control nativo del sensor" que en una aplicación web es imposible a nivel de app (§5 arriba) queda cubierto **a nivel de dispositivo vía MDM** en el parque corporativo — cierra la carencia señalada como Fase 2 para esas tablets.
  - Nada de esto altera las garantías de captura: aunque el sensor esté siempre activo a nivel de dispositivo, **la aplicación solo lee la posición al pulsar un hito** — el diseño de minimización es de la app, no del sensor.
  - Nota de despliegue: configurar en Hexnode la concesión automática del permiso de ubicación para el dominio de la aplicación (evita diálogos de permiso en modo kiosco).
  - **Identidad de dispositivo verificada en servidor (implementada):** la URL de arranque del kiosco incluye una clave de aprovisionamiento (`?disp_corp=…`) que la app canjea por un **token HMAC** verificado con `hash_equals` en cada fichaje. Con ello: **en dispositivo corporativo, la denegación del GPS está bloqueada también en la aplicación** (403 + anomalía registrada en auditoría `GPS_DENEGAT_DISP_CORP` — no debería ocurrir nunca, porque Hexnode fuerza la ubicación a nivel de OS); el **fallo de señal/sensor** sigue registrándose con anotación automática (la excepción legítima). El **flujo manual con justificación queda reservado al dispositivo personal**, cuyo uso solo está previsto para los casos tasados: robo o extravío de la tablet corporativa, o falta de señal. Un dispositivo personal no puede forjar el token sin la clave (y hacerlo solo le impondría reglas más estrictas).

```
Fisioterapeuta (navegador del dispositivo, sesión Domiciliària — aplicación web)
   │  llegada/salida de visita (lat/lng del instante)
   ▼
CRT Domiciliària (api/rrhh_jornada.php)
   │  · resuelve el domicilio del paciente EN LOCAL (crt_geo_addr, geo-stack propio)
   │  · calcula distancia (haversine) → veredicto radi_ok
   │  · NUNCA expone el token de servicio al navegador
   ▼  POST /api/v1/domi/hito  {user_id, tipus, moment, lat/lng, radi_ok, dist_m, ref-opaca}
CRT RRHH
   · categoriza (verificat / fora_radi / no_disponible) — RRHH decide, Domiciliària informa
   · particiona la jornada en tramos visita/desplazamiento, aprobables individualmente
   · cifra AES las coordenadas en reposo; audita cada operación (AuditLog DOMI_*)
```

**Modos de funcionamiento (degradación controlada):**
- **Modo A** (Domiciliària responde a la sonda `api/health.php`): verificación por hitos con contraste de domicilio.
- **Modo B** (Domiciliària caída o no desplegada): RRHH funciona **autónomo** — fichaje simple con validación por zona (código postal), sin hitos. El registro legal nunca depende del sistema asistencial.
- Si RRHH no responde, Domiciliària **no se bloquea** (la atención al paciente es prioritaria) y el trabajador registra directamente en RRHH.

**Protocolo escrito de aplicación del Modo B — supuestos tasados, sin discrecionalidad:**
El modo de verificación **lo determina el sistema automáticamente**, nunca una elección discrecional del trabajador ni de la empresa — no hay margen de arbitrariedad. Supuestos, todos registrados en el propio fichaje (`verification_mode`):
1. Domiciliària no responde a la sonda de salud en 2 segundos (caída, mantenimiento, sin despliegue).
2. El trabajador no pertenece al colectivo domiciliario (resto de plantilla: siempre fichaje simple).
3. El trabajador domiciliario no tiene vinculación técnica entre sistemas (mapeo pendiente): registra directamente en RRHH sin pérdida de derechos.
Casos que **permanecen en Modo A pero sin veredicto de domicilio** (hito registrado, verificación `no_disponible`, jamás en contra del trabajador):
4. Domicilio del paciente sin geocodificación a nivel de portal.
5. GPS del dispositivo denegado, sin señal o sin cobertura en el momento del hito.
En todos los supuestos el registro de jornada del trabajador es válido a todos los efectos; el modo y el motivo quedan trazados y son visibles en la revisión.

**Precisión honesta del veredicto:** si el domicilio del paciente solo está geocodificado a nivel de código postal (sin portal), el sistema **no** afirma verificación: el hito se marca `no_disponible`. El sistema no genera falsos "verificados".

## 6. Medidas técnicas y organizativas implementadas (verificadas en código y en ejecución)

| Medida | Implementación concreta |
|---|---|
| **Cifrado en reposo (AES-256, Laravel Crypt)** | Coordenadas del trabajador en `work_logs`, `location_tracking` y `work_log_segments`. En BD solo hay texto cifrado; los booleanos de validación quedan en claro para que el control funcione sin descifrar masivamente |
| **Retención limitada y purga automática** | Comando `locations:purge` mensual: a los **48 meses** se eliminan coordenadas (puestas a NULL con marca `coords_purged_at`) conservando el registro horario 4 años (art. 34.9 ET). Excepción: fichajes con alerta no resuelta |
| **Sin seguimiento continuo** | Captura GPS únicamente en hitos (entrada/salida jornada, llegada/salida visita) |
| **Tiempo real obligatorio** | Hitos a >±15 min rechazados (anti-fabricación de presencia) |
| **Cuenta de servicio acotada** | Token Sanctum de rol `service` restringido por middleware a `bulk-index`, `users/me` y `/domi/*`; cualquier otra ruta → 403. Sin login por contraseña (los tokens no se revocan accidentalmente) |
| **Secreto fuera del repositorio** | `rrhh_secret.php` en `.gitignore`; plantilla `.example` documentada; token generado con `php artisan domi:service-account` |
| **Registro de accesos** | Toda consulta de datos de ubicación por personal autorizado queda en `audit_logs` (`ACCESS_LOCATION_DATA`); toda operación vía integración, con acción `DOMI_*`, IP y user-agent |
| **Trazabilidad de origen** | Cada jornada indica `verification_mode` (A/B) y cada tramo su verificación y referencia opaca |
| **Geo autoalojado** | Domiciliària geocodifica y calcula rutas con Nominatim/OSRM/tiles propios (docker local): las direcciones de pacientes no se envían a servicios de terceros. RRHH puede apuntar sus mapas al mismo stack (variables `VITE_TILE_URL`/`VITE_OVERPASS_URL`) |
| **Aviso y consentimiento informado** | Aviso de geolocalización reformulado: uso del dispositivo personal **voluntario**, método de registro alternativo con la misma validez, y retirada de permisos **no sancionable** habiendo prestado el servicio |
| **Revisión humana** | Tramos `fora_radi` o incompletos quedan `pending` para revisión por coordinación; nada se sanciona automáticamente (protocolo en §6.2) |
| **Radio de verificación mínimo y dinámico** | 50 m base + precisión GPS del fix (tope 100 m) → rango 50–150 m por hito; distancia y radio usados quedan registrados en el tramo (§6.1); parámetros calibrables con el piloto |

### 6.1 Radio de verificación: mínimo legal y técnico, dinámico por hito

El sistema **no usa un radio fijo generoso**: calcula en cada hito el radio más pequeño compatible con el error real del sensor en ese instante — minimización aplicada al propio umbral de control:

**`radio efectivo = 50 m (base) + precisión GPS reportada por el fix (tope 100 m)`**

- **Base 50 m**: huella de la finca + error de geocodificación del portal (Nominatim, típicamente 10–30 m). Es lo que separa "en el domicilio" de "en la calle de al lado".
- **Componente dinámico**: el navegador reporta la precisión del fix (`accuracy`). Si el GPS ve bien (±8 m), el radio efectivo es **58 m**; solo cuando el sensor mismo declara error grande (interior degradado, multitrayecto) el radio crece, con **tope en 150 m**. Sin dato de precisión, se aplica el tope (conservador a favor del trabajador: nunca genera un `fora_radi` por falta de dato).
- Consecuencia jurídica: el umbral **nunca es más amplio de lo que el error del sensor exige** en cada momento — no existe un margen fijo "cómodo" que pudiera tacharse de vigilancia laxa ni de cifra arbitraria. Rango real: 50–150 m, dominado por el propio sensor.
- **Trazabilidad probatoria**: cada tramo de visita guarda la distancia medida y el radio efectivo aplicado (`home_distance_m`, `home_radius_m`), de modo que la revisión humana (§6.2) y el trabajador ven el hecho exacto: "distancia 70 m con radio permitido 58 m (precisión reportada ±8 m)".
- Los parámetros (base y tope) son **configurables**, y el **piloto** (§10.3) medirá la distribución real de distancias y precisiones para que el DPD valide o ajuste ambos con datos empíricos, no con estimaciones.

### 6.1.bis Instrumentación empírica del piloto (validación del radio)

El sistema incorpora el comando `pilot:radi-report`, que produce a partir de los hitos reales el informe que la EIPD exige para fijar el radio con evidencia y no con estimaciones:
- distribución de la **precisión GPS reportada** en entorno urbano denso real (p50/p90/p95/máx);
- distribución de las **distancias medidas** al domicilio;
- **tasa de `fora_radi`** y detección de candidatos a **falso negativo** (veredictos fora_radi con distancia ≤ radio+20%, la "zona gris" del sensor);
- **radio mínimo recomendado** = base 50 m + p95 de la precisión observada.
Con ese informe, el DPD fija los parámetros definitivos (base y tope) **minimizando el radio de forma justificada**: ni un metro más de lo que la tasa de error real del GPS exige para no generar falsos negativos sistemáticos. Verificado con datos de prueba (salida reproducible).

Además del comando CLI, el **panel de administración** muestra visualmente el mismo informe (tarjeta "Radi de verificació — informe del pilot" en el tablero, solo rol admin, endpoint `geolocation/pilot-radi` protegido): radio aconsejado a fijar tras el período de prueba, percentiles de precisión, tasa de `fora_radi` y avisos de zona gris. La decisión del radio queda así **a la vista del responsable en todo momento**, no enterrada en un informe técnico. Verificado en navegador.

### 6.2 Protocolo de revisión de tramos `fora_radi` — derecho de audiencia

Garantías técnicas implementadas (verificadas en ejecución) — el proceso es **dirigido por la empresa** con la audiencia como garantía infranqueable:
1. Un tramo `fora_radi` o `no_disponible` **nunca se invalida automáticamente**: queda `pending` (no computa como tiempo efectivo hasta resolución) y **abre audiencia automáticamente** — el trabajador recibe notificación en su app con el **plazo de alegaciones (7 días naturales) fijado por la empresa**.
2. El **rechazo sin audiencia es técnicamente imposible**: la API lo deniega (422) si no hay alegación del trabajador y el plazo no ha vencido. El primer intento de rechazo sin audiencia previa **la abre** en lugar de rechazar.
3. El **reloj lo lleva la empresa**: vencido el plazo sin alegación, coordinación resuelve unilateralmente y la traza documenta *"audiència oferta el [fecha]; termini vençut sense al·legacions"* — el derecho es a ser oído, no a bloquear el proceso.
4. Si el trabajador alega, el rechazo posterior documenta *"al·legació del treballador valorada"*; la justificación del registro manual entra automáticamente como alegación inicial.
5. Todo motivado y trazado (`rejection_reason` obligatorio, `work_log_modifications` visible para el trabajador).
Protocolo de actuación para coordinación:
1. **Detección**: el tramo aparece como pendiente con su veredicto, la **distancia medida y el radio efectivo aplicado** (`home_distance_m`/`home_radius_m`); el trabajador ve lo mismo en su propia app (transparencia simétrica).
2. **Audiencia previa obligatoria**: antes de rechazar, el coordinador debe recabar la explicación del trabajador (contacto directo o nota en el sistema). Causas admisibles típicas: error de precisión GPS en interior, cambio de domicilio del paciente no actualizado, atención excepcional fuera del domicilio (acompañamiento, gestión).
3. **Resolución motivada**: aprobación, o rechazo con el motivo escrito que la API exige; el motivo queda en la trazabilidad (`work_log_modifications`) y es visible para el trabajador.
4. **Efecto limitado**: el rechazo de un tramo afecta al cómputo de tiempo efectivo de ese tramo; **no constituye sanción disciplinaria**, que en su caso seguiría el cauce del ET y convenio con sus propias garantías.
5. **Discrepancia**: el trabajador puede hacer constar su desacuerdo ante RRHH y ejercer sus derechos ante el DPD (dpd@crtbcn.cat).

### 6.3 Restricción de visualización de coordenadas y mapas — IMPLEMENTADA

La visualización de posiciones históricas está limitada por diseño (verificado en ejecución), distinguiendo entre acceso legítimo del responsable y acceso libre:
- **Listados**: nunca incluyen coordenadas.
- **Detalle de un fichaje** — supuestos de visibilidad, todos con registro de auditoría de cada consulta:
  1. **Rol de administración**: acceso en ejercicio del control empresarial legítimo (art. 20.3 ET). No es acceso "libre": **cada consulta queda registrada** en el log de accesos con identificación de quién, cuándo y sobre qué fichaje (la disuasión y la trazabilidad sustituyen a la prohibición, que la norma no exige para el responsable).
  2. El **propio trabajador** (transparencia simétrica, art. 15 RGPD).
  3. Otros roles autorizados (coordinación): **solo** si el fichaje está **en conflicto** (fuera de zona/radio, tramo rechazado, fuera de cuadrante o alerta pendiente) o con **motivo de auditoría explícito**, que queda registrado **literalmente**.
- Fuera de esos supuestos, la respuesta omite las coordenadas (los booleanos y distancias sí viajan: el control funciona sin exponer la posición) y se marca `coords_restringides`.
Consecuencia: **no existe acceso sin traza** — toda visualización de una posición por cualquier rol queda auditada, y los roles no administradores solo ven posiciones con causa concreta.

### 6.4 Método alternativo de registro — protocolo formal

Para el trabajador que no desee usar ningún dispositivo con geolocalización, cascada formal de registro (todas las vías con **idéntica validez** a efectos del art. 34.9 ET):
1. **Tablet corporativa sin permisos de ubicación**: el trabajador ficha en la aplicación con el GPS denegado; el sistema registra la jornada con verificación `no_disponible` (nunca en su contra). Es la vía por defecto: no requiere ningún medio personal.
2. **Registro manual en la aplicación** (parte de jornada digital): entrada/salida introducidas por el trabajador sin captura de posición, marcadas como registro manual y visibles como tales en la revisión.
3. **Parte de jornada en papel** (formulario normalizado con fecha, horas y firma), entregado a coordinación, que lo digitaliza en RRHH en un plazo máximo de 7 días; la digitalización queda trazada en `work_log_modifications` con identificación de quién la realiza.
**Flujo automático implementado (verificado en navegador):** si el dispositivo deniega el GPS (solo posible en dispositivo personal, ver §5-MDM), la aplicación **deriva automáticamente al "Registre manual amb justificació"**: un diálogo no bloqueante ofrece motivos tasados más un texto libre **obligatorio con un mínimo de 15 caracteres** — la obligatoriedad se fuerza **en servidor** (422 sin justificación suficiente cuando no hay GPS), no solo en la interfaz —, registra la jornada/hito igualmente y deja la justificación trazada en `work_log_modifications` (acción `registre_manual`), visible en la trazabilidad del fichaje. La alegación de audiencia exige un mínimo de 20 caracteres. Si el fallo es de sensor/señal (posible también en la tablet), el registro se hace **solo, con anotación automática**, sin molestar al trabajador. El trabajador nunca queda bloqueado en su labor asistencial. **El mismo flujo existe en el fichaje directo de CRT RRHH** (verificado): sin GPS, el fichaje se crea sin coordenadas y **sin veredicto de posición** (`location_match` nulo — nunca «fuera de zona» por falta de dato), con la justificación trazada.
En ningún caso la elección de una vía alternativa puede suponer perjuicio, presunción de ausencia ni sanción (compromiso ya recogido en el aviso legal y en la comunicación individual del Anexo I).

### 6.5 Encargado de tratamiento — hosting (art. 28 RGPD) [HECHO A VERIFICAR]

Los sistemas se alojan en infraestructura de **IONOS** (encargado de tratamiento de infraestructura). Antes de la activación debe verificarse que el contrato de encargo (DPA) cumple el art. 28.3 RGPD con este checklist mínimo:
- objeto, duración, naturaleza y fin del tratamiento; tipo de datos (incluida geolocalización de trabajadores) y categorías de interesados;
- tratamiento solo bajo instrucciones documentadas del responsable; confidencialidad del personal;
- medidas del art. 32 (el anexo de seguridad debe cubrir expresamente los datos de geolocalización);
- régimen de subencargados con autorización previa; asistencia en derechos y en brechas (soporte a la notificación en 72 h);
- supresión/devolución al fin del contrato; auditabilidad; **residencia de los datos en la UE**.
**Mitigación estructural ya implementada**: las coordenadas se almacenan cifradas AES a nivel de aplicación con clave que NO reside en el proveedor de base de datos — IONOS no puede acceder al dato de posición en claro aunque acceda al almacenamiento. Este hecho debe constar en el contrato como medida del responsable, sin eximir al encargado de sus propias obligaciones del art. 32.

## 7. Análisis de riesgos y mitigación

| Riesgo | Mitigación implementada | Riesgo residual |
|---|---|---|
| Monitorización desproporcionada del trabajador | Solo hitos; sin trayectorias; fuera de horario no se registra (salvo complementarias autorizadas); radio justificado técnicamente (§6.1) y calibrable con datos del piloto | Bajo. El DPD valida la cifra final del radio con los datos empíricos del piloto |
| Invalidación arbitraria de tramos (`fora_radi`) | Rechazo técnicamente imposible sin motivo escrito; audiencia previa obligatoria (§6.2); trazabilidad visible al trabajador | Bajo |
| Fuga de datos de pacientes hacia el sistema laboral | Cálculo de distancia local; referencia opaca; RRHH no puede reidentificar al paciente | Muy bajo (requeriría acceso simultáneo a ambas BD, mismo responsable) |
| Acceso indebido a ubicaciones históricas | AES en reposo + log de accesos + purga 48 m + **visualización restringida a supuestos tasados con motivo auditado (§6.3, implementada)** | Muy bajo |
| Robo/uso indebido del token de servicio | Alcance acotado por middleware (solo lectura de plantilla + jornada); secreto fuera del repo; TLS verificado | Bajo |
| Falsos positivos de ausencia (GPS impreciso, permisos retirados) | Veredicto `no_disponible` honesto; método alternativo; garantía escrita de no sanción por retirar permisos | Bajo |
| Caída de un sistema arrastra al otro | Modos A/B probados en ambos sentidos; sin dependencia bloqueante | Muy bajo |
| Fabricación de presencia (registro retroactivo) | Ventana ±15 min en servidor | Bajo |

## 8. Juicio de idoneidad, necesidad y proporcionalidad (posición de la empresa)

- **Idoneidad:** la plantilla domiciliaria trabaja dispersa en domicilios de pacientes, sin centro físico donde fichar. La verificación por hitos es el único mecanismo que acredita de forma fiable la prestación real del servicio ligada al registro de jornada, en un servicio público concertado (CatSalut) sujeto a auditoría.
- **Necesidad — alternativas evaluadas y descartadas:**
  - *Fichaje sin geolocalización:* no acredita presencia en un servicio esencialmente presencial y auditablemente facturado; ya existía y motivó la necesidad de control adicional.
  - *Seguimiento continuo:* descartado y **eliminado** por desproporcionado (era el diseño anterior; su retirada es evidencia de privacy-by-design sobrevenido y corregido).
  - *Firma del paciente como única prueba:* se mantiene para facturación, pero admite registro retroactivo y no acredita jornada.
- **Proporcionalidad:** captura mínima (instante del hito), veredicto en lugar de datos brutos cuando es posible, cifrado, purga, no-sanción automática, voluntariedad del dispositivo y método alternativo. El interés legítimo empresarial (art. 20.3 ET) se ejerce con el paquete completo de garantías del art. 90 LOPDGDD.

## 9. Derechos de los interesados

- **Trabajadores:** acceso/rectificación/supresión/limitación/oposición vía DPD (dpd@crtbcn.cat). El detalle de sus tramos y verificaciones es visible para ellos en la propia app (transparencia). La supresión de coordenadas opera además de oficio por purga.
- **Pacientes:** sin cambio — sus datos no entran en el flujo de integración.
- **Representación legal de los trabajadores:** información previa conforme a arts. 64 ET y 90.2 LOPDGDD antes de la activación en producción (véase §10).

## 10. Condiciones previas a la puesta en producción (pendientes declarados)

1. **EIPD formal** elaborada por el DPD sobre esta memoria, previa a la activación de hitos con trabajadores reales.
2. **Información a la representación de los trabajadores** (art. 90.2 LOPDGDD / 64 ET) y firma del aviso reformulado por la plantilla afectada.
3. **Piloto controlado** (1-2 fisioterapeutas, con la tablet corporativa Blackview Zeno 5 real) para validar: tiempo de fix GNSS en domicilios, distribución real de precisiones y distancias, flujo de permisos de ubicación en Chrome/Android 16 y cobertura LTE en las zonas asignadas. Al término, el informe `pilot:radi-report` (§6.1.bis) documenta la tasa de error real y el radio mínimo justificado, que el DPD incorpora a la EIPD antes del despliegue general.
4. Fase 2 técnica (residual): control nativo del sensor **únicamente en la app híbrida Capacitor de CRT RRHH** (en la aplicación web de Domiciliària es técnicamente imposible y las garantías son de servidor, ver §5); verificación adicional vía OSRM (ruta a pie) si el DPD lo considera proporcionado. La restricción de visualización de mapas ya NO es Fase 2: está implementada (§6.3).
5. Saneado de identificadores (emails de fisioterapeutas coincidentes entre sistemas) para el mapeo; sin coincidencia, el trabajador registra directamente en RRHH (sin pérdida de derechos).
6. **Verificación del contrato de encargo con IONOS** conforme al checklist del §6.5 (art. 28 RGPD), con el anexo de seguridad cubriendo expresamente la geolocalización.

## 11. Verificación

Todo lo descrito como implementado fue **ejecutado y verificado el 17/07/2026** en entorno local con réplicas de ambas bases de datos reales: flujo completo navegador→Domiciliària→RRHH (jornada, hito verificado a 20 m del domicilio geocodificado, hito fuera de radio a 5,6 km, pausa, cierre en Modo A y en Modo B), cifrado comprobado en BD, purga en dry-run, y rechazo de hitos retroactivos. Los scripts y datos de prueba fueron eliminados tras la verificación.

**Limitaciones de esta memoria:** describe el estado en las ramas indicadas, aún no desplegadas en producción; la prueba con GPS de dispositivo móvil real está pendiente (piloto); la EIPD formal y su juicio jurídico corresponden al DPD.

---

## 12. ANEXO I — Comunicación individual al trabajador (art. 90.2 LOPDGDD)

Texto para entrega **individual y previa** a cada trabajador afectado, con acuse de recibo firmado. Se entrega en catalán (lengua de trabajo de la plantilla y de la aplicación); la IA legal producirá la versión castellana espejo. Este documento NO es un consentimiento (la base jurídica no lo requiere): es la **información previa expresa** que exige el art. 90.2 LOPDGDD, y así debe rotularse.

> **INFORMACIÓ PRÈVIA SOBRE EL SISTEMA DE REGISTRE DE JORNADA AMB VERIFICACIÓ DE PRESÈNCIA — CRT RRHH / CRT Domiciliària**
>
> **De:** CRT (responsable del tractament) · DPD: dpd@crtbcn.cat
> **A:** [Nom i cognoms del treballador/a] · **Data de lliurament:** [data]
>
> D'acord amb els articles 90.2 de la LOPDGDD i 64 de l'Estatut dels Treballadors, t'informem de manera prèvia, expressa, clara i inequívoca de les característiques del sistema de registre de jornada:
>
> **1. Què fa el sistema.** El registre legal de jornada (art. 34.9 ET) es fa a CRT RRHH. Si treballes en l'àmbit domiciliari, pots operar-lo des de CRT Domiciliària: en marcar l'arribada i la sortida de cada visita, el sistema captura la teva posició GPS **només en aquell instant** i verifica si ets al domicili assignat.
>
> **2. Què NO fa.** No hi ha seguiment continu ni localització fora dels moments de marcatge. No es registra cap posició fora de la teva jornada (les hores complementàries autoritzades amb codi sí que en formen part). Cap dada del pacient viatja al sistema de RRHH.
>
> **3. Com es verifica.** La distància al domicili es compara amb un radi que és el **mínim tècnic**: 50 metres més l'error que el teu propi GPS reporta en aquell moment (màxim 150 m). Si el GPS no està disponible o el domicili no està ben geocodificat, el sistema ho anota com a "no disponible" i **mai en contra teva**.
>
> **4. Garanties.** Les coordenades es guarden **xifrades**, s'eliminen automàticament als 48 mesos, i cada accés d'una persona autoritzada queda auditat. Un marcatge "fora de radi" **mai es rebutja automàticament**: queda pendent, se't demanarà la teva explicació abans de resoldre'l (dret d'audiència), el motiu del rebuig ha de constar per escrit i el pots veure. El rebuig d'un tram no és cap sanció disciplinària.
>
> **5. Dispositiu.** L'empresa et facilita una **tauleta corporativa** per treballar. L'ús del teu telèfon personal és **completament voluntari**. Si retires els permisos d'ubicació, disposes d'un mètode de registre alternatiu amb la mateixa validesa, i això **no es considerarà absència, incompliment ni motiu de sanció**.
>
> **6. Els teus drets.** Pots exercir els drets d'accés, rectificació, supressió, limitació, portabilitat i oposició davant del DPD (dpd@crtbcn.cat) i reclamar davant l'AEPD (aepd.es). Pots consultar en tot moment els teus registres i les seves verificacions dins de la mateixa aplicació.
>
> ---
> **Justificant de recepció** (no implica conformitat ni consentiment, únicament recepció de la informació):
> Nom i cognoms: _______________ DNI: _______________ Data: ____________ Signatura: _______________

**Registro de entrega:** RRHH conservará el acuse firmado de cada trabajador junto a la EIPD (accountability, art. 5.2 RGPD). La representación legal de los trabajadores recibirá esta misma información con carácter previo (art. 90.2 LOPDGDD y 64.4 ET), junto con la EIPD aprobada.
