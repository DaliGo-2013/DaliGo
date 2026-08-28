# Parte de Max-1 — 2026-08-20 · Dictado v82, LOG-1 HECHO: la app dejó de mentir (espera doble llave)

> Forjador A, stream 1 · rama `feature/param-log-1-textos` (commit `816a611b`,
> nace de `bdf6140b`) pusheada. **NO mergeo**: doble llave. NO arranco LOG-2.

## El fix urgente

La ficha del traslado prometía «el espejo se refresca **cada 15 minutos**» y
`bsale:sync-stock` corre `hourlyAt(45)` — ahora dice «**una vez por hora**». No
es derivable limpio desde Blade (decisión declarada): texto verdadero +
comentario apuntando al schedule con el porqué. **El cron NO se tocó** (la
grilla */15 de I-01 es del scheduler, no del sync — intacta).

## La familia entera deriva (doctrina DASH-2)

- «folio 1000» ← `HojaDeRuta::FOLIO_PISO + 1`.
- «15 MB» ×2 ← `RespaldoDeDocumento::MAX_KB` (constante nueva) vía
  `topeLegible()`; `reglas()` también deriva (`'max:'.MAX_KB`) — tope y
  mensaje ya no pueden divergir.
- «llave N de 3» ×3 ← `HojaDeRuta::TOTAL_LLAVES` (constante nueva con el
  porqué: la salida del camión no es llave).
- «se usan 15 enteros» del pallet ← `PalletSimulado::BASE_CM`. **Declarado**:
  el «14,4 cm» (ficha del EPAL) y el «entre 1,60 y 2,20 m» (observación
  práctica) son prosa del mundo real, no valores del sistema — se quedan.

## El molde nuevo de candados (fuentes-constantes) y su asimetría demostrada

Las fuentes de estos rótulos son CONSTANTES, no claves movibles en runtime —
el molde DASH (mover la clave, ver la pantalla moverse) no aplica. El molde de
LOG-1 tiene dos mitades: **regla de oro EN PANTALLA** (los textos rinden
byte-idéntico + la verdad nueva del traslado, con la flota del simulador
sembrada) + **candado ESTRUCTURAL sobre los fuentes** (forma derivada
presente, literal viejo prohibido; contado con `substr_count` donde hay 3
gemelos — doctrina 29-07). La mutación post-commit (reponer UN «de 3).» a
mano) lo demostró: **los dos tests de pantalla quedaron VERDES** (un literal
que hoy coincide es indistinguible en el HTML) **y solo el estructural se puso
rojo** — 1 rojo exacto predicho. Esa asimetría ES la razón del candado.

## Lo que el propio candado cazó al nacer (2 rojos honestos)

1. **Mi comentario Blade reintroducía el literal** «cada 15 minutos» en el
   fuente (bitácora 30-07: documentar el defecto lo causa) — reformulado.
2. **La prosa del pallet no se emite con el catálogo vacío**: la página corta
   en `@if ($camiones->isEmpty())` antes de llegar — el assert de pantalla
   necesitaba sembrar `CamionesSimulacionSeeder` (y de paso quedó fijado que
   la pantalla del simulador SIN flota no promete nada).

## Verificación (invariante, delta 100 % atribuido)

- **Baseline propio** (worktree + robocopy + diagnóstico autoloader, sobre
  `bdf6140b`): **2311 / 16.109** — sobre el 2303/16.048 del dictado por los
  PRs/pushes de Marcos, absorbidos por el «recuenta tú».
- **Rama**: **2314 / 16.134** = baseline **+3 tests / +25 aserciones**, todos
  míos (`ParametrosLogisticaTest`, archivo nuevo — el espejo del módulo
  arranca). Sin claves de seeder → sin iteradores, como se predijo. Cero
  tests existentes movidos (los que asertan «llave 1 de 3» siguen verdes: el
  texto rendido es byte-idéntico).
- Batería dirigida (Carga + Despachos/Hojas + Vehículos + Conductores +
  Traslados + Baja): **465 / 1.975 verdes, cero amoldes**.
- **Bundle byte-idéntico** (status real vacío): textos sin clases nuevas.

## Para el radar del Director

- Rama lista: **va la doble llave**. Con el merge, la única conducta que
  cambia es que la ficha del traslado dice la verdad.
- Espero dictado para LOG-2 (la franja de 30 días de la flota — ahí derivan
  sus 3 rótulos, con la perilla). NO lo arranco solo.

## Fuera de alcance (declarado)

LOG-2/3/4 (radar) · merge (doble llave) · el texto «pagos → ruta → carga» de
la ficha (prosa del ORDEN, no un número — derivarla del mapa SIGUIENTE sería
sobre-ingeniería, declarado) · las funciones de la cola (buscador de folios,
caducidad QR, topes R11) · territorio de Max-2 y Marcos · RUTA-MAESTRA/Trello.
