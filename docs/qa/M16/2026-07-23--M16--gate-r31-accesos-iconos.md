# Gate R-31 — `feature/m16-accesos-iconos` → `main` (E10-v1.2, P-M16V1-05)

- **Fecha:** 2026-07-23 · **Auditor:** panel adversarial de 5 lentes independientes + adjudicación contra código (los verificadores del panel cayeron por límite de sesión; los 9 hallazgos crudos se adjudicaron uno a uno contra el código real, doctrina «verificar cada hallazgo antes de corregir»)
- **Alcance del diff:** zócalo del Inicio → cards con squircle de ícono + color personalizable por usuario (D-013). 30 archivos, ~570 líneas.
- **Veredicto:** **APROBADO CON OBSERVACIONES (corregidas en `326d87f` antes del merge)** → merge `6d8ae18`.

## Tabla de gates

| Gate | Veredicto | Evidencia |
|---|---|---|
| Tests verdes | OK (obs.) | Corridas completas: 772/772 (r3), 774/774 (r4 post-fixes), **790/790 final** (r5, con los 16 tests que trajo main en vuelo). Rojos de r1/r2 = flaky PREEXISTENTE `IngresoTallerPublicoTest::envio_publico_guarda_producto_id`: archivo idéntico a main, 12/12 verde aislado, 2/1/0 rojos entre corridas idénticas — fuera del diff, derivado a tarea aparte (territorio Marcos) |
| Build recompilado | OK | Regenerado en CADA merge de main (`d3f4441`, `c4877bd` — los hash cambiaron: main traía clases nuevas). Grep del bundle final `app-BD6EfR5L.css`: `lg:flex`/`lg:hidden` + 6 familias pastel `bg-*-100` ✓ |
| MySQL 5.7 | OK | TEXT nullable sin DEFAULT; cero dropIndex/FK descubierta; cero `whereBetween` sobre fechas casteadas; cero índices >191; cero funciones JSON en queries; lectura defensiva de NULL/legacy (cae al default, test dedicado) |
| Locks | OK (n/a) | Sin check-then-act; columna propia, last-write-wins inofensivo; ahora además MERGE server-side |
| Permisos por ruta | OK | Grupo `auth` (preferencia personal, patrón `/perfil/notificaciones`); CSRF activo + token fresco del `<meta>`; render filtra cards por `can()`; sin mass-assignment (fuera de `$fillable`); sin XSS vía `@js` (payloads server-controlled con whitelist estricta) |
| x-componentes | OK (obs.) | Card extraída a `<x-dashboard.acceso>` y catalogada; «Personalizar» reusa idioma text-link de «Ver panel». Obs. aceptada: «Listo» = primario en miniatura inline (el componente no admite override de tamaño; caso único anotado — si el patrón se repite, extraer variante) |
| Responsive 375/768/1024 | OK | Preview: 2/3/4 columnas, sin scroll horizontal, swatches 44px, nav desktop `lg:flex` vivo |
| Bitácora (reincidencia) | OK | 0 reincidencias; candidato «scope del selector» ([2026-06-30]) rechazado con razón: protege datos del kardex, no una preferencia propia jamás renderizada sin permiso (y el filtro por scope rompería el merge de prefs) |

## Hallazgos adjudicados (9 crudos)

| # | Hallazgo | Adjudicación |
|---|---|---|
| 1 | [media] Sin anillo de foco de marca en Personalizar/Listo/swatches | REAL → corregido (`326d87f`): idioma exacto de `x-primary-button` |
| 2 | [baja] `aria-expanded` ausente + foco perdido al ocultarse «Personalizar» | REAL → corregido: `:aria-expanded` en modo edición + foco a «Listo» vía `$nextTick` |
| 3 | [media] Semántica reemplaza-todo del PATCH sin test ni doc + pérdida silenciosa de prefs invisibles | REAL → corregido: endpoint pasa a **MERGE** (`array_merge`), test `test_patch_parcial_hace_merge_y_conserva_lo_ya_guardado` |
| 4 | [baja] Payloads `colores=[]` / no-array sin cobertura | REAL (las reglas ya los rechazaban; faltaba candado) → test `test_rechaza_colores_vacio_y_no_array` |
| 5 | [baja] Candado anti-drift solo PHP→Blade | REAL → candado inverso: cada card renderiza exactamente `count(COLORES)` swatches |
| 6 | [baja] «Listo» primario inline | REAL, aceptado como caso único (anotado arriba) |
| 7 | Flaky `IngresoTallerPublicoTest` | PREEXISTENTE, fuera del diff → tarea aparte, canal directo a Marcos |
| 8 | Endpoint acepta colores de cards fuera del permiso del usuario | FALSA ALARMA (rechazado): nunca se renderiza sin permiso, cero fuga, storage acotado, y el filtro por scope rompería el merge de prefs ante permisos que van y vienen |
| 9 | Cobertura del contrato del PATCH (gate FALLO del lente tests) | Absorbido por #3/#4 → cerrado |

## Cierre

Merges de main en vuelo absorbidos dos veces (`1aaa6ee`+`d3f4441`, `c4877bd`) — `app.js`/`routes/web.php` auto-mergeados conservando ambos aportes; `public/build` regenerado, jamás a mano. Suite final **790 verdes (2574 aserciones)**. Merge a main `6d8ae18` por orden del dueño (doble llave: orden explícita de Mauricio en sesión).
