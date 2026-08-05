# Parte de cierre — Max-2 · P-DSP-09 · La PWA del conductor SOBRE la hoja (F2)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **P-DSP-09** — GO del dictado v16 (PLAN-DESPACHOS-V2 §3, F2)
ESTADO: **HECHA** — pide doble llave

## EVIDENCIA

Rama **`feature/pwa-sobre-hoja`** desde main fresco (`645447b`), pusheada — 3 commits,
suite COMPLETA verde en cada uno:

| Commit | Qué | Suite |
|---|---|---|
| c1 | Receptor obligatorio + cobro condicional + parada entregada (+ migración `rechazo_motivo`) | 1541 / 11.271 |
| c2 | Rechazo en puerta + evento M15 completo | 1550 / 11.308 |
| c3 | UI: dirección/teléfono/chip de cobro + panel receptor/cobro/rechazo + build | **1553 / 11.322** |

**23 tests nuevos** (EntregaSobreHoja 11 · RechazoParada 9 · smoke 3 dentro del primero).
**4 mutaciones corridas** (árbol commiteado, checkout + grep del marcador): receptor sin
required → rojo; cobro condicional ciego → rojo; pre-check del rechazo ciego → **VERDE**
(la 2ª capa —el re-check bajo lock— atrapó el duplicado: defensa en profundidad REAL, no
decorativa); ambas capas ciegas → rojo. Esa secuencia verde-luego-rojo es la prueba de que
las dos capas existen de verdad.

## Los 4 puntos del dictado, verificados por ejecución (banco browser, borrado)

1. **Dirección + comuna + teléfono por parada**: en la tarjeta, con el teléfono como
   enlace `tel:` (llamar es UN toque) — medido en el banco: 2 tarjetas, direcciones
   renderizadas, `tel:+56911111111` limpio de espacios.
2. **Receptor obligatorio**: gates escalonados verificados en vivo (sin receptor el botón
   no habilita; sin método de cobro tampoco; completo sí). RUT con `inputmode="text"` +
   `autocapitalize="characters"` (gotcha del DV K) y **normalizado a mayúscula** al enviar
   (`12345678-k` → `12345678-K`, verificado en la cola).
3. **Cobro en entrega**: chip visible ANTES de abrir el panel (el conductor sabe si cobra
   antes de tocar la puerta); bloque de cobro SOLO en paradas `cobrar_en_entrega` (la
   pagada no lo muestra — 1 bloque de 2 tarjetas en el banco); monto prefill con el total
   (119000 llegó solo). Persistido en la PARADA junto a `resultado=entregada`, en la MISMA
   transacción (gancho `$dentro` en registrarEntrega, patrón `transicionar`).
4. **Rechazo con motivo**: chips de motivos frecuentes + texto libre; viaja por la MISMA
   cola sin blobs (`encolarEntrega` ya era genérico — **cero cambios a offline-queue.js**);
   drenado verificado en vivo: confirmación y rechazo salieron cada uno a su endpoint con
   su payload, cola vacía, reload automático. Aviso M15 `despacho.parada_rechazada` a
   jefe_despacho/jefe_logistica/admin (campanita al tiro, aterriza en la hoja, placeholders
   resueltos, un duplicado NO dispara segundo aviso).

## El hallazgo de diseño que vale la doble llave

**La idempotencia GANA a la validación condicional.** El test del duplicado (no la teoría)
cazó que un reintento de la cola con uuid ya procesado tropezaba con la exigencia del cobro
→ 422 → la cola marcaría «no se pudo enviar» una entrega que SÍ está registrada, con el
conductor mirando un error falso. El guard: si el uuid ya existe, no se le exige nada y el
service responde duplicado. Es el mismo espíritu del patrón LoteServicio, aplicado a la
validación además del insert.

## Micro-decisiones (para tu veredicto)

1. **DV del RUT del receptor NO estricto** (formato suave `min:8|max:12`): un 422 diferido
   por la cola llega HORAS después de la puerta, imposible de corregir, y mataría la
   evidencia real (firma+foto ya capturadas). La UI empuja el formato correcto; el dato
   duro es la firma.
2. **`rechazo_motivo` como columna nueva** en `hoja_ruta_paradas` (V2 §2 no la enumeró;
   «rechazo con motivo» exige persistirlo — es lo que lee el jefe en el aviso).
3. **Rechazo SOLO para despachos con parada** (el `resultado` vive en la parada; un suelto
   no tiene dónde registrarlo). El despacho rechazado NO cambia de estado: la carga física
   vuelve a bodega y su reingreso es territorio M13/bodega. La parada rechazada sale de la
   pantalla del conductor.
4. **Compatibilidad de la cola vieja**: entregas encoladas ANTES del deploy drenarían sin
   receptor → 422 → sección «No se pudieron enviar» (ventana mínima; la cola normalmente
   está vacía al desplegar).

## ❓ Pregunta para el dueño (del dictado, ronda 2)

**¿El rechazo en puerta crea la devolución M13 automáticamente?** Hoy solo registra y avisa
(candado `test_el_rechazo_no_crea_una_devolucion_m13` fija ese contrato). Si el dueño quiere
la devolución automática, es un paso propio con su regla declarada.

## SIGUIENTE

**Doble llave de `feature/pwa-sobre-hoja`.** Con eso F2 queda completa y resta **P-DSP-10**
(edición de hoja en curso + cierre + bono — bloqueado por el Excel de la fórmula, ronda 2).
Espero dictado.
