# Integració RRHH → CRT Domiciliària (contracte de consum)

**RRHH és la font de veritat de qui treballa aquí.** La app domi consumeix aquest
contracte per saber, en viu, quins fisioterapeutes domiciliaris estan actius, la seva
zona (geovallat per CP) i la seva jornada, i així repartir i programar pacients.

> No cal cap endpoint nou: `GET /api/v1/users/bulk-index` ja retorna tot el necessari.

---

## 1. Connexió

- **URL base (RRHH_URL):** l'API de Laravel **NO** està al host del front
  (`crtrrhh.crtbcn.cat` → només serveix el build del front, retorna 404 a
  `/api/*`). L'API respon al host de backend: **`https://crtrrhh.crtbcn.cat`**
  (veure `APP_URL` al `.env`). Confirmar amb qui gestiona el desplegament/DNS.
- **Endpoint:** `GET {RRHH_URL}/api/v1/users/bulk-index`
- **Autenticació:** capçalera `Authorization: Bearer <TOKEN>` + `Accept: application/json`.

### Compte de servei (read-only)
Domi ha d'usar un **compte de servei dedicat**, no compartit amb cap persona. Motiu:
`AuthController::login` fa `$user->tokens()->delete()`, així que si es comparteix compte,
cada login mata el token de l'altre i la connexió cau de forma intermitent.

- Generar el token **una sola vegada** a RRHH:  `php artisan domi:service-account`
  (crea l'usuari `servei.domi@crtbcn.cat` amb rol `service` i imprimeix el token Sanctum).
- Guardar el token a la config blindada de domi. **No fer login amb contrasenya** amb aquest
  compte (el token és estable mentre no es faci login).
- El rol `service` és **només lectura**: només pot fer `GET` a `bulk-index` i `users/me`.
  Qualsevol escriptura → `403`.
- Rotar el token si cal:  `php artisan domi:service-account --rotate`

---

## 2. Clau pont: DNI

Domi identifica els fisios per `admin.UserName` i **no té DNI**. L'email **no serveix**
com a clau (bústies compartides, treballadors sense email). La clau bona és **`users.dni`**:
- Els fisios domiciliaris actius tenen DNI al 100% i sense duplicats.
- `bulk-index` exposa `dni` (fa `makeHidden(['password'])`, la resta de camps surten).

Domi ha de mantenir un mapatge `dni → admin.UserName` al seu costat.

---

## 3. Qui està actiu / baixes

`bulk-index` retorna **tots** els usuaris (actius i inactius). Domi ha de:
- **Fisio domiciliari actiu** = `role='worker'` AND `active=true` AND
  `job_profile='Fisioterapeuta'` AND `work_type IN ('DOMICILIARIA','DOMICILIARIA_VALLES')`.
- **Baixa** = un usuari que abans processava i ara ve amb `active=false` (o ja no compleix
  el filtre). En aquest cas domi **ha de retirar-lo i reassignar els seus pacients**.
  (Cas real que va motivar això: un fisio de baixa amb 38 pacients actius, sense que res
  ho detectés.)

---

## 4. Zona (geovallat per CP)

- `zone_assignments`: array de zones amb els seus `users` (ja filtrats per vigència
  `valid_from`/`valid_to` a dia d'avui via el pivot `zone_worker`).
- Cada zona té `postal_codes` (array de CP) i `municipalities` (array).
- Per a un fisio: buscar-lo dins `zone_assignments[].users[]` (per `id`/`dni`) → la seva zona
  són els `postal_codes` (+ `municipalities` per als de Vallès).
- També hi ha `work_locations` (punt `lat`/`lng` + `radius`) via `location_assignments`, per a
  geovallat de punt si cal.

---

## 5. Jornada (disponibilitat)

Cada usuari porta `workSchedule` (o `work_schedules[]` per id). El camp rellevant és **`days`**:

```json
"days": [
  {"day": 1, "active": true, "start": "09:00", "end": "14:00", "name": "Dilluns"},
  {"day": 1, "active": true, "start": "15:00", "end": "18:00", "name": "Dilluns"},  // 2n tram (partida)
  {"day": 6, "active": false, "start": null, "end": null, "name": "Dissabte"}
]
```

⚠️ **Convenció de `day`: `0`=Diumenge, `1`=Dilluns, … `6`=Dissabte** (com `getDay()` de
JS). **NO és ISO 1-7.** Domi ha de mapar en conseqüència (diumenge = 0, no 7).

- **Jornada partida:** s'expressa amb **diverses entrades el mateix `day`** (veure exemple).
  Sumar-ne els trams per a la disponibilitat diària.
- **`active`:** dia laborable = `active:true` amb `start`/`end`. Un dia amb `active:false` o
  sense horari és descans.
- **Robustesa:** dades legacy poden portar entrades **sense el camp `active`**. Regla segura:
  tractar una entrada com a **activa si té `start` i `end`** encara que no porti `active`
  (RRHH normalitza les dades amb `php artisan schedules:normalize-days`, però convé que domi
  ho contempli igualment).

---

## 6. Resum de la resposta de `bulk-index`

| Clau | Contingut |
|------|-----------|
| `users` | tots els usuaris (amb `dni`, `active`, `work_type`, `job_profile`, `workSchedule`) |
| `work_schedules` | totes les plantilles de jornada |
| `zones` | zones actives (`postal_codes`, `municipalities`) |
| `zone_assignments` | zones amb els seus treballadors vigents avui |
| `work_locations` | geovallats de punt (`lat`,`lng`,`radius`) |
| `location_assignments` | ubicacions amb treballadors vigents |
| `ambulatory_centers` / `center_assignments` | (àmbit ambulatori, no domiciliari) |

---

## Interoperabilitat BIDIRECCIONAL (domi → RRHH)

El treballador pot operar la jornada des de domi; **RRHH segueix sent la font de
veritat** del registre i **manté l'autonomia** (si domi cau, RRHH funciona igual).
Domi identifica el treballador pel **DNI**; RRHH el resol i **delega a la mateixa
lògica** (segmentació, validació de zona, hores efectives, pausa). Tota operació
queda registrada a `audit_logs` (`DOMI_CLOCK_IN` / `DOMI_CLOCK_OUT`).

**Autenticació:** token del **compte de servei** (`role=service`). Aquest compte
NOMÉS pot cridar `bulk-index`, `users/me` i les rutes `/domi/*`. Res més.

**Identificació del treballador:** clau PREFERENT `user_id` (l'`id` de RRHH que domi
rep a `bulk-index` i emmagatzema) → domi NO depèn de la seva clau interna
(UserName/DNI). Alternativa acceptada: `dni`. Cal enviar **un dels dos**; si falten
tots dos → `422`. A `break-status/{ident}`, `ident` numèric = `user_id`, si no = DNI.

### Endpoints

| Mètode | Ruta | Cos | Efecte |
|---|---|---|---|
| POST | `/api/v1/domi/jornada/start` | `{user_id\|dni, start_location_lat?, start_location_lng?, start_location_match?, start_location_distance?, location_match?, justificacio?}` | Obre jornada (idempotent: si ja n'hi ha d'oberta, la retorna) |
| POST | `/api/v1/domi/jornada/stop` | `{user_id\|dni, end_location_lat?, end_location_lng?, hours_out_of_area?, justificacio?}` | Tanca la jornada oberta (calcula hores, segments, `verification_mode`) |
| GET | `/api/v1/domi/break-status/{ident}` | — | Estat de pausa perquè domi decideixi mostrar el modal (`break_due`, `trigger_time`) |
| POST | `/api/v1/domi/break/start` | `{user_id\|dni}` | Inicia la pausa obligatòria |
| POST | `/api/v1/domi/break/complete` | `{user_id\|dni}` | Completa la pausa obligatòria |

### Modal de pausa a domi

`break-status` retorna `{has_open_jornada, break_status, break_required,
break_duration_minutes, break_due, trigger_time}`. La **lògica de quan toca la pausa
viu a RRHH** (llindar d'hores + mode offset/fixed). Domi fa **poll** i, quan
`break_due=true`, mostra el modal i crida `break/start`; en tancar-lo, `break/complete`.

### Hitos de servei (el nucli del model)

| Mètode | Ruta | Cos |
|---|---|---|
| POST | `/api/v1/domi/hito` | `{user_id\|dni, tipus: arribada\|sortida, moment: ISO8601, accuracy?, dist_domicili_m?, radi_efectiu_m?, radi_ok?, ref?, justificacio?}` — **SENSE lat/lng (C2 EIPD)** |

- **Temps real obligatori**: `moment` a ±15 min del servidor; si no → `422`. (La firma
  d'assistència retroactiva es queda a domi per a facturació: no és prova de presència.)
- Els hitos **particionen la jornada** en segments `visita` (arribada→sortida, amb
  `home_verification` i `ref`) i `desplacament` (entre visites, compta com a temps
  efectiu aprovable). La primera arribada **obre jornada** automàticament (Mode A).
- **Categorització (RRHH mana)**: `radi_ok=true` → `verificat`; `false` → `fora_radi`;
  absent → `no_disponible`. El pitjor hito del dia mana al `work_log`.
- **Privacitat**: les coordenades del PACIENT mai viatgen — domi calcula la distància
  amb el seu geo local i envia només el veredicte. `ref` és un hash opac.
- En tancar la jornada, RRHH tanca la cua (visita sense sortida → `pending` per a
  revisió humana; desplaçament final) i **no trepitja** el mode A ni la categorització.

### Detecció Mode A/B (health)

RRHH consulta `DOMI_HEALTH_URL` (a domi: `api/health.php`, públic, sense dades, 200 =
viu) amb timeout 2 s abans de categoritzar el tancament:
- respon → **Mode A** (hitos + verificació de domicili)
- no respon → **Mode B** (RRHH autònom: fitxatge simple + validació per zona)

⚠ Desenvolupament local: el servidor integrat de PHP ha de ser multi-worker
(`PHP_CLI_SERVER_WORKERS=4 php -S ...`), si no la crida health de RRHH cap a domi es
bloqueja (monofil) i tot surt Mode B. En producció (Apache/nginx) no passa.

| GET | `/api/v1/domi/incidencies-obertes` | — | Refs de visita amb incidència OBERTA (tram pendent o alerta): la purga de coordenades de domi els EXCLOU fins a resolució |

### C2 EIPD — les coordenades brutes NO viatgen al sistema laboral

Des de l'EIPD definitiva, el hito NO envia lat/lng: RRHH rep només el veredicte i
les seves metadades (distància, radi, precisió). Les coordenades resideixen a
Domiciliària (taula `crt_hito_coords`, xifrades AES-256-CBC amb `RRHH_COORDS_KEY`)
amb purga diària a 30 dies via `cronjobsDomi/purga_coords_hitos.php`, que consulta
`incidencies-obertes` i CONSERVA les coordenades de visites amb incidència en estudi
(fail-safe: si RRHH no respon, no es purga res aquell dia). El tram pendent no compta
com a temps efectiu fins que l'audiència el resol.

### Registre manual amb justificació

Tots els endpoints d'escriptura accepten `justificacio` (string ≤500): quan el
treballador no té GPS (denegat en dispositiu personal, o sensor sense senyal), el
registre es fa igualment i la justificació queda traçada a `work_log_modifications`
(acció `registre_manual`), visible a la traçabilitat del fichatge. El registre MAI
es bloqueja per absència de GPS.

### Coordenades

Les coordenades crues s'emmagatzemen **xifrades AES en repòs** (work_logs,
location_tracking i work_log_segments); els resultats de validació
(`location_match`, distàncies, booleans) es guarden en clar. Domi només
ha d'enviar lat/lng als hitos; no cal enviar res més.

### Provisionament del secret (compte de servei)

1. A RRHH: `php artisan domi:service-account` → crea/mostra el token (role `service`,
   acotat a `bulk-index`, `users/me` i `/domi/*`).
2. A domi: copiar `includes/rrhh_secret.php.example` → `includes/rrhh_secret.php`
   (gitignored) i posar-hi el token i, si cal, `RRHH_URL`.
3. **MAI** regenerar el token a la lleugera ni fer login amb usuari/contrasenya des de
   domi: ambdues coses revoquen el token vigent i maten la integració en silenci.
