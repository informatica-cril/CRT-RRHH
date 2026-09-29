# Segon factor (2FA) a CRT RRHH

**Estat actual: DISPONIBLE i OPCIONAL. No s'ha imposat a ningú.** Cap persona de la plantilla
entra avui de manera diferent a com entrava ahir. Aquest document explica què hi ha muntat i
quins interruptors ha de tocar Direcció, i quan, si un dia decideix imposar-lo.

---

## 1. Què fa falta perquè una persona pugui activar-se'l

Dos passos, en aquest ordre:

1. **Un admin li assigna el mètode `totp`**
   `PUT /api/v1/users/{id}/2fa` amb `{"method": "totp"}` (només rol `admin`).
   Mentre el mètode sigui `dispositiu` (el valor per defecte de tothom), la persona **no es
   pot enrolar**: l'endpoint d'enrolament li respon `422 not_totp`.
2. **La persona s'enrola ella mateixa** des del seu compte:
   - `POST /api/v1/auth/2fa/prepare` → retorna `secret` i `uri` (`otpauth://…`, per al QR).
   - Afegeix el compte a Google Authenticator / Authy / 1Password.
   - `POST /api/v1/auth/2fa/confirm` amb `{"code": "123456"}` → **retorna els 10 còdis de
     recuperació**. És l'únic moment en què existeixen en clar.

A partir d'aquí, i **només per a ella**, el login li demanarà el codi.

## 2. Els tres interruptors, de menys a més abast

| Nivell | Com s'activa | A qui afecta |
|---|---|---|
| **0. Voluntari** (actual) | res a tocar | només qui completa l'enrolament pel seu compte |
| **1. Per rols** | `SECOND_FACTOR_ROLES=admin,hr` al `.env` | tothom amb aquests rols |
| **2. Tothom** (Fase B) | `SECOND_FACTOR_REQUIRED=true` al `.env` | tota la plantilla |

Per sobre de tots, **kill-switch d'emergència**: `SECOND_FACTOR_OFF=1` deixa entrar sense segon
factor encara que la resta estigui activada. És per al dia que una cosa surti malament a les
08:00 i hi hagi trenta persones a la porta.

Després de tocar el `.env` cal `php artisan config:clear` (o `config:cache`) a producció.

### ⚠️ Abans d'activar el nivell 1 o el 2

Qui queda obligat i **no s'ha enrolat encara** no podrà entrar fins que s'enroli — i per
enrolar-se necessita haver entrat. Per tant, l'ordre correcte és:

1. Assignar el mètode `totp` a les persones afectades.
2. Avisar-les i deixar-les enrolar-se (nivell 0, voluntari).
3. Comprovar amb `GET /api/v1/auth/2fa` de cadascuna que `enrolled: true`.
4. Només llavors, activar `SECOND_FACTOR_ROLES` o `SECOND_FACTOR_REQUIRED`.

Nota sobre el mètode `dispositiu`: si s'activa el nivell 2 amb gent en mètode `dispositiu`,
aquestes persones només entraran des d'un dispositiu corporatiu aprovisionat (token HMAC de
`services.disp_corp.key`). Sense l'aprovisionament fet, es queden fora.

## 3. Còdis de recuperació

- Se'n lliuren **10** en confirmar l'enrolament. Es desa **només el hash** (sha256), i el JSON
  sencer va xifrat amb `APP_KEY`: ni un admin amb accés a la base els pot llegir.
- **Un sol ús** cadascun. S'accepten al login al camp `codi_recuperacio`, amb guions o sense,
  en majúscules o minúscules.
- Cada consum queda a `audit_logs` amb els que queden.
- Regeneració: `POST /api/v1/auth/2fa/recovery-codes` amb un codi vigent de l'app. **Invalida
  els anteriors.**
- Si algú es queda sense mòbil i sense còdis: un admin li torna a posar el mètode
  (`PUT /users/{id}/2fa` amb `totp`), cosa que **esborra l'enrolament**, i la persona es torna
  a enrolar. Aquesta és la via de rescat i queda traçada amb qui l'ha feta (`second_factor_by`).

## 4. Desactivació

`DELETE /api/v1/auth/2fa` amb `{"password": "...", "code": "123456"}` (o `codi_recuperacio`).

- Exigeix **contrasenya i codi**: desactivar el 2FA és el primer que faria qui robés una sessió.
- Si Direcció l'ha declarat obligatori per a aquesta persona (nivell 1 o 2), respon **403**: no
  se'l pot treure.
- Tampoc es pot **reenrolar** en calent (`prepare` respon `422 already_enrolled` si ja hi ha un
  enrolament actiu): per canviar de mòbil cal desactivar-lo primer, amb contrasenya i codi.

## 5. Endpoints

| Mètode | Ruta | Qui |
|---|---|---|
| `GET` | `/api/v1/auth/2fa` | l'usuari — estat propi (`method`, `enrolled`, `required`, `enforced`, `recovery_codes_left`) |
| `POST` | `/api/v1/auth/2fa/prepare` | l'usuari — genera secret + URI otpauth |
| `POST` | `/api/v1/auth/2fa/confirm` | l'usuari — confirma i rep els còdis de recuperació |
| `POST` | `/api/v1/auth/2fa/recovery-codes` | l'usuari — regenera els còdis (cal codi vigent) |
| `DELETE` | `/api/v1/auth/2fa` | l'usuari — desactiva (cal contrasenya + codi) |
| `PUT` | `/api/v1/users/{id}/2fa` | **admin** — assigna el mètode (`dispositiu`\|`totp`) |

Al login, `POST /api/v1/auth/login` accepta `totp_codi` i `codi_recuperacio`. Quan falta el
segon factor respon **200** amb `{"second_factor_required": true, "method": "totp"}` i **sense
token**: el client ha de demanar el codi i reenviar les credencials.

## 6. Què NO hi ha

- **Pantalla al frontend Vue.** Tot el 2FA és avui API pura: no hi ha ni el QR d'enrolament, ni
  el camp del codi al login, ni la llista de còdis de recuperació. Mentre no es faci, l'única
  manera d'enrolar-se és per API. **Cap persona pot activar-se'l des de la interfície**, cosa
  que reforça que ara mateix no canvia la dinàmica de treball de ningú.
- Recordatoris o campanyes d'enrolament.
- Confiança de dispositiu ("no m'ho tornis a demanar en 30 dies").

## 7. Proves

`tests/Feature/SecondFactorTest.php` — 23 proves: vectors RFC 6238, finestra de temps,
enrolament, els tres interruptors, el kill-switch, còdis de recuperació (lliurament, consum
d'un sol ús, regeneració), desactivació amb i sense codi de recuperació, prohibició de
desactivar el que és obligatori, prohibició de reenrolar en calent, i que ni el secret ni els
còdis surten mai per l'API.
