# SPIKE-PWA — memo de arquitectura offline (cierre de P-SPK-01/02/03)
> **Estado: VIGENTE — verificado contra el código el 2026-07-07 (el push que introduce este memo)**

> **Qué es esto:** el entregable del spike PWA de la unidad E2 — el mayor riesgo técnico declarado
> del proyecto (biblia §6: ruta Atacama sin señal), atacado en W12 en vez de W27. Este memo
> **gobierna el diseño offline de M08** (E8, PWA del conductor) y de cualquier PWA futura del
> proyecto. Quien construya E8 parte de aquí, no de cero.
> Commits del spike: P-SPK-01 `ee01204` · P-SPK-02 `793bfcc` · P-SPK-03 este push.
> Regla de re-sellado: `docs/planes/README.md` §3 (>7 días o commits que toquen estas áreas → re-verificar).

---

## 1. Qué se construyó (mapa de piezas)

| Pieza | Archivo | Qué hace |
|---|---|---|
| Manifest | `public/manifest.json` | Instalable: `scope:"/"` + `id:"/"`, `start_url:/produccion/mi-reporte`, standalone, iconos any+maskable |
| Iconos | `public/icons/*` (5) | 192/512 any + maskable (safe zone 80%) + `apple-touch-icon` 180 (iOS ignora los del manifest) |
| Service worker | `public/sw.js` | Cache de assets + fallback offline, estrategia conservadora (§2.1). Vanilla, sin build |
| Página offline | ruta `GET /offline` + `resources/views/offline.blade.php` | Fallback de navegación sin red; standalone (cero assets externos) |
| Estado de red | `resources/js/app.js` → `Alpine.store('red')` | `navigator.onLine` + confirmación `HEAD /up` al volver online |
| Registro del SW | `resources/js/app.js` | `updateViaCache:'none'` + guard de localhost (opt-in `localStorage.daligoSW='1'`) |
| Cola offline | `resources/js/offline-queue.js` | IndexedDB `daligo/tandas`: encolar/drenar/pendientes, clasificación de errores, guard de reentrada |
| Idempotencia | migración `2026_07_02_120000` + `MiProduccionController::registroStore` | `cliente_uuid` char(36) nullable + **unique `[reporte_id, cliente_uuid]`** + check bajo `lockForUpdate` |
| UI operario | `<x-produccion.indicador-red>` + contador en `mi-reporte.blade.php` | Pill "Sin conexión" + "N guardada(s) sin conexión" |

Tests: `PwaTest` (4, contrato de manifest/SW/offline) + 4 de idempotencia en `ProduccionTest`.

## 2. Decisiones de arquitectura (y por qué)

### 2.1 Service worker CONSERVADOR — las 5 reglas
Detalle completo en la bitácora de CLAUDE.md (entrada 2026-07-02 P-SPK-01). Resumen normativo:
1. **Fallback offline SOLO en el `catch` del fetch** — jamás por `response.ok` (el 302 del
   middleware `auth` llega como `opaqueredirect` status 0: un fallback por status mostraría
   "Sin conexión" en cada login/logout).
2. **`scope:"/"` + `id:"/"` explícitos** en el manifest (el default sería el directorio del
   start_url y el login quedaría "fuera de la app" instalada).
3. **Passthrough sin `respondWith`** para non-GET/cross-origin/`/up`; solo dos intercepciones:
   `/build/*` cache-first (hashes inmutables, cache aparte con poda) y navegaciones network-first.
   **JAMÁS se cachea HTML autenticado.**
4. **Tocar `offline.blade.php` exige bump de `CACHE` en `sw.js`** (el SW se actualiza por
   byte-diff de sw.js; comentarios espejo en ambos archivos). **Con guardarraíl automático:**
   `PwaTest::test_tocar_offline_blade_exige_bump_de_cache` acopla el hash del Blade a la versión
   de CACHE — editar uno sin el otro pone la suite en rojo.
5. **Registro con guard de hostname** (localhost solo con opt-in) y `updateViaCache:'none'`.

### 2.2 Cola offline — idempotencia en tres capas
UUID generado en el cliente (`crypto.randomUUID`) viaja con cada tanda encolada →
(1) check `WHERE cliente_uuid` **dentro del `lockForUpdate`** del reporte; (2) **unique compuesto
`[reporte_id, cliente_uuid]`** como red final (MySQL 5.7 y SQLite permiten múltiples NULL: el
camino nativo sin uuid no choca); (3) respuesta de éxito ante uuid repetido (el drenado borra el
item aunque la tanda ya existiera — un reintento nunca duplica ni se atasca).

### 2.3 CSRF fresco, nunca serializado
La cola **no guarda `_token`** (stale tras un rato offline). El drenado lee el token del
`<meta name="csrf-token">` de la página viva y lo manda en header `X-CSRF-TOKEN`. No se exceptúa
CSRF: el endpoint muta inventario tras auth+permission+ownership.

### 2.4 Clasificación de errores del drenado
`2xx` → borrar de la cola · `422/403` (validación/reporte ya no editable) → **permanente**: item
marcado `error:true`, sin reintento, sin borrado silencioso · `419/5xx/fallo de red` →
**transitorio**: reintenta en el próximo `online`/`load` con tope `MAX_INTENTOS=5`.
El endpoint lanza las reglas de negocio como `ValidationException` (no `back()->withErrors`) para
que el fetch reciba 422 real y la web el redirect de siempre — y responde JSON con `expectsJson()`.

### 2.5 Drenado en `online` + `load` (LA decisión iOS)
**iOS no tiene Background Sync** → nada de `sync` events. La cola se vacía con la página en
primer plano: al volver el evento `online` y al cargar la página. Guard de reentrada (`drenando`)
porque ambos pueden dispararse juntos.

### 2.6 Reconciliación: el servidor es la fuente de verdad
Offline, la UI solo acumula **contadores** optimistas (jamás inserta filas en la lista renderizada
server-side). Tras drenar con éxito la cola completa → `location.reload()` y la página vuelve a
nacer del servidor. Si quedan items rechazados/transitorios NO se recarga (se perdería el aviso).

## 3. Qué NO hacer (la lista de minas, cada una con su porqué)

| # | NO hacer | Por qué |
|---|---|---|
| 1 | Precachear/cachear HTML autenticado | Sesión/CSRF stale, datos de otro usuario, TypeError de Chrome con respuestas redirected |
| 2 | Fallback offline por `response.ok`/status | `opaqueredirect` (302 de auth) = status 0 → "Sin conexión" en cada login |
| 3 | `respondWith(fetch(req))` como passthrough | Rompe redirects/streaming/range; lo no manejado se deja pasar con `return` |
| 4 | Workbox / precache-manifest acoplado a Vite | El server no tiene Node y `public/build` va commiteado: cada acople build↔SW es un punto de fallo del deploy |
| 5 | Runtime-cache de cross-origin (fuentes bunny) | Respuestas opaque se inflan ~7MB c/u en la cuota; offline cae a fuente de sistema y ya |
| 6 | Exceptuar CSRF del endpoint de la cola | Hueco permanente para ahorrar un caso de borde; token fresco del `<meta>` lo resuelve |
| 7 | Serializar `_token` en IndexedDB | Stale tras rato offline → 419 evitables |
| 8 | Background Sync API | No existe en iOS; `online`+`load` cubre el caso real con página en primer plano |
| 9 | Tocar `offline.blade.php` sin bump de `CACHE` | El Blade queda congelado en el precache para siempre (update del SW = byte-diff de sw.js) |
| 10 | Confiar la idempotencia solo al check aplicativo | El unique en BD es la red final ante carreras; check bajo lock + constraint, siempre ambos |
| 11 | Registrar el SW en localhost sin opt-in | El SW persiste POR ORIGEN y contamina otros proyectos servidos en el mismo puerto de dev |

## 4. Patrón reusable para M08 · PWA del conductor (checklist de adopción para E8)

### 4.1 Se hereda TAL CUAL (no rediseñar)
- `public/sw.js` (mismo archivo y estrategia; a lo más bump de versión).
- El módulo `offline-queue.js` → **generalizar**: hoy el object store es `tandas`; extraer a
  stores por tipo de item (`tandas`, `entregas`) manteniendo encolar/drenar/clasificación.
- El patrón de endpoint idempotente: `cliente_uuid` + unique `[agregado_id, cliente_uuid]` +
  check bajo `lockForUpdate` + `ValidationException` + rama `expectsJson()`.
- `Alpine.store('red')`, `<x-produccion.indicador-red>` (mover a componente genérico si se
  comparte entre roles) y el contador de pendientes.
- Manifest: **decidir el `start_url`** — hoy apunta a mi-reporte (soplador); con dos roles PWA
  conviene `start_url` neutro (`/`, redirige por rol tras login) o manifests por ruta. Registrarlo
  como decisión de diseño de E8, no improvisar.

### 4.2 Lo NUEVO que M08 debe resolver (no cubierto por el spike)
1. **Firma + foto = Blobs en IndexedDB** (structured clone los soporta nativo). Obligatorio:
   comprimir/redimensionar la foto ANTES de encolar (canvas → JPEG ~1280px) y tope de tamaño por
   item; la cuota en celulares de gama baja es real. Drenar con `FormData` (multiparte), no JSON.
2. **Hora exacta offline:** capturar `capturado_at` del dispositivo AL CREAR la confirmación (no
   al drenar) y mandarlo como campo; el server guarda ambos (hora capturada + hora recibida).
3. **Ruta del día visible offline:** problema de LECTURA, distinto a la cola de escritura —
   precargar los datos (JSON) al abrir con señal y renderizar desde IndexedDB/estado; **no**
   resolverlo cacheando HTML (regla §3.1).
4. **Catálogo offline para venta-en-ruta (Atacama):** ídem lectura; store de catálogo con
   timestamp de frescura y aviso de datos viejos.
5. **UI de items rechazados:** el spike marca `error:true` y deja de contarlos; M08 necesita
   pantalla de "no se pudo enviar" con re-intento/descarte manual (una entrega rechazada es plata:
   no puede quedar solo en IndexedDB).
6. **Multi-dispositivo/cierre de ruta:** definir qué pasa si la cola queda con items al terminar
   el turno (recordatorio al abrir; el cierre de ruta debe exigir cola vacía).

### 4.3 Lo que el spike des-riesga para el Gantt
El patrón completo (SW + cola + idempotencia + reconciliación) quedó **probado en producción**
con el flujo más crítico del negocio actual (tandas del soplador). E8 ya no arranca un riesgo
técnico: arranca una adaptación de patrón con 6 puntos nuevos conocidos (§4.2).

## 5. Decisión iOS (registrada)

Target real: **Android** (celulares de los operarios — funciona completo: banner de instalación,
standalone, drenado). En iOS el degradado se ACEPTA: instalación manual (Compartir → Agregar a
pantalla de inicio), sin banner, cookies no compartidas con Safari (re-login único dentro de la
app), cache del SW evictable tras semanas sin uso (por eso `paginaOffline()` tiene una `Response`
sintética de respaldo), sin Background Sync (§2.5). **Consecuencia para M08:** los conductores
deben operar con Android; si algún rol exige iOS, se asume el degradado documentado aquí — no se
re-arquitectura.

## 6. Prueba de campo (P-SPK-03) — resultados

> Guion: celular real contra staging. Escenario A (app viva): modo avión → 2 tandas (contador
> "2 sin conexión") → volver señal → recarga sola → tandas visibles. Escenario B (persistencia):
> modo avión → 2 tandas más → MATAR la app desde multitarea → volver señal → abrir la app →
> drena al cargar → 2 nuevas, cero duplicados. Verificación: conteo de tandas en el reporte del
> jefe (+ el caso "matar durante el drenado mismo" está cubierto por el test de idempotencia:
> reintento del mismo uuid no duplica).

> **Ejecutada por el dueño el 2026-07-06** (celular real contra staging); capturas verificadas
> por el Director (tablero de flota día 3, commit `50f8878`).

- **Escenario A: OK** — 2 tandas en modo avión con contador "2 sin conexión" → al volver la
  señal sincronizaron solas (recarga y tandas visibles).
- **Escenario B: OK** — app MATADA desde multitarea con cola pendiente → al reabrir con señal,
  la cola drenó al cargar.
- **Duplicados en tabla: 0** — 4/4 tandas únicas en el reporte del jefe; el motivo por tanda
  ("2ª: Rebaba") sobrevivió el viaje por la cola offline.

## 7. Límites conocidos del spike (honestos, deliberados)

- Offline cubre SOLO la tanda (`registroStore`). **Enviar el reporte** (`update`) exige señal —
  decisión: el envío es el acto de cierre consciente del turno, no debe ocurrir a ciegas.
- La primera carga de la página exige señal (no hay app-shell de HTML cacheado — regla §3.1).
  El caso real (soplador ya dentro de la pantalla cuando se cae la señal) queda cubierto.
- `navigator.onLine` tiene falsos positivos (WiFi sin internet); se mitiga con `HEAD /up` al
  volver online. Tolerable: el indicador informa, no bloquea.
- Los items permanentes (422/403) quedan marcados en IndexedDB sin UI de gestión (§4.2.5).
