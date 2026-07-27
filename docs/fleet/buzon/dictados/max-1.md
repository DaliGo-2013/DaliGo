# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-27 (v26 — NOTIF-1 EN PRODUCCIÓN; standby corto). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ NOTIF-1 EN PRODUCCIÓN (merge `601ec7a`, doble llave, Deploy success)
La directiva del dueño del 22-07 —«notificaciones más específicas, cosas tangibles que podamos
mirar siempre»— está viva: campanita con cuerpo y navegable, ancla puntual por solicitud,
payload con objeto y cambio anterior→nuevo, fallback nunca-mudo, plantillas ricas entregadas
por la one-shot. QA del Director en staging: `/notificaciones`, `/aprobaciones`,
`/aprobaciones/mias`, `/admin/audits` → 302 y bundle `DcH-lDk3` sirviendo 200.

Ejecución impecable del rescate: los 5 conflictos predichos fueron 5, las indentaciones
calzaron, y **corregiste el defecto real** (el `ir=1` por `urlDestinoPara`, que evitaba mandar
al usuario a un 403 con la notificación ya marcada leída). Verificación del Director sobre el
árbol mergeado: gates 3/3 (ancla 1+1=2, build sin mover un byte, seeder bajo 191), cero BOM en
los 12 archivos, **suite 989 verdes / 5.394 aserciones**.

Tu rebuild salió **byte-idéntico** al de main: lo que main tenía por el accidente del
`@source` de `storage/framework/views` ahora lo produce el `.blade` por derecho propio. Eso
cierra limpio el matiz que yo había dejado abierto en el gate de CSS.

## 🏆 Tu hallazgo del BOM corrigió MI anexo
`git show origin/main:<archivo> > <archivo>` —la forma que yo recomendé— inyecta **BOM + CRLF**
en PS 5.1, y en `manifest.json` eso revienta el `json_decode` de Vite: **la app queda sin
CSS/JS en runtime**, con `git status` limpio porque solo delata el CRLF. Lo cazaste con
`file -b` antes de commitear. Ya corregí el anexo (`docs/fleet/buzon/anexo-reaplicacion-notif1.md`)
para que nadie lo siga: la forma correcta es `git checkout origin/main -- <archivo>`.

Es la segunda vez esta semana que un forjador corrige al Director con evidencia. Así funciona
el sistema; sigue haciéndolo.

## 🟡 STANDBY corto
No abras rama nueva todavía. Pendiente de decisión del dueño:
- **#6 chips paramétricos** (motivo del ajuste configurable) — el Director lo está
  dimensionando; si entra, es tuyo.
- **Sublote C de notificaciones** (payload de cotización/terreno: falta la descripción libre
  del cliente, el servicio del catálogo, quién rechazó) — es territorio de Marcos y va por
  canal directo del dueño, salvo que él decida pasártelo.
- **Incoherencia anotada, sin fix:** la campanita aterriza en la tarjeta puntual pero el botón
  del correo apunta a la lista pelada (`payload['url']` sin ancla, `Aprobaciones.php:303`).
  Igualarlo obliga a tocar los `assertSame` de `payload['url']` en `AprobacionAccionableTest`.
  Decisión del dueño si entra.

Si al abrir sesión el buzón sigue en v26, cierra `feature/notif-especificas-v2` del remoto
(ya está mergeada) y quédate disponible.

## No es tuyo
- P-DSP-04 QR anti-fraude: Max-2, con GO en firme.
- P-TZ-03 QA de borde, decisión del ciclo de la factura: dueño.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
