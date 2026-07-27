# Parte — Max-2 · P-DSP-03 HECHO: entidad Despacho + escaneos + panel admin · 2026-07-14

CUENTA: Max-2 · Forjador B (stream 2) · Fable 5 (decisión del dueño hasta 19-07)
TAREA: dictado v6 — GO P-DSP-03 con los 6 recordatorios.
ESTADO: HECHO en rama `feature/despachos-v1` (refrescada con main antes de compartidos). Suite **630 verde**. Responsive verificado en preview a 375/768/1280 con datos reales.

QUÉ HICE (los 6 recordatorios del dictado, en orden):
1. **Re-verificación Bsale obligatoria** en `DespachoService::crearDesdeDocumento` (documents/{id}.json ANTES de crear) — y la endurecí a **FAIL-CLOSED total** por hallazgo del review: DTE anulado, Bsale caído **o un 200 sin `cancellationStatus` legible** → NO se crea (indeterminado ≠ vigente). El re-check refresca de paso el espejo (incl. `cancellation_at`). Tests de los 3 caminos.
2. `$table = 'despachos'` fijado + código `DSP-XXXXXXXX` impredecible (patrón `generarCodigoUnico` de OrdenServicio, con reintento).
3. `escaneos_despacho` **append-only** (sin `updated_at`; constantes `valido/doble_retiro/estado_invalido`). `validarRetiro` con lockForUpdate llega en P-DSP-04 (su tabla y modelo ya están).
4. Superficie compartida tocada SOLO con bloques al final (routes grupo nuevo, nav Operación→Despachos desktop+mobile, seeder, permissions, AuditController) sobre main fresco.
5. Permisos `manage despachos` (jefe_bodega) y `confirmar entrega` (conductor): aditivos en los 3 puntos + RoleMatrixSeedTest actualizado.
6. Panel responsive verificado EN PREVIEW con datos seed reales: index (badges de estado, filtro, filas con folio/cliente/zona) y create (select de docs espejados sin despacho, zona con "Del cliente (automática)", conductor, transportista) — 375/768/1280 **sin scroll horizontal**, hamburguesa a 375, "Despachos" en nav desktop y móvil. `npm run build` + grep del bundle: `lg\:flex`, `lg\:hidden` y las 6 de M16 **8/8 PRESENTES** (ojo: el grep de `min-w-\[1.5rem\]` da falso negativo por el escape CSS `\[1\.5rem\]` — grepear `min-w-` crudo).

REVIEW ADVERSARIAL (workflow 4 dimensiones × 15 refutadores, 19 agentes): 15/15 confirmados, **todos resueltos antes del commit**. Los estructurales:
- **[alto] Carrera doble-submit**: el check-then-act tenía la HTTP a Bsale (hasta ~30s) entre el exists() y el create → dos QR válidos del mismo documento (rompe el núcleo anti-fraude, indetectable como doble_retiro). Fix: **unique en BD sobre `documento_venta_id`** (la regla es estructural) + transacción corta con `lockForUpdate` + re-check (la HTTP queda FUERA del lock) + catch de unique con mensaje amable. El verificador confirmó que SQLite no exhibía la carrera — por eso el unique.
- **[medio] Fail-open**: un 200 con body vacío pasaba como "vigente" (el verificador lo REPRODUJO: espejo local ya anulado + Bsale {} → despacho creado igual). Fix: `cancellationStatus === 0` explícito; null → rechazo.
- **[medio] `conductor_id` sin scope**: validaba contra cualquier user (violaba la regla de bitácora 2026-06-30 M-3). Fix: closure que exige rol conductor + test.
- 6 huecos de cobertura cerrados (zona explícita del form gana + zona inactiva, transportista/conductor persistidos, assertSent del doc correcto, filtro estado + estado inválido, 403 en create/store, doc inexistente). DespachoTest quedó en 15 tests.

TESTS: 630 verde. /usage: ← Mauricio completa.

SIGUIENTE: **P-DSP-04 (QR anti-fraude M07)** — QR firmado por despacho, escaneo en bodega con `validarRetiro` (lock + doble-retiro + fila de escaneo), cola "McDonald's". Reuso el patrón QR de M12 (chunk `qrcode` ya en el bundle). Arranco al próximo ciclo salvo dictado en contra.
