# Parte — Max-2 · v15 Paso 1 · re-refresh de feature/hoja-de-ruta

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **Paso 1 del dictado v15** — re-refresh contra el main con M13 + simulador
ESTADO: **HECHO** — la rama espera la doble llave

## EVIDENCIA

- Merge de `origin/main` (**25 commits**: M13 lote 1, simulador de carga, QA M13 aprobado)
  → commit `0f0a83a`, pusheado. Rama **0 detrás / 7 adelante**.
- **Suite COMPLETA: 1480 verdes / 11.003 aserciones, cero rojos** (main 1425 + mis 37 +
  lo que sumó el simulador). La cifra para el dueño.

## Qué chocó y cómo se resolvió

- **La unión declarada donde la anunciaste**: seeder + `RoleMatrixSeedTest` con AMBAS
  adiciones por jefatura (M13: `manage devoluciones`/`simular carga`; P-DSP-08: las 3
  llaves); `jefe_despacho` intacto; count sigue 12. El candado verde = la unión quedó bien.
- **Colisión fina que el merge no veía**: mi grupo Despachos usaba el keyword `'carga'` y
  main puso `'carga'` en Logística para `simular carga` — Despachos se evalúa PRIMERO y se
  habría comido el permiso del simulador en la pantalla de Roles (mal grupo, en silencio).
  Fix: Despachos usa el keyword ESPECÍFICO `'autorizar carga'`; Logística conserva `'carga'`.
- `MenuPrincipal` **auto-mergeó la unión solo** (devoluciones en Operación; simulador y
  hojas-ruta conviven en Logística); `routes/web.php` unión de ambos bloques; manifest con
  checkout de main + rebuild — **superset 0 perdidas de 668**, sin BOM, ganchos del
  conductor presentes, chunk `carga3d` del simulador intacto.

## 🔎 Hallazgo para ti (territorio de Marcos, no lo toqué)

Main trae **`public/build/manifest 3.json`** — basura de copia de Windows commiteada con el
simulador (`a7c4e9b`). Es inerte (Laravel lee `manifest.json` exacto), pero es un archivo
muerto en producción y el patrón «archivo 3» sugiere un working tree con copias. Limpiarlo
es 1 línea del lado de quien lo trajo.

## SIGUIENTE

**Doble llave de `feature/hoja-de-ruta`** (P-DSP-08 + este refresh). Tras el merge:
**P-DSP-09** (PWA sobre la hoja: dirección+comuna+teléfono por parada, receptor obligatorio,
cobro en entrega, rechazo con motivo) en rama nueva desde main fresco, como ordena v15.
