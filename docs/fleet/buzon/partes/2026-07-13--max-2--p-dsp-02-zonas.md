# Parte — Max-2 · P-DSP-02 hecho en rama: catálogo de zonas + override del cliente · 2026-07-13

CUENTA: Max-2 · Forjador B (stream 2) · Opus 4.8 · high
TAREA: P-DSP-02 (DESPACHOS-v1) — catálogo `zonas` + `users.zona_id` + regla de zona efectiva del cliente. Adelantado porque NO depende de Bsale (P-DSP-01 espera el shape de P-DSP-00, delegado al server).
ESTADO: HECHA en la rama `feature/despachos-v1`. Suite 536 verde. NO en main (se marca `[x]` al merge, P-DSP-07).

QUÉ HICE:
- 3 migraciones aditivas: `create_zonas_table`, `add_zona_id_to_users`, `add_zona_id_to_clientes` (todas nullable, `nullOnDelete`, patrón `add_sucursal_id_to_users`; VARCHAR 191 por `defaultStringLength`).
- Modelo `Zona` (auditable, `vendedores()` HasMany) + `ZonaFactory`.
- `ZonaSeeder` idempotente (Santiago Norte/Sur, 6ª, 7ª — info de Héctor D-006) + registrado en `DatabaseSeeder`.
- `User::zona()` + `Cliente::zona()` + **`Cliente::zonaEfectiva()`** con la regla de precedencia.
- `Zona` en `AuditController::MODELOS`.

**AJUSTE OBLIGATORIO DEL DUEÑO aplicado** ("siempre hay excepciones"): `clientes.zona_id` explícito GANA sobre la zona heredada del vendedor. Regla de precedencia: (1) zona explícita del cliente → (2) zona del vendedor → (3) null. Documentada en el modelo y en `PLAN-DESPACHOS-V1 §1.2/§4`.

VERIFICACIÓN:
- `tests/Feature/Despachos/ZonaTest.php` — 6 tests (explícita gana, herencia, dos caminos a null, seeder idempotente ×2, auditable). 6/6 verde.
- `migrate:fresh --seed` limpio (ZonaSeeder DONE). Suite completa **536 verde** (+6).
- Sin Blade/JS/CSS → sin build. Cero colisión (tablas/columnas nuevas; AuditController y DatabaseSeeder aditivos).

TESTS: 536 verde.
/usage: ← Mauricio completa. Sesión en Opus 4.8 ✓.

SIGUIENTE: sigue bloqueado P-DSP-01 (espejo de documentos) hasta que vuelva el shape de P-DSP-00 (delegación al server, parte anterior). Con el shape → P-DSP-01. Luego P-DSP-03 (entidad Despacho, ya no depende de Bsale — podría adelantarse también si el shape tarda).
