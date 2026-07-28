# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-28 (v28 — P-NAV-06 y #6 chips EN PRODUCCIÓN; standby). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ AMBOS LOTES EN PRODUCCIÓN (merge `80ac3db`, doble llave, Deploy success)
- **P-NAV-06**: Kardex, Máquinas y Tipos de botellón bajo Operación; Conductores bajo ST.
  QA del Director en staging: las 5 rutas responden 302 (incluido el kardex en
  `/admin/produccion/movimientos` — ojo, no `/admin/produccion/kardex`, me confundí al
  probar y quedó como nota).
- **#6 chips del motivo del ajuste**: la idea de producto del dueño está viva.
- **Suite verificada sobre el árbol final: 1019 verdes / 5.506 aserciones.** Cero archivos de
  `public/build` tocados por ninguno de los dos lotes, como declaraste.

**Dos cosas que hiciste bien y quedan como precedente:**
1. **Quitaste 4 «Volver», no los 3 que dicté.** El Kardex también tenía `:back` y el candado lo
   habría cazado. Encontraste lo que el dictado no listaba, en vez de ejecutar la lista al pie
   de la letra.
2. **Actualizaste el candado en vez de silenciarlo** — y lo giraste al sentido inverso
   (`test_las_ex_huerfanas_estan_en_el_menu`: si algún día salen del menú, vuelven a necesitar
   su Volver). Eso es mejor que lo que pedí.

**Y el hallazgo aguas abajo:** que el `{cambio}` de las plantillas NOTIF-1 duplicara el motivo
lo encontraste porque te pedí confirmar el efecto y fuiste a mirarlo de verdad. Fix con test
positivo+negativo. Ese es el estándar.

## 🟡 Sub-paso del bundle de diseño: NO es tu problema
Hiciste lo correcto al NO inventar `DesignCaptureTest` desde cero: no existe en main y el
mecanismo vive en la sesión de rediseño del dueño (`design/menu-talana`). Habría sido
infraestructura no dictada con colisión probable. **Queda en manos del dueño** decidir si esa
sesión regenera su bundle post-merge. No lo retomes por tu cuenta.

## 🟡 STANDBY
Sin rama nueva. El dueño está priorizando y hay que cuidar el presupuesto de sesiones. Al
abrir, si el buzón sigue en v28: borra del remoto `fix/nav-huerfanas` y
`feature/chips-motivo-ajuste` (ya mergeadas) y quédate disponible.

Cola posible cuando el dueño decida, en orden de valor que yo veo:
1. **P-NAV-05** — gate R-31 formal de E-NAV. Es lo único que le falta a la unidad del menú
   para cerrarse, pero el paso incluye QA del dueño en celular: es suyo, no tuyo.
2. **La incoherencia campanita/correo** (`payload['url']` sin ancla, `Aprobaciones.php:303`):
   la campanita aterriza en la tarjeta puntual y el botón del correo va a la lista pelada.
   Chico, cierra NOTIF-1 del todo. Toca `assertSame` de `payload['url']` en
   `AprobacionAccionableTest`.
3. **Sublote C de notificaciones** (payload de cotización/terreno) — territorio de Marcos,
   salvo que el dueño te lo pase.

## No es tuyo
- P-DSP-04: Max-2 tiene 4 hallazgos MEDIA por arreglar (dictado v11). No lo toques.
- Decisión del ciclo de la factura, P-TZ-03, el bundle de diseño: dueño.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
