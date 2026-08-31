# Parte — Max-1 · MIPROD-2: se retira el selector «Cavidades activas del molde» de la pantalla del soplador

> **Rama:** `feature/param-miprod-1-motivos` · commit `955e9453` (encima de
> `ecdabc79`/MIPROD-1, que ya está EN PRODUCCIÓN vía `563aa98`). `git cherry
> origin/main` confirma: **solo `955e9453` pendiente**; trial merge contra
> `a668a7f9` (main fresco): **0 conflictos**. Espera doble llave.

## Origen (fuera de cola, declarado)

Pedido DIRECTO del dueño (21-08, con la pantalla en mano): *«elimina esa
opcion porfavor»* sobre el collapsible «Cavidades activas del molde» del
reporte del soplador. Segundo pedido del mismo canal que MIPROD-1 — v81
autoriza (el plan PARAMETRICOS entero es de este stream).

## Qué se hizo

1. **`mi-reporte.blade.php`**: el collapsible se retiró completo, con su
   estado Alpine (`cavidades:` y `paneles.cavidades` fuera del `x-data`) y
   la fila del resumen bajo `@if ($reporte->cavidades_activas)` (legado solo
   con valor). Comentario Blade deja la lápida.
2. **`MiProduccionController::update()`**: la regla `cavidades_activas` se
   quitó — un request que igual mande el campo (form viejo cacheado, cola
   offline antigua) **no revienta ni pisa** el histórico, porque `fill()`
   con clave ausente no escribe (bitácora 20-08: ausente ≠ vacío).
3. **Ficha del jefe** (`admin/produccion/reporte.blade.php`): la fila de
   cavidades quedó bajo `@if` — se muestra solo en reportes históricos que
   la tienen.
4. **La columna SE QUEDA**: `Oee.php:119` y `Moldes.php:58` leen el
   histórico. Acople leve declarado: hacia adelante ya no se puede declarar
   un molde con cavidades tapadas — `null` era el default («Todas») y la
   opción no se usaba, que es exactamente por qué el dueño la sacó.

## Tests (clasificación bitácora 26-08)

- **Mueren con el campo** (2): `test_cavidades_activas_persisten` y
  `test_cavidades_fuera_de_rango_es_rechazada` — retirados con lápida en el
  encabezado de su sección.
- **Candado INVERSO nuevo**: `test_cavidades_activas_ya_no_se_aceptan_del_request`
  — histórico en 8, PATCH que manda 12 → redirect limpio y el 8 intacto,
  más `assertDontSee('Cavidades activas del molde')` en la pantalla.
- **Assert muerto** (1): el `assertSee('name="cavidades_activas"', false)`
  del candado de sección-de-paradas, quitado con nota en el propio test.

**Mutación post-commit**: reponer la regla `cavidades_activas` en `update()`
→ **1 rojo exacto** (el candado inverso: el PATCH vuelve a pisar el 8 con
12). Restaurado con `git checkout --`; grep del marcador: `cavidades`
aparece 1 vez en el controlador (solo el comentario-lápida).

## Suite y atribución (al centavo)

- Baseline propio (worktree aislado sobre `30c147db`): **2404 / 17.121**.
- Suite completa de la rama (MIPROD-1+2): **2407 / 17.159** — CERO rojos.
- Delta: +4/+45 de MIPROD-1 (ya verificado por el Director en su merge)
  −1/−7 de MIPROD-2. **La predicción decía −4 aserciones y fueron −7**; la
  diferencia de 3 quedó atribuida ANTES de declarar: mi conteo era de
  FUENTE (1 por llamada) y PHPUnit cuenta RUNTIME — `assertRedirect` son
  2 aserciones (esRedirect + location) y `assertSessionHasErrors` son 2
  (bag + clave). Los tests muertos se llevaban 11 runtime (2 redirects +
  2 sessionHasErrors incluidos), el inverso agrega 5, el assert muerto 1:
  −11 +5 −1 = **−7 exacto**. Lección anotada para las próximas
  predicciones: contar en runtime, no en fuente.
- **Predicción para el árbol del Director** (main `a668a7f9` = 2419/17.212
  de su cifra en `563aa98` + un commit de solo docs): **2418 / 17.205**.

## Bundle

`view:clear` + `npm run build`: **byte-idéntico** (status vacío entre
marcadores) — el retiro no deja clases nuevas ni huérfanas.

## Estado del stream

v85 LEÍDO (GO de LOG-3 con baseline nuevo `563aa98` = 2419/17.212).
**NO arranqué LOG-3**: ciclo nuevo espera el «revisa el buzón» del dueño.
Radar intacto (LOG-4 con el ⚠ del #13 · cross de Mi producción · cola de
funciones).
