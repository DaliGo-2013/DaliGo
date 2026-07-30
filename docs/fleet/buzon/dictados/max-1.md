# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-30 (v31 — gate R-31 EN PRODUCCIÓN, E-NAV CERRADA; en pausa hasta el próximo GO). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ Tu gate R-31 EN PRODUCCIÓN (merge `ba4944b`, Deploy success) — E-NAV CERRADA 10/10

El lote entró entero con doble llave Director+Mauricio el 30-jul. Verificación del Director:
merge limpio, diff 9 archivos = tu parte, spot-checks del fix ALTO y del candado confirmados
en el código, **suite completa sobre el árbol mergeado: 1199 verdes / 7.843 aserciones —
exacta a tu cifra**.

Lo que cierra E-NAV:
- El **QA del dueño en celular ya está hecho** («todo funcionando súper bien»). Tu fix ALTO
  (drawer cerrado recorrible por teclado) es invisible a la vista — el QA visual confirma que
  nada se rompió; la pasada de teclado queda como opcional del dueño.
- **Decisión de producto tomada por el dueño:** la campanita **ES** la entrada a la bandeja
  personal de notificaciones, **a propósito**. No se agrega ítem de menú. Tu hallazgo queda
  cerrado como diseño intencional, no como deuda.

Tu quinta mutación cazando tu propio verde-engañoso, y el rojo Windows-only de
`ArchivoInputTest` declarado y arreglado con una línea test-only: exactamente la disciplina
que este gate necesitaba. `MenuPermisoRutaTest` era el candado que le faltaba al menú desde
que existe — que los 31 ítems cumplan hoy vale más porque ahora no pueden dejar de cumplir
en silencio.

De tus 7 hallazgos reportados sin tocar: el MEDIO de M05 («Ver documento» exigirá dos
permisos cuando la emisión llegue al mostrador) va por **canal directo del dueño a Marcos**
— no lo toques. Los de decisión de producto (objetivos táctiles 44 vs 48 px, focus-restore
al cerrar el drawer, capas de Escape) quedan con el dueño; si decide, vuelven como paso
propio con GO.

## ⏸️ EN PAUSA — sin lote activo

E-NAV era tu stream y quedó cerrada. El próximo GO depende de una decisión de alcance del
dueño que el Director le está planteando (candidata fuerte: **M13 Devoluciones**, prioridad
★ del dueño, hoy en 0 % — arrancaría desde especificación en rama nueva desde main fresco).
No arranques nada por tu cuenta: hay dos manos activas cerca (Marcos en M05 Facturación/DTE,
Max-2 en P-DSP-05 PWA del conductor) y el costo de pisarse es mayor que el de esperar.

Si abres sesión y este dictado sigue en v31: revisa el buzón por si hay v32, y si no lo hay,
cierra sesión sin gastar ventana.

## Recordatorios
Suite COMPLETA antes de cualquier push (baseline hoy **1199**). Blade tocado → build + grep
superset. Conflictos con `git checkout origin/main -- <archivo>`, nunca con `>` (el `>` de
PS 5.1 mete BOM y revienta Vite). Parte al buzón → doble llave.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
