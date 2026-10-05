# Guía para trabajar en CRT RRHH sin acceso al servidor

Para quien mejora la app **solo con el código de GitHub** (sin aaPanel, sin Jenkins, sin SSH) y
para la IA que le ayude. CRT RRHH es la misma app que CRIL SALUT RRHH adaptada a CRT; lo que cambia
es cómo se monta y cómo se despliega.

---

## 1. Cómo llega un cambio a producción

**Se despliega haciendo `push` a la rama `branch-deploy`.** Jenkins la vigila (webhook de GitHub)
y despliega solo. `main` es donde se prepara el cambio: subir a `main` **no** publica nada.

```
main  ──(merge --no-ff)──►  branch-deploy  → Jenkins despliega TODO (web + API + panel) en crtrrhh.crtbcn.cat
```

```bash
# 1. Trabajar y hacer commit en main (si se ha tocado el frontend, ver el paso 2 antes)
git checkout main && git pull origin main
git add <ficheros> && git commit -m "feat: ..."
git push origin main

# 2. Publicar
git checkout branch-deploy && git pull --ff-only origin branch-deploy
git merge --no-ff -m "merge: <resumen>" main && git push origin branch-deploy
git checkout main
```

### ⚠️ La web va compilada dentro del repositorio
El servidor **no tiene Node**: sirve la web tal cual está en `public/app`. Si cambias algo de `src/`,
antes de hacer commit hay que compilarla y subir también el resultado:

```bash
npm ci            # la primera vez
npm run build:web # deja la web en public/app (usa .env.web: la API en el mismo dominio)
git add public/app
```

Si no se compila, el código cambia en GitHub pero la web que ve la gente sigue siendo la anterior.

### Qué hace Jenkins (el `Jenkinsfile` de la raíz)
1. Guarda el commit actual y hace **backup de la BD** (`storage/backups/`).
2. Se detiene si el servidor tiene cambios locales sin commit.
3. Coloca el servidor exactamente en `branch-deploy`.
4. `composer install`, `php artisan migrate --force` (**las migraciones nuevas se ejecutan solas**) y caches.
5. Health-check de la API (`/api/v1/health`) y de la web (`/app/`).

### Lo que NO se puede hacer sin acceso al servidor
Comandos `artisan` manuales (crear cuentas de gestión, importaciones), ver logs, el `.env` o la BD,
y el rollback de la BD. Para deshacer código: `git revert <commit>` y publicar de nuevo.

---

## 2. Estructura

| Qué | Dónde |
|---|---|
| API (Laravel) | `app/Http/Controllers/Api`, `app/Models`, `routes/api.php`, `database/migrations`, `config/` |
| **Web (Vue 3 + Vite), código** | **`src/`** en la raíz |
| Web compilada (lo que se sirve) | `public/app` → se regenera con `npm run build:web` |
| Panel Laravel/Inertia | `resources/js`, compilado en `public/build` |
| Tests | `tests/Feature` (PHPUnit, SQLite en memoria) |
| Normas del proyecto | `docs/DIRECTRICES_PROYECTO.md` |

Producción: **un solo dominio** `crtrrhh.crtbcn.cat` (web en `/app/`, API en `/api/`, panel en
`/dashboard`), PHP 8.3 y **MySQL 8** (sí aplica las restricciones `CHECK`).

---

## 3. Reglas que no se pueden romper

1. **Base de datos solo aditiva**: migraciones nuevas y reversibles; nunca modificar una ya ejecutada
   ni renombrar o borrar columnas.
2. **Rutas de API solo aditivas.**
3. **Nada de `env()` fuera de `config/`**: el despliegue hace `config:cache` y `env()` devuelve `null`.
   Definirlo en `config/*.php` y leerlo con `config()`.
4. **Textos de la app en catalán.** Los códigos internos (`pending`, `approved`…) pasan por
   `src/utils/etiquetes.js`.
5. **Nunca datos personales en el repositorio** (volcados de BD, Excels, paquetes de migración).
6. **Nunca ejecutar el `DatabaseSeeder` en producción**: crea un administrador con contraseña `123456`
   y personas de prueba. Las cuentas de gestión se crean con `php artisan crt:crea-admin` (abajo).

---

## 4. Trampas conocidas

- **Horas de fichaje en hora de Madrid sin zona** (`2026-10-02T08:20:00.000`): en el frontend, el día y
  la hora se leen del texto, sin convertirlos con la zona del dispositivo.
- `GET /v1/work-logs` sin parámetros devuelve solo los 500 más recientes; para un mes, `?mes=AAAA-MM`.
- `work_log_modifications.action` es un enum (`created, segmented, approved, rejected, modified,
  break_started, break_completed, break_skipped, registre_manual, allegacio`).
- MySQL 8 no admite un `CHECK` sobre una columna con FK `ON DELETE SET NULL`.
- Esta app viene de CRIL SALUT RRHH: las mejoras de allí se pueden traer con `git cherry-pick`
  (añadiendo el repositorio de CRIL como remoto), y luego hay que pasar `CRIL` → `CRT` en textos,
  claves de `localStorage` (`cril_*` → `crt_*`) y dominios.

---

## 5. Antes de subir

```bash
npm run build:web                       # si se ha tocado src/
composer install
APP_KEY="base64:$(head -c 32 /dev/urandom | base64)" php artisan test
```

El test `ExampleTest` falla siempre (la portada redirige a `/app/` y espera un 200): no es un error.

---

## 6. Tareas de servidor (solo quien tiene acceso)

**Cuentas de gestión** (la contraseña se pide por terminal y no queda en ningún sitio):
```bash
php artisan crt:crea-admin <correo> "<Nombre>"            # administración
php artisan crt:crea-admin <correo> "<Nombre>" --rol=hr   # Recursos Humanos
```

**Dar de alta el despliegue automático en Jenkins** (una sola vez):
1. Jenkins → *New Item* → nombre `RRHH CRT-Deploy` → tipo **Pipeline**.
2. *Pipeline* → **Pipeline script from SCM** → Git → repositorio
   `git@github.com:informatica-cril/CRT-RRHH.git`, credencial de GitHub la misma que usan los jobs de CRIL,
   rama `*/branch-deploy`, *Script Path* `Jenkinsfile`.
3. *Build Triggers* → **GitHub hook trigger for GITScm polling**.
4. Comprobar que existe la credencial SSH **`credenciales-ssh`** (la de los jobs de CRIL); el `Jenkinsfile`
   se conecta a `administrador@192.168.1.184`.
5. GitHub → repositorio `CRT-RRHH` → *Settings → Webhooks* → *Add webhook*: la misma URL de Jenkins
   que tiene el webhook del repositorio de CRIL (acaba en `/github-webhook/`), *Content type*
   `application/json`, evento *Just the push event*.
6. Lanzar el job una vez a mano (*Build Now*) para comprobar que conecta y despliega.

---

## 7. Resumen para la IA

> Laravel (API + panel) y Vue 3 (`src/`), todo en un dominio. La web se sirve compilada desde
> `public/app`: tras tocar `src/`, `npm run build:web` y subir `public/app`. Publicar = merge `--no-ff`
> de `main` a `branch-deploy` + push (Jenkins hace backup, migraciones y health-check). Producción en
> MySQL 8. Migraciones y rutas solo aditivas; nada de `env()` fuera de `config/`; textos en catalán;
> nunca datos personales en el repo; nunca el `DatabaseSeeder` en producción.
