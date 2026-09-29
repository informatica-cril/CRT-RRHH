# BACKUP DE BASE DE DATOS — CRT_RRHH

> Backup de salvaguarda de todas las referencias y dependencias de la base de datos.
> Creado el 2026-07-01 en la rama `crt-rrhh-actualizaciones`.
> **Objetivo**: permitir despliegue a producción inmediato sin cambios ni ajustes en la BD.

---

## Estructura del backup

```
docs/DB_BACKUP/
├── config/
│   ├── env.example.bak          → copia de .env.example (configuración BD principal)
│   ├── database.php.bak         → copia de config/database.php (conexiones)
│   ├── app_env.bak              → copia de app/.env (frontend VITE_API_URL)
│   └── app_env_production.bak   → copia de app/.env.production
├── migrations/                  → copia de las 37 migraciones (esquema BD)
├── models/                      → copia de los 24 modelos Eloquent
├── seeders/                     → copia de los 3 seeders
└── routes/
    └── api.php.bak              → copia de routes/api.php (rutas API)
```

---

## Configuración de BD principal (`.env.example`)

| Parámetro | Valor |
|-----------|-------|
| `DB_CONNECTION` | mysql |
| `DB_HOST` | 127.0.0.1 |
| `DB_PORT` | 3306 |
| `DB_DATABASE` | portal_empleado |
| `DB_USERNAME` | crt_app |
| `DB_PASSWORD` | *(vacío en example)* |

### Conexiones adicionales (comentadas, solo lectura, integración futura)

| Conexión | Database |
|----------|----------|
| `DB_LOGO` | logopedia_new_db |
| `DB_AMBULATORIA` | crtbcn |
| `DB_DOMICILIARIA` | domi_crt |

---

## Tablas de la base de datos (esquema)

Inventario de tablas creadas por las migraciones:

| # | Tabla | Migración |
|---|-------|-----------|
| 1 | work_schedules | 2026_03_24_000001 |
| 2 | users | 2026_03_24_000002 |
| 3 | work_logs | 2026_03_24_000004 |
| 4 | absence_types | 2026_03_24_000005 |
| 5 | absences | 2026_03_24_000006 |
| 6 | authorization_codes | 2026_03_24_000007 |
| 7 | postal_code_assignments | 2026_03_24_000008 |
| 8 | excedencia_types | 2026_03_24_000009 |
| 9 | excedencias | 2026_03_24_000010 |
| 10 | documents | 2026_03_24_000011 |
| 11 | document_signatures | 2026_03_24_000012 |
| 12 | onboarding_profiles | 2026_03_24_000013 |
| 13 | onboarding_statuses | 2026_03_24_000014 |
| 14 | audit_logs | 2026_03_24_000015 |
| 15 | location_tracking | 2026_03_24_000016 |
| 16 | ambulatory_points (→ centers) | 2026_03_27_000001 |
| 17 | payrolls | 2026_03_27_000002 |
| 18 | chat_system (conversations+messages) | 2026_03_27_000003 |
| 19 | chat_settings | 2026_03_27_000004 |
| 20 | holidays | 2026_03_27_000005 |
| 21 | municipal_assignments | 2026_03_27_000006 |
| 22 | zones | 2026_03_27_000007 |
| 23 | work_locations | 2026_03_27_000008 |

### Migraciones de modificación (aditivas)

| Migración | Descripción |
|-----------|-------------|
| 2026_04_08_120110 | add_geolocation_fields_to_work_logs |
| 2026_04_14_000000 | add_fields_to_users_and_alerts |
| 2026_04_14_112839 | add_pdf_data_to_payrolls |
| 2026_04_14_122122 | update_municipal_assignments (multiple) |
| 2026_04_20_000000 | fix_zones_table_columns |
| 2026_04_20_000001 | fix_work_locations_columns |
| 2026_04_20_124557 | update_chat_conversations_type_enum |
| 2026_05_07_105016 | repurpose_ambulatory_points_to_centers |
| 2026_05_15_000000 | add_missing_indexes |
| 2026_05_19_000000 | add_geo_consent_to_users |
| 2026_06_01_000000 | add_dni_to_users |
| 2026_06_03_000000 | add_gps_gap_minutes_to_work_logs |
| 2026_06_16_000000 | add_auto_closed_to_work_logs |
| 2026_06_22_000000 | add_reminder_fields_to_work_logs |
| 2026_06_23_000000 | add_pre_reminder_to_work_logs |

---

## Modelos Eloquent (24)

`Absence`, `AbsenceType`, `AmbulatoryCenter`, `AuditLog`, `AuthorizationCode`, `ChatAlert`,
`ChatConversation`, `ChatMessage`, `ChatPolicyAcceptance`, `ChatSetting`, `Document`,
`DocumentSignature`, `Excedencia`, `ExcedenciaType`, `Holiday`, `LocationTracking`,
`OnboardingProfile`, `OnboardingStatus`, `Payroll`, `User`, `WorkLocation`, `WorkLog`,
`WorkSchedule`, `Zone`.

---

## Regla de oro

> **Cualquier cambio futuro en la BD debe ser ADITIVO y REVERSIBLE.**
> No se pueden modificar ni eliminar estructuras existentes. Consultar este backup antes
> de cualquier modificación para verificar que no se rompe nada.
