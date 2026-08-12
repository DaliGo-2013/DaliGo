# Parte de Max-1 — 2026-08-12 · Dictado v48, Lote 2 HECHO: retiro completo del boceto «Seguimiento»

> Forjador A, stream 1 · rama `feature/menu-lote2-retiro-seguimiento` (commit `f30f2f1`) —
> **espera doble llave**. El Lote 3 (Estado→Documentos) no arranca hasta que este merge aterrice.

## El número

| | |
|---|---|
| Menú antes → después | **46 → 45 rótulos** (11 primer nivel + 33 subítems + 1 cuenta, verificado por tinker) |
| El diff | 11 archivos, **+3 / −244** — el único lote de la cola que RESTA código en vez de moverlo |
| Suite | baseline main `04ff980`: **1966** (idéntica a la referencia del Director) · rama: **1962 verdes (13.924 aserciones)** — delta **−4 exacto** (3 de SeguimientoDemoTest + 1 de TrasladoServicioTest) |
| Bundle | recompilado: **64.353 → 63.445 bytes** (−908 de clases que solo el boceto usaba) · superset de las vistas restantes: **0 perdidas** (control positivo: 618) |

## Qué se retiró (completo, no escondido)

- Ítem `seguimiento` de `MenuPrincipal` («Seguimiento (boceto)»).
- Ruta `admin.servicio-tecnico.seguimiento-demo` con su comentario (`routes/web.php`).
- `ServicioTecnicoController::seguimientoDemo()` + el helper privado `pasosSeguimiento()`
  (sus 3 llamadas vivían todas dentro del primero) + el import `Str` que quedaba muerto.
- La vista `admin/servicio-tecnico/seguimiento-demo.blade.php` y el componente
  `components/st/seguimiento-timeline.blade.php` (consumidor único; `components/st/`
  quedó vacío y se fue entero).
- `SeguimientoDemoTest` completo (3 tests: guest, sin-permiso, render de etapas).

Si algún día se retoma: vive en git (además la rama `feature/st-seguimiento-boceto`
sigue en el remoto, como anotó el dictado).

## El test ajeno retirado (declaración que exige el dictado)

`TrasladoServicioTest::test_el_seguimiento_del_cliente_tiene_el_paso_en_camino` hacía GET
a la ruta del boceto y asserteaba su viewData (`pasosConTraslado` con el paso
`en_traslado`) — **cubría el boceto, se retiró CON él**. El resto del archivo (traslados
reales) quedó intacto y verde. La decisión de negocio que ese test fijaba (el paso «en
camino» para el cliente, dueño 03-08) NO se pierde: sigue escrita en
`docs/reglas/traslado-maquinas-a-reparar.md`, y cuando el seguimiento real por folio se
construya, su candado nace con él contra datos reales (no contra una maqueta).

## Verificaciones del dictado

- **¿Datos reales o usuarios? NO** — la acción era render puro de arreglos hardcodeados
  (cero tablas, cero modelos, cero escrituras). La condición de STOP no aplicó; confirma F0.
- **¿Algo más enlazaba las rutas? NO** — `route:list | grep seguimiento` vacío; grep de
  `seguimiento-demo|pasosSeguimiento|st.seguimiento-timeline` en `app/ resources/ routes/
  tests/`: cero referencias vivas. Lo único tocado fuera del corte: 1 palabra en un
  comentario de `st/index.blade.php` que enumeraba «Seguimiento» entre las acciones de la
  sidebar (ya no existe — el comentario mentía).
- **¿Permiso huérfano? NO HAY** — el ítem usaba `view servicio tecnico|manage servicio
  tecnico`, el permiso compartido de todo el módulo ST. El seeder no se tocó.
- **Referencias que se dejaron A PROPÓSITO**: los docblocks de `VolverTest` y
  `AnchoDePaginaTest` que citan el boceto como ejemplo histórico de diseño (no ejecutan
  contra él — sus candados derivan de MenuPrincipal y se auto-adaptaron solos);
  «seguimiento» en `bodegas/index` (sentido stock/Bsale); los docs históricos.

## Candados

Batería dirigida post-corte: **75 verdes** (TrasladoServicioTest recortado + Sidebar +
MenuPrincipal + Volver + Navigation + MenuConsolidaciones + AnchoDePagina) — cero amoldes:
todos iteran desde la fuente única. El mini-candado del Lote 1 no aplica acá (no hay
anfitrión), tal como lo anotó el dictado; el candado del lote es la suite verde con el
delta exacto predicho (−4).

## Para el radar del Director

- `components/st/` ya no existe; si ST vuelve a necesitar componentes propios, el
  namespace se recrea sin drama.
- QA del dueño: el acordeón de Servicio Técnico ya no muestra «Seguimiento (boceto)»;
  todo lo demás del módulo intacto.

## Fuera de alcance (declarado)

Lotes 3-5 (esperan la doble llave de este) · el resto del mapa F0 sin visto bueno ·
seeder/permisos · docs históricos · territorio de Marcos y Max-2.
