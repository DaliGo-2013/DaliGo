# Parte — Max-1 · TWEAK-CAMPANITA-MOVIL listo (rama `feature/campanita-movil` @ `2e25c74`)

> De: Max-1 · Para: Director / Mauricio
> Dictado ejecutado: v13 (talla S). Housekeeping v12 también hecho: P-M16V1-03 [x] con
> `6caf1f9` en RUTA (`d0c1962`, main).

## El tweak

- **Campana SIEMPRE visible en la cabecera móvil**, a la izquierda de la hamburguesa (zona
  `lg:hidden`, mismo estilo de icon-button), con **badge de no-leídas** (reusa `$dgConteo`
  del tope del nav — cero query nueva; `9+` sobre 9, patrón del partial).
- **Decisión documentada (punto 2 del dictado):** SIN dropdown en móvil — va directo a
  `notificaciones.index`. Razones: en 375px un dropdown tapa la pantalla, la página personal
  ya existe con todas las acciones, y el partial desktop está acoplado a hover/anchos de
  escritorio — reusarlo no era trivial. Un tap = la bandeja completa.
- **Hallazgo de reuso:** `<x-icon.bell />` YA existía en el catálogo (M15, formato exacto:
  fill=none, stroke 1.5, currentColor, aria-hidden) — se reusó, **cero componente nuevo**.
- **Accesibilidad:** `aria-label="Notificaciones (N sin leer)"` (sin conteo cuando N=0) +
  `sr-only`; objetivo táctil ≥40px verificado.
- La sección del fondo del hamburguesa **se queda** (dictado punto 3).

## Verificación

- **Suite 595 verdes (1.895 aserciones).** `CampanitaTest` extendido:
  `test_campana_movil_siempre_visible_con_badge_y_destino` (badge con conteo → leer-todas →
  aria-label sin conteo). Gotcha cazado en el camino: el `sr-only` del partial desktop
  siempre dice «(0 sin leer)», así que el assert va contra el aria-label EXACTO de la
  campana móvil, no contra el texto suelto (quedó comentado en el test).
- **Bundle `app-FGPKmQ6Z.css`, grep 10/10** (lg\:flex, lg\:hidden, las 4 de M16-v1
  min-w-8/-rotate-90/rounded-t/lg\:grid-cols-2, y las del badge gap-1/min-w-[1.25rem]/
  right-0/top-0).
- **Preview:** a 375 la campana es visible y tocable (40×40, x=283 — logo + campana +
  hamburguesa caben sin overflow); a 1280 se oculta (zona lg) y el dropdown desktop sigue
  intacto. Superficie compartida: la rama nació de main fresco (`d0c1962`), diff de 2
  archivos de código + bundle.

## Para la doble llave exprés

El dueño ya aprobó el concepto; falta el endoso del Director a este resultado → merge a
main = deploy. Con eso, el QA repetido de M14 (Δ ≥ 50) tendrá la campana a la vista en el
teléfono — las dos cosas se retroalimentan.

Pendiente vivo: confirmación del QA M14 con Δ ≥ 50 → P-M14-07 [x] + sello.

— Max-1
