> ⚠️ **OBSOLET per a CRT.** Descriu el desplegament en dues apps (front i API separats) heretat del projecte original.
> CRT es desplega com **una sola app** a `https://crtrrhh.crtbcn.cat`: vegeu [DESPLEGAMENT_AAPANEL.md](DESPLEGAMENT_AAPANEL.md).

# Checklist de desplegament — CRT RRHH + CRT Domiciliària (integració)

Per a informatica@crtbcn.cat. Les dues apps es despleguen **per separat** (repos i
mecanismes propis); aquest document ordena els passos i les dependències.
Estat verificat en local a 17/07/2026 (ramas `crt-rrhh-actualizaciones` i `integracio-rrhh`).

## 0. Desplegament ràpid i segur (resum operatiu)

**Ordre**: RRHH primer (exposa `/domi/*`), domi després (els consumeix). Si s'inverteix,
la integració degrada a Mode B (vàlid, sense hites) fins que RRHH estigui al dia.

**RRHH — un sol push per destí, tot automatitzat** (workflows millorats):
1. `git checkout branch-api && git merge crt-rrhh-actualizaciones && git push` →
   el workflow fa **backup de BD** (`storage/backups/`), `migrate --force`, caches i
   **health-check** (`/api/v1/health`); si el health falla, **avorta i deixa el punt de
   rollback** (commit previ a `storage/.last_deploy_commit` + backup).
2. `git checkout branch-app && git merge crt-rrhh-actualizaciones && git push` →
   build reproduïble (`npm ci`), **no publica un build buit**, backup del front i health-check.
3. Verificació d'integració (no només API):  `ssh … && php artisan deploy:verify`
   (confirma migracions, xifratge, sonda de domi/Mode A, compte de servei i clau de dispositiu).

### 0.bis Desplegament MANUAL (`git pull`) — llegir sí o sí

Si el desplegament es fa a mà al servidor (rama única `main`) en lloc del workflow,
el `git pull` **no fa el backup ni el health-check automàtics**. Seqüència obligatòria:

```bash
mysqldump -u <user> -p <db> | gzip > pre-deploy-$(date +%F).sql.gz   # 1. BACKUP PREVI (imprescindible: la migració de xifratge altera dades)
git pull origin main                                                  # 2. codi
composer install --no-dev --optimize-autoloader                       # 3. deps de PRODUCCIÓ (sense Faker/PHPUnit/Ignition)
php artisan migrate --force                                            # 4. migracions
php artisan config:cache && php artisan route:cache                    # 5. caches
curl -fsS https://<host>/api/v1/health                                # 6. health-check manual → {"ok":true}
```

**⛔ MAI executar `php artisan key:generate` amb dades ja carregades.** Les coordenades
(`work_logs`/`work_log_segments`) estan xifrades amb `APP_KEY` (cast `encrypted`).
Regenerar la clau deixa **totes les coordenades permanentment il·legibles** i el detall
de fichatge peta amb `DecryptException`. `APP_KEY` es fixa **una sola vegada** al primer
`.env` de producció i no es torna a tocar.

**⏰ CRON del scheduler de Laravel — cal instal·lar-lo (RRHH, no només domi).** Sense
aquesta línia al crontab del sistema, **no s'envien recordatoris de jornada sense tancar
i NO es purguen les coordenades** (`locations:purge`, retenció EIPD/RGPD) → no-conformitat
silenciosa a partir del dia 30:

```cron
* * * * * cd /ruta/rrhh && php artisan schedule:run >> /dev/null 2>&1
```

(Aquest cron és independent del `purga_coords_hitos.php` de domi del §2.2b; cada app té el seu.)

**Rollback ràpid** (si el health-check ha avortat o cal revertir):
- Codi:  `git reset --hard $(cat storage/.last_deploy_commit)` i re-cachejar.
- BD (només si una migració ha corromput dades):  restaurar l'últim `storage/backups/pre-deploy-*.sql.gz`
  (o `php artisan migrate:rollback --force` si el `down()` és net — ho és per a totes).
- Front:  `cp -r ../dist_backup_<data>/* ../dist/`.

**Finestra de la migració de xifratge** (`000003`): xifra TOTES les coordenades
existents. En una taula gran pot bloquejar. Mesura el temps en una rèplica del volum
real i executa en horari de baixa activitat. `down()` reverteix a DECIMAL.

**domi** (desplegament manual): merge `integracio-rrhh` → `master`, `git pull` al
servidor, aplicar el SQL de `bases_de_Datos/migracions/` **amb backup previ**, i copiar
`rrhh_secret.php` (token + claus). No hi ha CI: fer `php -l` dels fitxers tocats abans.

---


## 1. CRT RRHH (API + front)

1. **Backup de BD** previ (les migracions alteren columnes de coordenades a TEXT xifrat).
2. Merge `crt-rrhh-actualizaciones` → `branch-api` (API) i → `branch-app` (front).
   El workflow de GitHub Actions desplega i executa `php artisan migrate --force`
   (migracions `2026_07_17_000002` a `000006`, totes additives/reversibles).
3. `.env` de producció — afegir:
   - `DOMI_HEALTH_URL=https://<host-domi>/api/health.php` (detecció Mode A/B)
   - (opcional geo autoallotjat) `VITE_TILE_URL`, `VITE_OVERPASS_URL` — vegeu `.env.example`
4. Token del compte de servei per a domi: `php artisan domi:service-account`
   (NO usar `--rotate` si ja existeix; MAI fer login amb contrasenya amb aquest compte).
5. Verificació post-desplegament:
   - `GET /api/v1/geolocation/pilot-radi` amb token admin → 200 (buit al principi: normal)
   - un fichatge de prova → coordenades xifrades a BD (`work_logs.start_location_lat` en text llarg)

## 2. CRT Domiciliària

1. Merge `integracio-rrhh` → `master` i desplegament manual habitual.
2. Al servidor: copiar `includes/rrhh_secret.php.example` → `includes/rrhh_secret.php`
   amb el token del pas 1.4 + `RRHH_COORDS_KEY` (64 hex aleatoris) per al xifratge
   local de coordenades (el fitxer està al .gitignore — MAI al repo).
   2b. Cron diari de purga de coordenades (EIPD Annex V):
   `15 3 * * * php /ruta/domi/cronjobsDomi/purga_coords_hitos.php`
   2c. En dictar el DPD el radi definitiu (fi del pilot): afegir
   `define('RRHH_HITO_RADI_DEFINITIU', <metres>)` al mateix fitxer.
3. `crt_geo_addr` poblada (`tools/geocode.php`) — sense adreça de portal, els hitos
   surten `no_disponible` (vàlid però sense verificació de domicili).
4. Verificació: `GET /api/health.php` → 200 · login fisio → widget "Jornada: no iniciada".

## 3. Tablets (Blackview Zeno 5 + Hexnode)

1. Versió **LTE amb SIM de dades** (confirmat). Activar "Precisió d'ubicació de Google" (A-GPS).
2. Hexnode: mode quiosc amb les apps de treball; **política d'ubicació activa forçada**;
   **URL d'arrencada del quiosc amb la clau de dispositiu**:
   `https://<domi>/modulos/Domiciliaria/dashboard.php?disp_corp=<DISP_CORP_KEY>`
   (i per a RRHH: `https://<rrhh>/?disp_corp=<DISP_CORP_KEY>`). La MATEIXA clau es
   defineix a `DISP_CORP_KEY` (.env de RRHH) i `CORP_DEVICE_KEY` (rrhh_secret.php de
   domi). Amb això, en dispositiu corporatiu la denegació del GPS queda bloquejada
   també a l'aplicació (el flux manual és només per a dispositiu personal);
   **auto-grant del permís d'ubicació** per al domini de l'app (sense diàlegs al quiosc).

## 4. Seqüència legal (abans d'activar hitos amb treballadors reals)

EIPD signada pel DPD → informació a la RLT (art. 90.2 LOPDGDD / 64 ET) → lliurament
de la comunicació individual amb justificant de recepció (Annex I de la EIPD) →
**pilot 1-2 fisios amb la Zeno 5** → informe `php artisan pilot:radi-report` al DPD
(fixa el radi definitiu) → desplegament general.

## Dependències creuades

| Si falta... | Efecte |
|---|---|
| DOMI_HEALTH_URL a RRHH | tot surt Mode B (vàlid, però sense hitos) |
| rrhh_secret.php a domi | widget diu "RRHH no disponible"; el fisio registra directament a RRHH |
| crt_geo_addr poblada | hitos `no_disponible` (mai en contra del treballador) |
| Emails coincidents admin↔users | fisio no mapejat: el widget ho diu i deriva a RRHH |

Cap d'aquestes dependències bloqueja el registre legal de jornada: la degradació és
sempre a Mode B / registre manual, per disseny.
