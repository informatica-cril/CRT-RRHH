# CRT RRHH — instruccions per a assistents d'IA

Guia completa (desplegament, estructura, trampes i Jenkins): **`docs/GUIA_PER_A_IA.md`**.
Normes obligatòries del projecte: **`docs/DIRECTRICES_PROYECTO.md`**.

## Imprescindible
- **Desplegament = push a `branch-deploy`.** `main` → `git merge --no-ff` a `branch-deploy`; Jenkins fa backup, `migrate --force`, caches i health-check. Pujar a `main` no publica res.
- **La web va compilada al repositori.** Després de tocar `src/`: `npm run build:web` i pujar `public/app`. Si no, el servidor segueix servint la web anterior (no té Node).
- **Un sol domini** (`crtrrhh.crtbcn.cat`): web a `/app/`, API a `/api/`, panell a `/dashboard`.
- **BD i API només additives.** Migracions noves i reversibles; mai modificar-ne una d'executada.
- **Producció és MySQL 8** (aplica `CHECK`); els tests usen SQLite.
- **Res d'`env()` fora de `config/`** (amb `config:cache` torna `null`).
- **Hores de fitxatge en hora de Madrid sense zona**: el dia i l'hora es llegeixen del text.
- **Textos en català**; codis interns via `src/utils/etiquetes.js`.
- **Mai dades personals al repositori. Mai el `DatabaseSeeder` a producció** (crea un admin amb contrasenya `123456`).
- Abans de pujar: `npm run build:web` (si s'ha tocat `src/`) i `php artisan test` amb una `APP_KEY` de prova. `ExampleTest` falla sempre (la portada redirigeix).
