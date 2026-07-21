# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-21 (v22 — P-TZ-02 EN PRODUCCIÓN; v2 cerrada sin merge por decisión del dueño: las 2 líneas van con Marcos; tú subes SOLO los tests). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (tarea S).

## ✅ P-TZ-02 render EN PRODUCCIÓN (merge `50f1f61` + integración `360a19e`, doble llave 21-07)
Suite ejecutada por el Director ×2 (686 tu rama vieja · 700/2243 el árbol final con el
carrusel de Marcos); manifest resuelto lado main con evidencia (`xuQc5R3b` superset: snap 4/4
+ flota 4/4; tu `BORCJ-Y7` eliminado como huérfano); Deploy+Tests success; staging vivo.
**P1 del QA muerto: los historiales muestran hora chilena.** Tu refresh proactivo de la rama
(`22560e7`, manifest regenerado con build) fue el movimiento correcto y ahorró un ciclo.

## 📕 v2 calendario: CERRADA SIN MERGE — decisión del dueño 21-07
Marcos reescribió TU línea por segunda vez («abre en HOY», `bc51e82` — la grilla ahora es
:113 con `$sel` delante). Perseguir su churn (4 features de agenda en 24h) con ramas de la
flota = colisión perpetua. **El dueño le pasa directamente a Marcos las 2 líneas restantes**
(grilla :113 `isToday`→`esHoy` + tu hallazgo `update():151` `?? now()`→`FechaNegocio::ahora()`
— tu auditoría pagó: el fix que dejaste "listo para dictar" va tal cual). Borra
`fix/tz-calendario-agenda-v2` del remoto como hiciste con la v1. Tu disciplina en las 3
iteraciones fue impecable — el cierre por otra vía no le quita nada.

## 🟢 TAREA — rama SOLO-TESTS `test/tz-frontera-agenda` desde main fresco (S)
Rescata lo valioso de la v2: la batería de frontera, SIN tocar Blade ni controller (cero
archivos de Marcos → inmune a su churn). En `FechaNegocioTest`:
- Frontera nocturna del calendario fusionado (23:00 Chile): grilla resalta el día chileno
  scoped a la celda `#dia-{iso}` (chileno CON `font-bold text-brand-600`, UTC-mañana SIN él),
  cabecera «· HOY» correcta, default del index en el mes chileno.
- Frontera de MES (31-07 22:00 Chile): abre julio, no agosto-UTC.
- El caso del hallazgo: editar solicitud QR sin fecha a las 21:30 Chile del 31-07 →
  redirect a `?anio=2026&mes=7` (julio chileno, no agosto UTC).
**GATE de merge: estos tests DEBEN nacer VERDES — solo se mergean DESPUÉS de que Marcos
aplique las 2 líneas.** Constrúyelos ya; si al correrlos la grilla/update siguen UTC, el test
rojo es la CONFIRMACIÓN de que Marcos aún no pasa — parte al buzón declarando el estado
(verde = Marcos ya aplicó, listo para llave; rojo = rama en espera, el Director vigila main).
Mutación inversa no aplica acá (el fix no es tuyo): el par positivo/negativo por celda cumple
el anti-verde-engañoso.

Sin Blade → sin build. Suite completa igual (regla de la casa). Parte al buzón.

## Pendientes que NO son tuyos
- Las 2 líneas de agenda: Marcos (canal directo del dueño).
- P-TZ-03 (QA de borde ~21:30 Chile): el dueño. Con render vivo ya puede verificar además
  la hora chilena en los historiales.
- #6 chips paramétricos: el Director lo dimensiona con el dueño.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
