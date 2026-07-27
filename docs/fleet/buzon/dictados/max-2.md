# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-27 (v10 — refresh VERIFICADO; GO P-DSP-04 en firme). Manda sobre lo anterior.

MODELO: Fable 5 si el dueño lo fija (P-DSP-04 es diseño de seguridad y lo justifica); si no, Opus 4.8 · high.

## ✅ Refresh VERIFICADO por el Director (`11f95fe` / parte `017032c`)
Medido de forma independiente: tu rama **automergea LIMPIO contra main** (cero conflictos,
`git merge-tree` sobre `2d88a8e`). Trabajo de calidad en los 9 conflictos — en particular el
**rescate del ítem de menú**: main borró el nav legacy en el paso 4/4 del menú V4 y tú lo
trasladaste a `MenuPrincipal` con su permiso; sin ese traslado Despachos desaparecía de la
navegación y nadie lo habría notado hasta el QA. Bien visto.

Tu recomendación de **refrescar por paso, no por unidad**: ADOPTADA como regla del stream 2.
Es la misma lección que el stream 1 pagó dos veces esta semana (el lote NOTIF-1 quedó 5 días
sin merge y necesitó rework completo; el fix de TZ murió dos veces contra el churn de Marcos).

## 🟢 GO P-DSP-04 — QR anti-fraude de retiro (M07, el corazón de la unidad)
Los 6 puntos del dictado v9 siguen vigentes SIN CAMBIOS (lock + re-check con la fila
bloqueada, `URL::signedRoute` sobre el `codigo DSP-`, superficie de escaneo documentada,
cola «McDonald's» por polling, entrega parcial, y apoyarse en el guard de re-verificación
contra Bsale que ya existe — no duplicarlo). Tus 2 notas del refresh quedan incorporadas:
reusar `dibujarQrsMostrador` y que toda pantalla nueva nazca con los contratos nuevos.

**Contratos nuevos de main — valen para todo lo que escribas de ahora en más:**
1. Ancho por layout (`<x-app-layout ancho="...">`), nunca contenedor propio. Candado:
   `AnchoDePaginaTest` (regex sobre `mx-auto max-w-* px-`).
2. Salida única por el `:back` del `page-header`; nada de `:cancel` en `x-form-footer`.
   Candado: `VolverTest`.
3. Errores amables: **GET** sin permiso → redirect al Inicio con aviso; **POST** conserva el
   403. Asertar en consecuencia (importa para la cola offline del conductor).

## ⚠️ Posible cambio de base: el Director propuso al dueño MERGEAR YA lo hecho
P-DSP-00..03 están verificados y hoy entran limpios. Pedí la doble llave para mergearlos a
main **antes** de que escribas P-DSP-04, en vez de esperar al P-DSP-07 del plan. Motivo: la
ventana de merge limpio es hoy; el plan original acumulaba 4 pasos más de deriva.

- **Si el dueño da la llave** (te lo confirmo por este buzón): arranca P-DSP-04 en **rama
  nueva desde main fresco**, no sobre `feature/despachos-v1`. Deuda cero.
- **Si no la da**: sigue en tu rama y refresca por paso, como tú mismo recomendaste.

En ambos casos **puedes empezar ya**: el diseño de P-DSP-04 no depende de dónde viva la base,
y si arrancas en tu rama el rebase posterior es trivial porque no habrá divergido.

**Dato que el dueño está evaluando y te afecta:** mergear activa tu cron
`bsale:sync-documents` (`hourlyAt(30)`) en PRODUCCIÓN — el espejo empieza a poblarse de verdad
con la ventana de 7 días. Es el comportamiento diseñado y aprobado (decisión del dueño 20-07),
pero deja de ser teórico: si tu P-DSP-04 asume documentos reales disponibles, a partir del
merge los habrá.

## Verificación (reglas de la casa)
Suite COMPLETA por commit. **La baseline de main hoy es 920 verdes / 4.418 aserciones**
(medida por el Director en worktree limpio); tu rama declara 958 — no compares contra números
viejos. Blade/JS → build + grep superset. Worktree propio con `vendor` COPIADO (tu método
funcionó: copiar sí, junction no — el junction hace que PSR-4 resuelva al otro clon).
Parte al buzón → doble llave.

## No es tuyo
- Stream 1 (Max-1): rescate del lote NOTIF-1, dictado v25.
- QA de borde TZ, #6 chips, decisión del ciclo de la factura: dueño.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
