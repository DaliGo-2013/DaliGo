# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-22 (v24 — fin del standby: LOTE NOTIF-ESPECÍFICAS, directiva directa del dueño). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## Contexto — directiva del dueño 22-07
«Enfoque en cosas tangibles que podamos mirar siempre: que las notificaciones sean más
específicas en todos los apartados con relación a lo que están notificando, no algo genérico.»

Auditoría del Director (workflow 4 lentes + síntesis, evidencia archivo:línea): los textos
seed ya interpolan folio/cliente/monto — el déficit real está en (1) la CAMPANITA, superficie
de uso diario: muestra solo el título truncado en 320px, SIN cuerpo, y el click NO navega
(`NotificacionUsuarioController.php:32-42` marca leída + back()); (2) el payload de
APROBACIONES: el aprobador decide a ciegas — ni el objeto (`aprobable_type/id`) ni el cambio
(`datos[anterior→nuevo]`) viajan (`Aprobaciones.php:308-322`); (3) `urlDestino()` de
aprobaciones aterriza en la lista, no en el ítem, pese a existir `notificable_id`
(`Notificacion.php:126-127`); (4) el fallback sin plantilla = título de catálogo + cuerpo
VACÍO (`NotificacionDispatcher.php:116-119`).

## 🟢 LOTE NOTIF-1 — rama nueva `feature/notif-especificas` desde main fresco (M)
Territorio: motor M15 + aprobaciones M14 + superficies de notificación. **NO toques los
productores de Marcos** (`OrdenServicioCotizacion.php`, `AgendaTrabajo.php`,
`AgendaTrabajoController.php`, `ServicioTecnicoController.php`) — ese sublote va por canal
directo del dueño (paquete aparte).

### D. Superficies (el mayor impacto visible — hazlo PRIMERO)
1. **Campanita navegable y con sustancia** (`campanita.blade.php`): bajo el título, línea
   secundaria con el cuerpo (clamp a 2 líneas está bien); la fila navega a `urlDestino()`
   además de marcar leída (mismo patrón fila-como-link del lote S2 en la bandeja). Sin
   destino → comportamiento actual.
2. **Bandeja**: `whitespace-pre-line` al cuerpo (`notificaciones/index.blade.php:37`) y no
   imprimir la línea «...aquí: {url}» cruda — la fila YA navega (recorta el sufijo de URL
   en las plantillas, ver sublote A).
3. **`urlDestino()` puntual para aprobaciones**: `aprobacion.solicitada/escalada` →
   bandeja anclada al ítem (`aprobaciones.index` + fragmento/param con `notificable_id` si
   la vista lo soporta; si no, agrega el ancla `#aprobacion-{id}` a la vista de bandeja —
   es tuya). `aprobacion.resuelta` ídem sobre «Mis solicitudes».
4. **Fallback nunca-mudo** (`NotificacionDispatcher.php:116-119`): sin plantilla, el cuerpo
   interpola los escalares del payload como «clave: valor» en líneas — que un evento futuro
   sin plantilla degrade a legible, no a vacío.

### B. Payload de aprobaciones (`Aprobaciones.php` — territorio flota)
`datosNotificacion()` suma: `objeto` (morph `aprobable()` legible — p.ej. «Reporte de
producción 21-07 · Sucursal Mirador»), `cambio` (datos `anterior → nuevo` PRE-FORMATEADO a
string — renderizar() filtra no-escalares), `resuelto_por` (`resueltoPor->name`),
`rol_anterior` + `pendiente_desde` + `minutos` para escalada (regla + created_at +
`aprobacion_escala_minutos`). TODO placeholder nuevo con default ('—') — sin dato queda
literal `{x}` en el texto.

### A. Plantillas (seeder + entrega a prod)
- `aprobacion.solicitada`: título «Aprobación pendiente: {descripcion} ({magnitud})»;
  cuerpo con {objeto} y {cambio}.
- `aprobacion.escalada`: cuerpo «Escaló a tu rol desde {rol_anterior} tras {minutos} min
  sin respuesta. … Pendiente desde: {pendiente_desde}».
- `aprobacion.resuelta`: título «{resultado}: {descripcion} — {magnitud}»; cuerpo
  «…quedó: {resultado} por {resuelto_por}. Monto: {magnitud}…».
- **Entrega a prod (riesgo #1 de la auditoría):** `firstOrCreate` NO pisa las claves ya
  sembradas → las plantillas nuevas jamás llegarían. Mecanismo: **migración de datos
  one-shot que actualiza SOLO si el valor actual == texto del seed anterior** (edición
  manual de UI se respeta). Documenta el patrón en el seeder para los próximos.

### Verificación (reglas de la casa + específicas)
- Tests: campanita muestra cuerpo y navega (marcador accesible, no markup pegado);
  urlDestino puntual por evento; fallback interpola escalares; payload nuevo con defaults;
  migración one-shot respeta una plantilla editada (test que la edita y migra).
- OJO conteos: campos nuevos de payload NO crean notificaciones (los asserts de conteo no
  deben moverse); tests que asserten cuerpos exactos van a romper — actualízalos por
  marcador, no por string pegada.
- Suite COMPLETA por commit; Blade tocado → main fresco + build + grep superset (Marcos
  sigue activo); parte al buzón → doble llave.

## Pendientes que NO son tuyos
- Sublote C (payload cotización/terreno, productores de Marcos): paquete por canal directo
  del dueño — el Director lo redacta.
- P-TZ-03 QA de borde + #6 chips: dueño/Director.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
