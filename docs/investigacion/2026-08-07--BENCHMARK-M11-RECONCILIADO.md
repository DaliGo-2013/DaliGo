# Benchmark M11 — RECONCILIADO (vía A × vía B) · 2026-08-07

> Documento final del benchmark. **Vía A**: investigación del Director (14 sistemas + 8
> marcos, `2026-08-07--BENCHMARK-M11-via-A.md`). **Vía B**: investigación independiente
> despachada por el dueño (brief `BRIEF-BENCHMARK-M11.md`); llegó completa para 7
> sistemas (Katana, MRPeasy, Odoo, Fusion Operations, Fishbowl, Tulip, Defontana) y
> truncada después — los ángulos faltantes (plásticos, monitoreo) ya los cubrió la vía A,
> así que la truncadura NO deja huecos; si aparece el resto, se anexa sin re-abrir
> conclusiones.

## 1. Veredicto global

**Las dos vías, investigando por separado, llegaron a las mismas conclusiones
estructurales.** El backlog de la vía A queda **VALIDADO** (con refinos, sin cambios de
fondo):

1. **Nuestro offline y nuestro flujo aprobar/devolver son ventajas reales y raras.**
   La vía B lo reforzó con la fuente más dura: la documentación oficial de Fusion
   Operations declara EXPLÍCITO que el sistema no corre sin conexión. Ningún sistema de
   ninguna de las dos vías tiene offline de verdad (la única excepción es PlantStar, con
   hardware dedicado DCM). Y el aprobar/devolver solo tiene eco en Defontana (aprobar/
   reversar OP y consumos).
2. **Los gaps se cierran con reglas de negocio sobre lo que ya capturamos**: backflush
   de preformas al aprobar, duración en paradas, alertas de meta (SIC), OEE simplificado,
   molde-como-entidad con contador de ciclos.
3. **Comprar sigue sin tener sentido, con precio corregido**: la vía B encontró el precio
   oficial de Fusion (US$173/mes, más barato que lo que la vía A estimó) y el detalle de
   Odoo Chile (US$8,95/usuario/mes). Aun así: ninguno trae offline + aprobación + kardex
   integrado a Bsale, y todos duplicarían inventario. La decisión de construir en la app
   propia se mantiene — ahora con la referencia de que un piloto barato (Odoo One-App-Free
   o Katana Free) existe si se quiere tocar un backflush real antes de construir.

## 2. Discrepancias entre vías — resueltas

| # | Punto | Vía A | Vía B | Resolución |
|---|---|---|---|---|
| 1 | Precio Fusion Operations | ~US$600/mes (listing Shopify) y US$2.000/usuario/año (Capterra) | **US$173/mes** (página oficial del producto) | **Gana B** — fuente primaria. Los números de A eran de terceros/planes viejos |
| 2 | Precio Fishbowl manufactura | US$675/mes «Advanced Manufacturing» | US$595/mes «Fishbowl Advanced» | Ambos válidos: son niveles/productos distintos del mismo vendor; se citan ambos |
| 3 | Tulip offline | Dato duro: pierde datos con cortes >50 s, cola local ~100 eventos (support.tulip.co) | «no encontrado» | **Gana A** — tiene la fuente específica; B simplemente no la halló |
| 4 | Odoo mantenimiento de molde | «molde puede modelarse como equipo con su calendario» | Precisa: frecuencia SOLO temporal, **por ciclos/golpes NO existe** | **Gana B (refina A)** — refuerza que mantención-por-ciclos es exclusiva de los MES de plásticos y diferenciador nuestro si lo construimos |
| 5 | MRPeasy OEE | Fórmula completa por workstation (A/P/Q en tiempo real) | Solo «menciona OEE» | Sin conflicto — A más profundo, se mantiene A |
| 6 | Katana consumo real | Descuento al pasar MO a Done (backflush al cierre) | «descuento al reportar, con corrección manual» | Matiz del mismo mecanismo (el operario reporta cantidades reales; el movimiento ocurre al completar) — sin impacto en conclusiones |

## 3. Aportes NUEVOS de la vía B (entran al diseño)

1. **La pantalla del operario de Fusion, paso a paso** (Insert Production: nº de
   trabajador → orden → producto → operación → cronómetro → cantidad → confirmar; e
   Insert Downtime como botón hermano en la pantalla inicial). Referencia UX directa
   para evolucionar `mi-reporte`: **producción y parada como dos botones del mismo
   nivel**.
2. **«No Machine» en las paradas de Fusion**: la parada puede ser del TRABAJADOR y no
   de la máquina. Refina G2: nuestro registro de paradas debería distinguir
   parada-de-máquina vs parada-de-operario.
3. **Waste Categories + % NOK filtrable por tipo** (Fusion, bajo menú Quality): la merma
   se analiza como `NOK del tipo / (OK + NOK)` — fórmula lista para nuestro G6.
4. **«OEE Target» por centro de trabajo** (Odoo): cada máquina declara su meta de OEE
   (ej. 87,5 %). Refina G4: cargar meta por máquina, no una global.
5. **«Update instructions»** (Odoo Shop Floor): el operario propone cambios al
   procedimiento desde la app, para revisión del supervisor. **Ítem nuevo de backlog
   (kaizen digital, prioridad baja)** — calza con nuestra cultura de aprobaciones.
6. **Scrap a ubicación virtual** (Odoo): el scrap sale del flujo sin tocar stock real
   pero queda registrado y listado. Patrón para G6: registrar merma ANTES de que el
   kardex/backflush la procese.
7. **El operario no ve costos ni precios** en la app de planta (Katana). Regla de
   visibilidad a adoptar en nuestra PWA cuando el backflush exponga consumos.
8. **Componentes de receta clasificados Materia Prima vs Insumo-servicio** (Defontana,
   donde «insumo» = maquila/mano de obra externa como artículo). Para la BOM de G1: la
   receta del botellón puede llevar preforma + tapa (materia) y opcionalmente servicios.

## 4. Backlog FINAL para PLAN-M11-FINAL (8 ítems)

| # | Ítem | Impacto | Esfuerzo | Refinos de la reconciliación |
|---|---|---|---|---|
| 1 | **Backflush de preformas al aprobar** | ALTO | BAJO | BOM con clases de componente (MP/servicio, aporte B8); cantidades pre-llenadas corregibles; merma TAMBIÉN consume |
| 2 | **Paradas con duración + split planificado/no** | ALTO | BAJO | + distinguir parada de máquina vs de operario (B2); etiquetas fácticas, jamás culposas |
| 3 | **Alertas de meta (SIC cada 2h + M15)** | ALTO | MEDIO | la vara comercial es email; escalamiento estilo Mattec |
| 4 | **OEE por máquina/molde** | MEDIO | BAJO | + campo «OEE target» por máquina (B4); fórmula simple primero |
| 5 | **Molde como entidad + mantención por ciclos** | MEDIO | MEDIO | ni Odoo lo tiene por ciclos (B refina): diferenciador real |
| 6 | **Merma fina** (scrap de arranque, cavidades activas, molido valorizable) | MEDIO | BAJO | + % NOK filtrable (B3) + patrón ubicación-virtual (B6) |
| 7 | Trazabilidad lote preforma→botellón | BAJO hoy | ALTO | fase posterior; espera M04-F3/D-005 |
| 8 | **Kaizen digital**: operario propone mejoras al procedimiento (B5) | BAJO | BAJO | nuevo — encaja con el motor de aprobaciones M14 |

**Fases sugeridas (sin cambio)**: F1 = 1+2 · F2 = 3+4 · F3 = 5+6+8 · ítem 7 aparte.
Reglas transversales: conservar offline y aprobar/devolver tal cual (ventajas
verificadas por ambas vías); el operario no ve costos (B7).

## 5. Preguntas abiertas para Luis / el dueño (sin cambio + 1 nueva)

1. ¿Qué es exactamente «GP» en el pendiente histórico del tracker?
2. ¿Receta real: 1 preforma = 1 botellón + 1 tapa, o variantes por gramaje?
3. ¿El molido/scrap PET se revende o reusa? (define si el ítem 6 valoriza la merma)
4. ¿Cuántos moldes hay, y quién conoce su ciclo ideal y vida útil esperada?
5. *(nueva, de B2)* ¿Las paradas típicas son de máquina o de operario? (define si el
   split del ítem 2 necesita las dos ramas desde el día uno)

## 6. Estado

**Benchmark CERRADO.** Siguiente paso natural: borrador de `PLAN-M11-FINAL.md` con las
fases de §4 — se escribe cuando el dueño y Luis den el visto bueno a este backlog.
La flota sigue EN PAUSA; nada de esto genera dictados todavía.
