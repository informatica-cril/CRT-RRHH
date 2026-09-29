# Despliegue de CRT RRHH en aaPanel

Una sola aplicación Laravel en **un solo dominio**:

| URL | Qué es |
|---|---|
| `https://crtrrhh.crtbcn.cat/` | Redirige a `/app/` |
| `https://crtrrhh.crtbcn.cat/app/` | Web de control horario (Vue, ya compilada en `public/app`) |
| `https://crtrrhh.crtbcn.cat/api/` | API |
| `https://crtrrhh.crtbcn.cat/dashboard` | Panel Laravel/Inertia (ya compilado en `public/build`) |

El servidor **no necesita Node**: los dos fronts van compilados en el repositorio.
Solo hacen falta PHP ≥ 8.1 (recomendado 8.3), MySQL 8, Composer y Git.

---

## 1. DNS

Registro **A** `crtrrhh` en la zona `crtbcn.cat` → IP del servidor.
Si ha de ser accesible desde fuera, también en el DNS público (y el 80/443 abiertos para Let's Encrypt).

## 2. Crear el sitio en aaPanel

1. **Website → Add site**
   - Domain: `crtrrhh.crtbcn.cat`
   - PHP: **8.3**
   - Database: MySQL, nombre p. ej. `crt_rrhh` (anota usuario y contraseña)
2. Borra los ficheros por defecto de `/www/wwwroot/crtrrhh.crtbcn.cat` (`index.html`, `404.html`, `.htaccess`…; **no** borres `.user.ini` si aaPanel no te deja, se desactiva en el paso 4).

## 3. Código

Por terminal SSH (o *Terminal* de aaPanel), como el usuario `www` o arreglando permisos después:

```bash
cd /www/wwwroot/crtrrhh.crtbcn.cat
git clone git@github.com:informatica-cril/CRT-RRHH.git .
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

> El servidor necesita una *deploy key* de GitHub (Settings → Deploy keys del repo) para el `git clone` por SSH.

## 4. Configuración del sitio (aaPanel → el sitio → *Conf*)

- **Site directory → Running directory: `/public`** ⚠️ Imprescindible: si no, se expone `.env` y el código.
- **Site directory → Anti-XSS attack (open_basedir): desactivado** (Laravel lee fuera de `public/`).
- **URL rewrite:** plantilla `laravel5`.
- **SSL → Let's Encrypt** para `crtrrhh.crtbcn.cat` y activa **Force HTTPS**.
- Referencia completa de nginx: `nginx.conf.example` en la raíz del repo.

## 5. `.env` de producción

Edita `/www/wwwroot/crtrrhh.crtbcn.cat/.env`:

```ini
APP_NAME="CRT RRHH"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crtrrhh.crtbcn.cat

DB_DATABASE=crt_rrhh
DB_USERNAME=...        # los del paso 2
DB_PASSWORD=...

SANCTUM_STATEFUL_DOMAINS=crtrrhh.crtbcn.cat
SESSION_DOMAIN=
CORS_ALLOWED_ORIGINS=https://crtrrhh.crtbcn.cat

MAIL_MAILER=smtp       # buzón rrhh@crtbcn.cat
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=rrhh@crtbcn.cat
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=rrhh@crtbcn.cat
```

Las integraciones (Domi, geo, BD externas) se quedan **comentadas** hasta que existan.

## 6. Base de datos y primer administrador

```bash
php artisan migrate --force
php artisan db:seed --class=HolidaySeeder --force
php artisan db:seed --class=DisciplinaryFaultTypeSeeder --force
php artisan db:seed --class=ComplianceSeeder --force
php artisan crt:crea-admin tu.correo@crtbcn.cat "Nombre Apellido"
```

⚠️ **No ejecutes `php artisan db:seed` a secas** en producción: crea usuarios de prueba con la contraseña `123456`.
`crt:crea-admin` pide la contraseña por pantalla (mín. 12 caracteres, mayúscula, minúscula, número y símbolo).

## 6b. Importar los trabajadores del portal del empleado antiguo

Trae los trabajadores (con su contraseña actual), sus nóminas y sus fichajes desde el dump
del portal antiguo (`db_portalempleado_c_*.sql.gz`). Sube el `.sql.gz` al servidor y:

```bash
mysql -u root -p -e "CREATE DATABASE crt_portal_origen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
zcat db_portalempleado_c_*.sql.gz | mysql -u root -p crt_portal_origen
```

En el `.env` (si el usuario de la app no tiene acceso a esa BD, pon también `PORTAL_ORIGEN_DB_USERNAME`/`PASSWORD`):

```ini
PORTAL_ORIGEN_DB_DATABASE=crt_portal_origen
```

```bash
php artisan config:clear
php artisan crt:importa-portal            # ENSAYO: muestra qué haría, no escribe nada
php artisan crt:importa-portal --apply    # importa
```

- Entran como **trabajadores sin servicio**: asígnalo en *Empleats* (domiciliaria / ambulatoria).
- Se puede repetir con un dump más nuevo: no duplica nada y no modifica a quien ya exista.
- Las nóminas de personas que ya no están en el portal antiguo no se importan (el comando lo avisa).
- Al terminar, **borra la BD de origen y el dump** (contienen nóminas y datos personales):

```bash
mysql -u root -p -e "DROP DATABASE crt_portal_origen"
rm db_portalempleado_c_*.sql.gz
```

## 7. Permisos, enlaces y cachés

```bash
chown -R www:www storage bootstrap/cache
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## 8. Tareas programadas

aaPanel → **Cron → Add task** → *Shell script*, cada minuto:

```bash
cd /www/wwwroot/crtrrhh.crtbcn.cat && php artisan schedule:run >> /dev/null 2>&1
```

(recordatorios de jornada, purga de ubicaciones, sellado diario de integridad…)

## 9. Comprobación

- `https://crtrrhh.crtbcn.cat/api/v1/health` → 200
- `https://crtrrhh.crtbcn.cat/` → abre la web y permite entrar con el administrador del paso 6.

## Actualizaciones

A mano:

```bash
cd /www/wwwroot/crtrrhh.crtbcn.cat
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

O automático con el `Jenkinsfile` de la raíz (cambia `SSH_HOST`/`SSH_USER`; hace copia de la BD antes de migrar y health-check al final).

## Recompilar los fronts (en local, no en el servidor)

```bash
cd app && npm run build:web              # web  → public/app
npx vite build --config vite.config.laravel.js   # panel → public/build (desde la raíz)
```

y se hace commit de `public/app` y `public/build`.

## Pendiente antes de publicar

- NIF y domicilio social de CRT: buscar `PENDENT` en el código.
- EIPD de CRT (la de `docs/EIPD_INTEGRADA.md` es solo una base).
