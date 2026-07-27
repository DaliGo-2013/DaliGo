# Parte — Max-1: dictado v24 LOTE NOTIF-1 LISTO — `feature/notif-especificas` @ `e6fbcc6` espera doble llave · 2026-07-22

> De Max-1 (Forjador A) al Director. Los 3 sublotes (D superficies → B payload → A plantillas)
> completos en el orden dictado, con gate R-31 adversarial corrido y sus fixes aplicados.

## D. Superficies (lo visible)

- **Campanita con sustancia y navegación**: línea de cuerpo bajo el título (`line-clamp-2`,
  `whitespace-pre-line`) y la fila NAVEGA a `urlDestino()` además de marcar leída — hidden
  `ir=1` en el form; el botón «Leída» de la bandeja conserva su `back()` (sin `ir`). Sin
  destino → comportamiento actual, como ordenó el dictado.
- **Bandeja**: cuerpo con `whitespace-pre-line`; ya no se imprime URL cruda (ver A).
- **`urlDestino()` PUNTUAL**: aprobaciones aterrizan en `#aprobacion-{id}` (la bandeja y
  «Mis solicitudes» emiten el ancla con `scroll-mt-6` + `target:ring`). Sin morph → la
  lista, como antes (compat con filas antiguas).
- **Fallback nunca-mudo**: sin plantilla, el cuerpo interpola los escalares del payload
  («clave: valor» por línea, `url` excluida — la fila ya navega).

## B. Payload de aprobaciones (el aprobador ya no decide a ciegas)

`datosNotificacion()` suma `objeto` (morph legible: «Reporte de producción 22-07 · turno
día · Soplador»), `cambio` («Asignadas: 450 → 500», pre-formateado), `resuelto_por`,
`rol_anterior` (de la REGLA — tras escalar, el rol vigente ya es el nuevo),
`pendiente_desde` (`enChile()`, doctrina P-TZ-02) y `minutos`. Todo con default `'—'`.

## A. Plantillas + entrega a prod (el riesgo #1 de tu auditoría)

- Textos nuevos exactos del dictado (magnitud al título, objeto+cambio al cuerpo, sin
  `{url}` en el cuerpo). **El link rápido del correo (hallazgo #8 del dueño) sobrevive
  como BOTÓN estructural** en el Blade del mail desde `payload.url` — la plantilla queda
  limpia sin perder el acceso de un tap.
- **Migración de datos one-shot** `2026_07_22_100000`: actualiza SOLO si el valor vigente
  == seed anterior (comparación por JSON decodificado, inmune a escapes), respeta
  ediciones de UI, `down()` simétrico, y **`Cache::forget` por clave** (Configuracion
  cachea `rememberForever` — sin eso el texto viejo seguía sirviéndose). Patrón documentado
  en el seeder. **Convergencia notable**: Marcos entregó por canal directo su sublote C con
  el MISMO patrón (su one-shot de terreno/cotización, también con `Cache::forget`) — claves
  100% disjuntas, cero conflicto; mergeado a la rama.

## Gate R-31 (workflow 4 lentes + refutación) — 2 confirmados, ambos resueltos/aceptados

1. **[media] El cableado `ir=1` no tenía test** (la feature insignia podía morir en verde):
   la campanita es el ÚNICO emisor de `name="ir"` en resources/ → assert agregado al test
   del dashboard. FIX aplicado.
2. **[baja] Ancla a ítem ya resuelto/fuera de página**: si la solicitud se resolvió antes
   del tap (multi-aprobador) la bandeja solo lista pendientes → aterriza arriba sin ring.
   Degradación con gracia (superficie correcta; el lock igual diría «ya fue resuelta» al
   actuar). ACEPTADA para v1, documentada aquí.

Fixes extra del gate (sospechas que verifiqué yo — 11 refutadores murieron por límite de
sesión de la cuenta): `line-clamp-2` estaba ANULADO por un `block` en el mismo span (orden
del bundle) → removido; `describirCambio` compara como string (un `0` nuevo sin valor
anterior sí es cambio; `!=` laxo lo perdía). Quedan ANOTADOS para v2/Director, sin fix:
botón del mail no-bulletproof en Outlook-escritorio (motor Word ignora padding en `<a>` y
`pre-line`) · doble link transitorio en mails PENDIENTES pre-deploy (cosmético, se agota
solo) · `titulo` 191 chars vs descripcion 255 (riesgo PRE-existente que los asuntos nuevos
alargan ~10 chars) · inconsistencia bandeja (la fila navega SIN marcar leída — decisión de
diseño para el Director) · plantilla escalada sin assert de render E2E (cubierta por seed
+ unit del payload).

## Verificación

- **Suite completa 772 verdes (2.505 aserciones)** en el árbol mergeado (mi lote + sublote
  C + preformas dañadas). Corridas previas: 43/43 del lote; una corrida tuvo **1 flaky
  AJENO** (`IngresoTallerPublicoTest::la foto se sirve solo con sesion`, 404 en suite
  completa bajo carga, pasa aislado y en re-corridas — territorio ST, lo dejo señalado por
  si el Director quiere ficha).
- Bundle `app-DLrdfNJe.css`, **superset 28/28** (+`line-clamp-2`, `target:ring-inset`,
  `target:ring-brand-300` — `brand-400` NO existe en el `@theme`, gotcha bitácora
  2026-07-01, por eso ring en 300).
- OJO conteos del dictado: cero notificaciones nuevas (solo payload/textos); los 2 asserts
  de cuerpos exactos del lote S2 actualizados POR MARCADOR como se ordenó.

## Consumo

Jornada v24: talla **M-L** (lote 3 sublotes + gate R-31 con 18 agentes/1.5M tokens de
subagentes + 3 suites completas). La cuenta TOCÓ SU LÍMITE de sesión a media auditoría
(reset 17:50 Chile) — 11 refutadores caídos, verificación completada a mano. `/usage`
exacto: Mauricio, cuando puedas.

**Espera doble llave.** Nota de orden: main ya contiene el sublote C; esta rama lo trae
mergeado, así que el merge es fast-forward-limpio sobre `61da8ea`+.
