# DIRECTRICES DEL PROYECTO — CRT_RRHH

> Este documento es **obligatorio y vinculante** para todo el trabajo realizado en la rama
> `crt-rrhh-actualizaciones` (y en cualquier rama derivada). Estas directrices **no se pueden
> saltar nunca** y son el objetivo primario del proyecto.

---

## 1. 🛡️ SALVAGUARDA DE BASE DE DATOS (OBJETIVO PRIMARIO — INELUDIBLE)

**El objetivo es que la app se ponga en producción de forma inmediata, sin cambios ni ajustes
en la base de datos.**

Por ello, TODA referencia y dependencia de la base de datos debe ser **salvaguardada**:

- **NO se pueden modificar, renombrar ni eliminar** tablas, columnas, índices, relaciones,
  claves foráneas, ni ninguna estructura de la base de datos existente en producción.
- **NO se pueden alterar** las migraciones ya ejecutadas (las que tienen fecha anterior a la
  fecha de inicio de esta rama). Si se necesita un cambio de esquema, se creará una **migración
  nueva** que sea aditiva y reversible, sin tocar la existente.
- **NO se pueden cambiar** los nombres de conexión, credenciales, ni parámetros de configuración
  de la BD definidos en `.env`, `.env.example`, `.env.production` y `config/database.php`.
- **NO se pueden modificar** los modelos Eloquent en lo relativo a:
  - `$table` (nombre de tabla)
  - `$fillable` / `$guarded` (campos asignables) — solo se pueden **añadir** campos nuevos,
    nunca quitar los existentes.
  - `$casts`, `$dates`, `$hidden`, `$appends` — solo aditivo.
  - Relaciones (`hasMany`, `belongsTo`, etc.) — no eliminar relaciones existentes.
- **NO se pueden cambiar** los seeders existentes de forma que alteren datos en producción.
- **NO se pueden modificar** las rutas de la API (`routes/api.php`) que ya estén en producción,
  para no romper la compatibilidad con la app móvil (Capacitor) ya publicada. Las rutas nuevas
  deben ser **aditivas**.

### Inventario de BD salvaguardada

| Recurso | Ubicación | Estado |
|---------|-----------|--------|
| Configuración de conexión | `.env.example`, `app/.env`, `app/.env.production`, `config/database.php` | 🔒 Protegido |
| Migraciones (37 archivos) | `database/migrations/` | 🔒 Protegido (no modificar existentes) |
| Modelos Eloquent (24) | `app/Models/` | 🔒 Protegido (solo aditivo) |
| Seeders | `database/seeders/` | 🔒 Protegido |
| Rutas API | `routes/api.php` | 🔒 Protegido (solo aditivo) |

### Backup de referencias

Se ha creado un backup completo de la configuración y estructura de BD en:
[`docs/DB_BACKUP/`](DB_BACKUP/) — consultar antes de cualquier cambio.

---

## 2. ⚡ OPTIMIZACIÓN DE RENDIMIENTO (OBJETIVO PRIMARIO)

La app debe ir **lo más rápida posible** y el código debe ser **lo menos farragoso posible**.

- Priorizar consultas optimizadas: usar `select()` para traer solo columnas necesarias,
  `with()` para evitar N+1, índices (mediante migraciones nuevas aditivas).
- Cachear datos que no cambian frecuentemente (catálogos, festivos, etc.).
- Evitar lógica redundante o duplicada; refactorizar hacia código limpio y legible.
- En el frontend (Vue), usar lazy loading de componentes y rutas.
- Minimizar el tamaño de las respuestas JSON de la API.

---

## 3. 🌐 IDIOMA (DIRECTRIZ INELUDIBLE)

- **Frontend y Backend**: el texto de la app debe estar **siempre en catalán**, traducible al
  castellano. Usar el sistema de traducciones de Laravel (`lang/ca`, `lang/es`) y, en Vue,
  archivos de i18n con `ca` como idioma por defecto.
- **Diálogos con el usuario**: las respuestas se darán **siempre en castellano**.

---

## 4. 🚀 PRODUCCIÓN INMEDIATA

Cualquier cambio realizado debe ser **desplegable a producción de forma inmediata**, sin
necesidad de ajustes manuales, migraciones destructivas ni reconfiguración. Esto implica:

- Los cambios deben ser **compatibles hacia atrás**.
- No introducir dependencias que requieran configuración adicional en el servidor.
- Mantener `APP_ENV=production` y `APP_DEBUG=false` como valores por defecto.
- Cualquier nueva migración debe ser **segura en producción** (aditiva, reversible, sin downtime).

---

*Documento creado el 2026-07-01 para la rama `crt-rrhh-actualizaciones`.*
