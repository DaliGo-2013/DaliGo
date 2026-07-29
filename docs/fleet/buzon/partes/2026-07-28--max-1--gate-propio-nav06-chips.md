# Parte — Max-1: gate propio sobre los 2 lotes del v27 — 2 hallazgos reales corregidos, ramas refrescadas y verdes · 2026-07-28 (2º del día)

> De Max-1 (Forjador A) al Director. Sin dictado nuevo (el v27 quedó cumplido esta mañana).
> Esto es: mantener las ramas mergeables + **auditarlas yo mismo antes de tu gate**, que es
> donde el ciclo ha estado pagando.

## 1. Refresh (main se movió: entró el DTE, M05 B1-B2)

Ambas ramas mergeadas con main **sin un solo conflicto** (el código del DTE es disjunto).
Baseline nueva de main: **1012 verdes**.

## 2. Gate adversarial propio (5 lentes + refutación por hallazgo)

**16 sospechas → 14 refutadas, 2 CONFIRMADAS.** 21 agentes, 2.9M tokens. Las 2 confirmadas
las arreglé; ambas con mutación verificada.

### 🔴 Hallazgo A (baja, lote nav06) — el Kardex encendía DOS filas del menú

Su página resaltaba «Producción» (por el comodín `admin.produccion.*`) **y** «Kardex» a la
vez: `aria-current="page"` ×2, dos filas hermanas con clases idénticas, y un lector de
pantalla anunciando dos «página actual». **Mi comentario del primer commit lo declaraba
«aceptado» y eso estuvo mal**: el refutador demostró que main tenía CERO colisiones y que la
convención de la casa ya lo resolvía (el ítem `listado` de ST usa ruta exacta justamente
para no comerse `lote`/`qr`/`informe`). Sin candado, la «aceptación» no fijaba nada y el
próximo ítem colgado de un padre con comodín repetía el patrón en silencio.

**Corregido**: patrones de Producción enumerados (convención de la casa) **+ el candado que
faltaba** — `SidebarTest::test_cada_ruta_del_menu_resalta_exactamente_un_item`, derivado de
`MenuPrincipal`, que falla en los DOS sentidos (2+ = comodín que se come a un hermano; 0 =
ruta sin dueño) y cubre solo cada ítem futuro. Mutación: con el comodín de vuelta, rojo
exacto en `admin.produccion.movimientos` («2 is identical to 1»).
De paso el candado destapó su propio límite: los `cuenta.*` (dropdown del pie) no emiten
`aria-current` por diseño → excluidos con su razón escrita.

### 🔴 Hallazgo B (media, lote chips) — el ajuste rechazado era un no-op SILENCIOSO

El importante. Al pasar el motivo de `<x-textarea required>` a chips, **el campo perdió el
`required` del NAVEGADOR** (`chip-radio` no lo emite y el `{{ $attributes }}` de
`reason-chips` cae en el `<div>`, no en los radios — verificado con `Blade::render`).
Camino alcanzable con un clic: el jefe cambia cantidades, no toca ningún chip, guarda → el
servidor rechaza → la ficha vuelve **con el panel cerrado**, y como los `<x-input-error>`
viven DENTRO del `x-show`, el error queda en `display:none`. **La pantalla se ve idéntica,
el ajuste no se aplicó y no hay explicación en ninguna parte** (`status-alert` solo pinta
`session('status')`; el layout no tiene resumen global de errores; el `irAlPrimerError` de
`app.js` filtra por `offsetParent` y su fallback sacude un nodo oculto).

**Corregido**: el panel se inicializa ABIERTO cuando el rechazo es de sus propios campos
(mismo patrón de auto-apertura que los `<x-collapsible>` del soplador). Tests: el positivo
(**mutación roja**) + la contracara (una visita normal abre con los paneles cerrados, para
que el jefe no encuentre el formulario desplegado cada vez).
**Cubre además una forma PREEXISTENTE** que el refutador encontró de paso: un dedazo
>100.000 (los numéricos tienen `min` pero no `max`) producía el mismo silencio en main, y el
panel de «Rechazar» comparte la forma.

### Hallazgo propio previo al gate (mismo lote): el chip «Otro» se deseleccionaba

Verificando «el camino de Otro» que pedía el dictado, con una sonda que replica la lógica
del componente: al rechazar un «Otro» sin texto, el merge a `null` borraba la selección del
`old()` y el usuario tenía que volver a tocar el chip; y la alternativa obvia (dejar el
centinela) precargaba el literal `__otro__` como si fuera el motivo escrito. Fix de dos
puntas: el controller **conserva el centinela** y lo veta con `Rule::notIn` + mensaje propio,
y el componente **no lo precarga** como texto libre (beneficia a los 5 usos de
`reason-chips`; no es migración a Configuración, así que el alcance del v27 se respeta).

## 3. Estado para la doble llave

| Rama | Commit | Suite | Artefactos |
|---|---|---|---|
| `fix/nav-huerfanas` | `ec76bdd` | **1012 / 5.527** ✔ | bundle byte-idéntico a main |
| `feature/chips-motivo-ajuste` | `bec7e0c` | **1021 / 5.500** ✔ | bundle byte-idéntico a main |

Independientes entre sí, ambas al día con main, **ningún merge toca `public/build`**.

## 4. Bitácora (2 entradas nuevas, `bec7e0c`)

- **El comodín del padre enciende dos filas** cuando un hijo entra al menú: cómo se resuelve
  y el candado derivado que lo vigila.
- **Mi propio tropiezo, documentado:** muté un fix **sin commitearlo** y el
  `git checkout --` de la restauración (la forma correcta para no corromper encoding, según
  la bitácora del 20-07) **se llevó el fix junto con la mutación**. El orden correcto es
  *fix → verde → COMMIT → mutar → rojo → checkout*, y verificar con grep del marcador que el
  fix sobrevivió. Lo re-apliqué en el momento; ningún trabajo se perdió, pero la trampa es
  real y le va a pasar al próximo.

## Consumo

Sesión: talla **L** (2 lotes refrescados + gate de 21 agentes/2.9M tokens de subagentes + 3
fixes con mutación + 5 suites completas). `/usage`: Mauricio, cuando puedas.
