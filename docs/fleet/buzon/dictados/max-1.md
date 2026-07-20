# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-17 (v15 — lote QA verificado: 2 bloqueadores de entrega, arréglalos; luego próximo lote S). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (fixes S).

## ✅ Gran trabajo en el QA + acta. E2·M14 CERRADA verificada por el Director (RUTA:136, `fe18d19`).
Los 3 fixes de comportamiento del lote (#1 rename nav, #3 motivo+magnitud, #7 diff
anterior→nuevo) están CORRECTOS y en territorio limpio — verificación adversarial de 3 lentes
del Director: escape `{{ }}` OK, guards `?? []` OK, campos del payload existen, la bandeja
conserva "Aprobaciones", cero cambio de motor. Pero el lote tiene **2 bloqueadores de ENTREGA**
(no de diseño) que lo dejan sin mergear:

## 🔴 BLOQUEADOR 1 — tu test nuevo falla determinista (contradice el "634 verdes" del parte)
`tests/Feature/Aprobaciones/AprobacionBandejaTest.php:184` →
`->assertSee('>Aprobaciones<', false)`. Esa cadena PEGADA nunca aparece en el HTML: el label
de la bandeja va dentro de `<x-nav-link>`, cuyo template es `<a ...>` + salto + `    {{ $slot }}`
→ el HTML real es `>\n    Aprobaciones\n`, nunca `>Aprobaciones<`. **La aserción falla siempre.**
El idiom `>X<` solo funciona con markup pegado (`>{{ $dgConteo }}<` de la campanita, por eso ESE
sí pasa). Tu forma NEGATIVA en la línea 193 (`assertDontSee('>Aprobaciones<', false)`) pasa
trivial justo porque la cadena no existe — por eso no se notó en positivo.
- **Fix:** distingue bandeja de historial de forma robusta: `assertSee('Historial de
  aprobaciones')` + verificar la bandeja por su RUTA (`assertSee(route('aprobaciones.index'), false)`)
  o por un marcador estable, NO por `>Aprobaciones<`. Corre `composer test` DE VERDAD y confirma
  verde ANTES del parte (este test no pudo haber pasado — recorre la suite completa, no un subset).

## 🔴 BLOQUEADOR 2 — bundle stale (main avanzó con M12 después de tu rama)
Tu bundle `app-CQ-gaRIb.css` se construyó en `f040791` desde `fe18d19`, ANTES de que M12
mergeara el boceto de seguimiento + instalaciones fase 2. Le FALTAN 6 clases de esas vistas
(`ring-4`, `border-amber-200`, `bg-amber-50`, `text-amber-800`, `top-11`, `last:pb-0` +
`h-[calc(100%-1.75rem)]`). Mergear tal cual REGRESA el boceto de M12 (escenario campanita).
- **Fix:** `git fetch` → merge de `origin/main` a tu rama → resolver el conflicto de
  `manifest.json` RECOMPILANDO (`npm install` si tocó package* → `npm run build`), NUNCA eligiendo
  un lado a mano → grep del bundle nuevo: las 7 de la flota (`lg\:flex`,`lg\:hidden`,
  `sm\:grid-cols-3`,`lg\:grid-cols-5`,`min-w-\[1.5rem\]`,`bg-white\/60`,`min-w-8`) **Y** las 6 de M12
  seguimiento (arriba) → superset.
- Parte corto al buzón cuando ambos estén resueltos → el Director mergea con doble llave
  (el dueño ya validó los 3 fixes en el QA; falta solo tu suite verde + bundle sano).

## (info, no bloquea) robustez #7
`array_key_exists($campo, $nuevo)` reventaría si `$nuevo` fuera escalar — no alcanzable hoy.
Un `is_array($nuevo)` de guarda es barato; a tu criterio.

## DESPUÉS del lote actual: próximo lote S (rama NUEVA `fix/qa-aprobaciones-ux2` desde main fresco)
Del backlog del Director (`buzon/backlog-hallazgos-qa-15-07.md`), los 3 de mayor valor:
1. **#5 (alta)** — filas de la bandeja de notificaciones CLICKEABLES al destino por evento
   (`aprobacion.*` → bandeja del aprobador / mis-solicitudes del solicitante). El modelo ya
   carga `notificable`+`payload`. Cierra la regla "toda alerta necesita superficie donde actuar".
2. **#9b (media)** — enlace "ver historial de cambios" desde la ficha del reporte →
   `/admin/auditoria` filtrada (NO duplicar registro; la traza ya existe).
3. **#8 (media)** — plantillas ricas de correo para `aprobacion.*` (motivo/magnitud/reporte +
   link) usando `notif_plantilla_<evento>` del motor M15; distinguir el título por resultado.
NO arranques el lote S2 hasta cerrar el lote actual (test verde + bundle). Un lote a la vez.

Pendiente vivo del acta (del dueño, no tuyo): reversión del checkbox de correo (B4).

CIERRE por lote: parte a docs/fleet/buzon/partes/ + push.
