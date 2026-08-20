# Parte de Max-1 — 2026-08-20 · Dictado v77.1, REBOTE HECHO: el tip del panel deriva (re-parte corto, espera doble llave)

> Forjador A, stream 1 · rama `feature/param-ope-1-ventanas` re-anclada y pusheada
> (`434a31ed..fd009294`). **NO mergeo**: doble llave. NO arranco OPE-2. NO toqué
> `MenuPrincipal` (MSG-4 es de Max-2).

## El rebote (una línea + 3 aserciones)

- `index.blade.php:97`: «(por defecto, últimos 7)» → «(por defecto, últimos
  `{{ $diasPanel }}`)». Mismo texto, cifra viva.
- Candados extendidos (**+0 tests / +3 aserciones**, declarado): en el test de
  mover el panel, `assertSee('por defecto, últimos 14')` +
  `assertDontSee('por defecto, últimos 7')`; en el de BD virgen, la otra punta
  (`por defecto, últimos 7`). La forma «por defecto, últimos N» es ÚNICA del tip
  (el rótulo grande dice «Últimos N días») — sin colisión verde-engañosa.
- **Mutación post-commit** (re-poner el 7 en prosa): **1 rojo EXACTO predicho** —
  el test de mover; el de BD virgen queda verde con la prosa (esa asimetría es la
  razón del assert nuevo). Restaurado con `checkout --` + grep del marcador.

## Re-ancla a main con MSG-3 (criterio declarado: MERGE, no rebase)

`git merge origin/main` → merge commit `9dc4fd68`, limpio como anunciaste. Elegí
merge porque la rama ya estaba pusheada (rebase reescribiría historia publicada).
El merge no trajo `package.json` ni `public/build` (MSG-3 fue Blade/Alpine puro).

## Verificación — la predicción clavó EXACTA

- Suite COMPLETA sobre el árbol re-anclado: **2251 / 15.722** = tu baseline
  (2244 / 15.594 en `1d7ad3e`) **+7 tests / +101 aserciones** (OPE-1) **+24**
  (candados-iteradores del seeder, sin cambio con MSG-3) **+3** (las del rebote).
  Delta 100 % atribuido, contingencia no necesaria.
- `ParametrosOperacionTest`: 7/7, 101→104 aserciones.
- **Bundle byte-idéntico** (status real vacío entre marcadores): la interpolación
  no trae clases nuevas.

## Para el radar

- Rama lista en `fd009294` (fix) sobre `9dc4fd68` (merge): **va la doble llave**.
- Con el merge puesto, el deploy siembra las 3 claves solo y los defaults son los
  históricos — producción no se mueve.
- Espero dictado para OPE-2. NO lo arranco solo.

## Fuera de alcance (declarado)

OPE-2/OPE-3 · merge a main (doble llave) · `MenuPrincipal`/MSG-4 (Max-2) ·
RUTA-MAESTRA/Trello (Director).
