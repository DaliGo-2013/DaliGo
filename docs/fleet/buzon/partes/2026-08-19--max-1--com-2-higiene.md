# Parte de Max-1 — 2026-08-19 · Dictado v74, COM-2 HECHO: la higiene de duplicados — el módulo Comercial queda listo para su QA

> Forjador A, stream 1 · rama `feature/param-com-2-higiene` (commit `c753f44e`) —
> **espera doble llave**. El lote más chico del proyecto, con la regla más estricta:
> delta CERO exacto.

## El número

| | |
|---|---|
| Duplicados unificados | **los 4 del mapa §5.2**: 25/página ×3 · 50 errores ×3 · chunk(500) ×2 · topes de medidas ×2 — cada literal quedó con UN dueño |
| Cambio de conducta | **CERO** — mismos números, misma pantalla, cero candados nuevos (declarado: la verificación del lote ES la cifra idéntica) |
| Suite | baseline main `5831889a` (worktree aislado): **2227 verdes / 15.510** · rama: **2227 verdes / 15.510** — **cifras IDÉNTICAS** (la prueba reina del delta cero) |
| Bundle | **byte-idéntico** |

## La decisión delegada del 25/página (con el paisaje como porqué)

El grep global da **15 `paginate(25)` en toda la app** — la convención que el mapa
describía. La constante quedó en **`Controller::POR_PAGINA`** (el padre abstracto que
TODOS los controllers ya heredan y que estaba VACÍO): ni clase de convenciones nueva
para un entero, ni acoplar controllers hermanos referenciándose entre sí. Solo migré
los 3 del módulo Comercial (no me desparramo): los otros 12 quedan para que cada
módulo los adopte cuando su propia auditoría los marque — el comentario de la
constante lo deja escrito.

## Los otros 3, en una línea cada uno

- **50 errores mostrados**: una variable `$maxErrores` al tope del bloque del
  resultado del import, con el comentario del duplicado ×3 que mata.
- **chunk(500)**: `CSV_CHUNK` en ProductoController (export + plantilla de medidas).
- **Topes de peso/medidas**: `REGLAS_MEDIDAS` compartida por el import y el
  formulario — el comentario del «Out of range» de MySQL, que vivía en UNA sola de
  las dos copias, ahora protege a ambas (el micro-beneficio típico de la higiene:
  el porqué deja de estar a medias).

## Verificación (la del delta cero)

- Greps del corte: `paginate(25)` = 0 en los 3 controllers del módulo ·
  `chunk(500)` = 0 · `9999999.999` exactamente 1 vez (la constante).
- Batería dirigida: **87 verdes** (ParametrosComercial + Cliente + Producto +
  CategoríaInterna + ListaPrecio) — sin tocar un test.
- Suite completa: baseline y rama con **la misma cifra exacta** en tests y
  aserciones. Sin mutación a propósito: no hay candado nuevo que mutar; en un lote
  cuyo contrato es «nada cambia», el candado es la igualdad de cifras.

## Para el radar del Director

- Con la doble llave, **el módulo Comercial queda completo** (mapa §5.2 saldado: 2
  nivel-1 en producción con COM-1, higiene hecha, 7 nivel-3 con porqué, 2 cross
  anotados) → QA del dueño: las 2 listas editables una-por-línea + TODO lo demás
  idéntico (esa es la gracia de este lote: no hay nada nuevo que ver).
- Espero el v75 (F0-OPERACIÓN — el territorio grande, con los cross del mapa DASH
  esperando) — no lo arranco solo.

## Fuera de alcance (declarado)

Los otros 12 `paginate(25)` de la app (sus módulos, sus mapas) · F0-OPERACIÓN (v75)
· territorio de Max-2 (MSG-2) y Marcos.
