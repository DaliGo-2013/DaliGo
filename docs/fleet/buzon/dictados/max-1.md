# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-28 (v29 — fin del standby: cerrar NOTIF-1 del todo + higiene del candado one-shot). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Tus 2 lotes + sus 2 fixes de gate: TODOS EN PRODUCCIÓN
`80ac3db` (P-NAV-06 + #6 chips) y `e9a8224` (los fixes de tu gate propio). Deploy success,
**suite 1025 verdes**, ramas `fix/nav-huerfanas` y `feature/chips-motivo-ajuste` **ya borradas
del remoto** por el Director (verificada ancestría completa antes).

**Y algo que quiero que quede dicho:** cuando el dueño me pidió borrar tus ramas «ya
mergeadas», el chequeo de ancestría dijo NO mergeadas — porque tú las habías refrescado con los
dos fixes de tu gate. Si hubiera confiado en mi propio merge sin verificar, habría borrado el
arreglo de un defecto **vivo en producción** (el ajuste rechazado que volvía con el panel
cerrado y el error en `display:none`). Tu costumbre de correr un gate propio después de
entregar salvó eso. Sigue haciéndolo.

## 🟢 TAREA 1 — Cerrar NOTIF-1 del todo: la incoherencia campanita ↔ correo (S)
Rama `fix/notif-url-ancla` desde main fresco. Es el último cabo suelto de tu propio lote.

**El síntoma:** la campanita aterriza en la tarjeta puntual (`#aprobacion-{id}`, tu ancla),
pero el **botón del correo apunta a la lista pelada** — porque `payload['url']`
(`Aprobaciones.php:303`, y el gemelo de `notificarRol`) sigue siendo `route('aprobaciones.index')`
/ `route('aprobaciones.mias')` sin ancla. El usuario que llega por correo tiene que buscar su
solicitud a mano en una página que ahora está agrupada por categorías, o sea más larga.

- Reusa la MISMA lógica del ancla que ya vive en `urlDestino()` — no la dupliques: extrae el
  cálculo a un punto único si hace falta (el `notificable_id` es la Aprobación en ambos
  caminos, lo verifiqué al integrar tu lote).
- **Aviso que ya te di y sigue vigente**: esto toca los `assertSame` de `payload['url']` en
  `AprobacionAccionableTest` (mergeado ~185 y ~198). Actualízalos, no los silencies.
- Verifica el correo de verdad, no solo el payload: el botón del Blade se arma desde
  `payload.url`, así que el ancla tiene que sobrevivir hasta el `<a href>`.

## 🟢 TAREA 2 — Candado para las migraciones one-shot de plantillas (S)
Deuda que anoté y no llegué a dictar. Rama aparte `test/candado-one-shot` o dentro de la
Tarea 1 si te queda corto — tu llamada.

**El problema:** el patrón de entrega que tú mismo inventaste (one-shot que actualiza SOLO si
el valor vigente es exactamente el texto del seed anterior) es correcto, pero **frágil por
diseño**: si alguien edita esos textos en `ConfiguracionSeeder` sin actualizar el `$viejo` de
la migración, la migración se vuelve un **no-op silencioso** — no falla, no avisa, simplemente
no entrega nada a producción. Aplica a tu one-shot de aprobaciones **y a la de Marcos**
(`2026_07_22_180000`).

Un test que, para cada clave que ambas one-shot tocan, exija que el **`$nuevo` de la migración
sea idéntico al valor que siembra el seeder hoy**. Si alguien cambia uno sin el otro, rojo.

## Lo que NO es tuyo
- P-DSP-04/05: Max-2 (le queda 1 candado de padding y arranca la PWA del conductor).
- **P-NAV-05** (gate R-31 formal + QA en celular) y el **bundle de diseño**: del dueño.
- Sublote C de notificaciones (cotización/terreno): territorio de Marcos.
- Decisión del ciclo de la factura: del dueño. Dato para tu contexto: entró trabajo de **M05
  DTE** (puerto emisor + documentos emitidos) por fuera de la flota — el ciclo se está moviendo.

## Recordatorios
Suite COMPLETA (main hoy ~1138 tests). **Main endureció un candado nuevo esta semana:
`MarcoHorizontalTest` exige padding mobile-first (`p-4 sm:p-6`, no `p-6` pelado)** — si tu lote
toca tarjetas, nace cumpliéndolo. Conflictos con `git checkout origin/main -- <archivo>`,
nunca con `>`. Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
