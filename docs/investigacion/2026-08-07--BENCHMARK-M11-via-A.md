# Benchmark M11 Producción — Vía A (investigación del Director) · 2026-08-07

> ⚠️ **RECONCILIADO el 07-ago con la vía B** — el documento vigente es
> `2026-08-07--BENCHMARK-M11-RECONCILIADO.md` (valida este backlog, corrige el precio de
> Fusion Operations a US$173/mes oficial y suma 8 aportes). Este archivo queda como
> registro de la vía A.

> Insumo para la «versión final» del módulo de producción (orden de Luis). Investigados
> **14 sistemas** (MES/MRP livianos, ERPs chilenos, MES de plásticos) + **8 marcos de
> KPI** con fuentes URL por cada claim. Pendiente reconciliar con la vía B (Chrome
> AI/Cowork del dueño, brief en `BRIEF-BENCHMARK-M11.md`). Este informe NO es un plan:
> el backlog de §5 es candidato, se depura con el dueño y Luis.

## 1. Las tres conclusiones que importan

1. **Lo que ya tenemos es raro de bueno.** La captura offline del soplador y el flujo
   aprobar/devolver del jefe NO los tiene casi ningún comercial: Tulip (~US$12.000/año
   mínimo) **pierde datos con cortes de red >50 segundos**; Fusion Operations queda «muy
   limitado» sin internet; Katana/MRPeasy/Odoo no documentan offline. Y el flujo de
   aprobación jefe→operario solo aparece en Defontana (como dos aprobaciones separadas).
   **Cualquier evolución debe conservar estas dos ventajas — no migrar la captura.**
2. **Los 3 gaps grandes son reglas de negocio, no hardware ni módulos caros**: el
   descuento de preformas es un backflush trivial con BOM 1:1 disparado por la
   aprobación que YA existe; el OEE se desbloquea agregando DURACIÓN a los motivos de
   parada que YA tipificamos; y la meta-del-día en vivo es un job de proyección + una
   alerta M15 (la vara comercial es bajísima: Fusion solo alerta por email).
3. **Comprar no tiene sentido para esta planta**: Fishbowl Manufacturing US$675/mes,
   Tulip US$1.000+/mes, Intouch US$70/máquina/mes, Katana Manufacturing US$199/mes — y
   ninguno trae el flujo meta-diaria + aprobación + offline que ya tenemos; todos
   duplicarían el kardex. Lo racional es cerrar los gaps en la app propia. (Katana Free
   ≤30 SKUs y Odoo One-App-Free sirven como *pilotos de referencia* si se quiere tocar
   un backflush real antes de construirlo.)

## 2. Baseline — lo que M11 tiene hoy (verificado en el repo)

Meta diaria por soplador (máquina + tipo botellón + cantidad) → reporte del operario en
PWA **offline** → motivos de diferencia tipificados (faltaron preformas, falla de
máquina, mantención, cambio de molde, molde dañado, otro) → **aprobar/devolver** del
jefe (motor M14) → kardex local (`ProduccionMovimiento`) → historial 45 días, rendimiento
por máquina y por tipo. Pendientes declarados en tracker: descuento de preforma, meta del
día, GP.

## 3. Tabla comparativa (resumen; detalle con fuentes en §7)

| Sistema | Backflush MP | Merma+motivos | Paradas c/duración | OEE | Meta viva/alertas | Aprobación | Mant. molde | Offline | Precio |
|---|---|---|---|---|---|---|---|---|---|
| **DaliGo M11 hoy** | ✗ | ✓ motivos (sin duración) | ✗ | ✗ | ✗ | ✅ **único** | ✗ | ✅ **único** | propio |
| Katana MRP | ✓ al cerrar MO, cantidad real corregible | parcial | ✗ | ✗ | ✗ (refresh 10 min) | ✗ | ✗ | ✗ | Free/US$299+199 |
| MRPeasy | ✓ backflush al kiosk | 3 vías, sin motivos | indirecto | ✓ tiempo real | parcial email | QC on-hold | ✓ **por unidades producidas** | ✗ | US$49-149/usuario |
| Odoo Mfg | ✓ con tolerancia | scrap sin motivos | ✓ 3 categorías → OEE | ✓ | parcial | control points | ✓ molde=equipo, MTBF | ✗ | US$9-11/usuario |
| Fusion Operations | ✓ pre-llenado corregible | ✓ **waste codes** | ✓ **códigos + reporte** | ✓ 24h vivo | alertas solo email | ✗ | ✓ correctivo/preventivo | ✗ (pierde datos) | ~US$600/mes |
| Fishbowl | ✓ al cerrar WO | notas libres | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | US$675/mes |
| Tulip | vía ERP | configurable | ✓ automático (sensores) | ✓ | ✓ Andon+escalamiento | como app custom | ✓ CMMS apps | ✗ (pierde >50s) | US$12k+/año |
| Defontana (CL) | ✗ (movimiento explícito) | ✗ | ✗ | ✗ | ✗ | ✓ **2 niveles** | ✗ | ✗ | 2,5 UF/usuario |
| Softland (CL) | ✓ guías auto por receta | ✗ | ✗ | ✗ | faltantes en línea | ✗ | ✗ | ✗ | cotización |
| Manager (CL) | ✗ (vales) | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | ✗ | cotización |
| Flexline (CL) | ✓ real vs teórico | indirecta (alerta exceso) | ✗ | ✗ | alertas consumo | ✗ | ✗ | ✗ | cotización |
| DELMIAworks (plásticos) | ✓ **ciclos×cavidades** | ✓ separa **scrap de arranque** | ✓ | ✓ respeta cavitación | ✓ IQAlert | ✗ | ✓ **molde por ciclos** | ✗ | cotización |
| Epicor Mattec (plásticos) | vía ERP | ✓ por ciclo + SPC | ✓ MTBF/MTTR | ✓ + energía/pieza | ✓ escalamiento | ✗ | ✓ máquina+molde por ciclos | ✗ | cotización |
| PlantStar (plásticos) | ✗ (delega a ERP) | ✓ ~20 códigos precfg. | ✓ **auto por umbral de ciclo** | ✓ drill-down | ✓ email/SMS/voz | login operario | ✓ tool-life por ciclos | ✅ DCM 30 días | cotización |

Lectura vertical: **ningún sistema chico hace paradas-con-duración bien** (solo los MES
de plásticos y Odoo); **todos los que valen la pena hacen backflush**; **la mantención
de molde por ciclos es EL rasgo distintivo de los MES de plásticos** — y es calculable
con datos que ya capturamos.

## 4. Gap analysis contra el M11 real

### Gaps confirmados (varios sistemas lo hacen, nosotros no)
- **G1 · Descuento de preformas (backflush).** Estándar en 8/14. El patrón correcto para
  nosotros (validado por 3 fuentes independientes): descuento AUTOMÁTICO al momento en
  que el jefe APRUEBA el reporte (evento único auditable — una devolución jamás deja
  stock inconsistente), cantidad = (buenos + merma) × receta, con la cantidad pre-llenada
  corregible estilo Fusion («solo corrige lo que varió»). La merma TAMBIÉN consume
  preformas — descontar solo los buenos infla el inventario teórico (lección Microsoft
  Dynamics). Receta 1:1 preforma-botellón + tapas ⇒ BOM trivial.
- **G2 · Paradas con duración.** Nuestros 5 motivos ya son «casi el árbol nivel-1
  recomendado» (8-12 códigos máx). Falta: (a) hora inicio/fin, (b) separar planificado
  (cambio de molde, mantención) de no planificado (falla, faltaron preformas, molde
  dañado). Desbloquea: Disponibilidad del OEE, MTBF/MTTR por máquina Y por molde
  (gratis, sin CMMS), y el Pareto que dice qué atacar (los 3 motivos top concentran ~80%).
- **G3 · Meta del día en vivo.** El patrón ganador NO es un dashboard: es **Short
  Interval Control** (cortes de meta cada 2h con motivo del desvío — crítico en planta
  de UN turno: no hay turno siguiente que recupere) + **alertas que persiguen al jefe**
  («proyección < X% de la meta a esta hora» → M15/WhatsApp, con escalamiento estilo
  Mattec). La vara comercial es email simple — la superamos con un cron + M15.
- **G4 · OEE simplificado.** Fórmula corta: (buenos × ciclo ideal) / tiempo planificado.
  Falta solo: ciclo ideal por molde/tipo (dato a cargar una vez) y G2. Empezar con los
  «3 grupos para pyme»: estado máquina+paradas, output vs meta, calidad/scrap — no los
  12 KPIs.
- **G5 · Mantención por contador de ciclos.** El rasgo distintivo de los MES de
  plásticos (DELMIAworks, Mattec, PlantStar «tool life»), replicable sin sensores: el
  molde como entidad, acumulando producción aprobada; alerta a los N mil botellones.
  Correctivo casi gratis: reporte aprobado con «falla máquina»/«molde dañado» → genera
  OT de mantención (patrón Tulip Report-Fault; ya tenemos M15 para avisar).
- **G6 · Merma con más matiz.** (a) «Scrap de arranque» como motivo propio (Six Big
  Losses: los botellones malos post-cambio-de-molde son pérdida DISTINTA — hace visible
  el costo real de cada cambio); (b) merma valorizable: el PET molido es materia
  recuperable — patrón co-producto de MRPeasy; (c) campo «cavidades activas» en el
  reporte (molde con cavidad bloqueada = producción teórica distinta, invisible hoy).
- **G7 · Trazabilidad lote preforma→botellón.** Existe en la mitad de los sistemas;
  Katana la cobra US$249/mes como add-on (señal de que es módulo caro y separable).
  Modelo mínimo: Lot-History estilo Fishbowl sobre nuestro kardex. **Fase posterior.**

### Validaciones (donde YA superamos a los comerciales)
- **V1 · Offline-first**: ventaja competitiva real (evidencia dura en §1.1). Robustecerla
  y documentarla, jamás cambiarla por captura conectada.
- **V2 · Aprobar/devolver del jefe**: casi nadie lo tiene; además es el **trigger
  perfecto para G1** — la arquitectura existente ya está bien puesta.
- **V3 · Motivos tipificados**: base directa de G2/G4/G6; regla de oro al extender:
  etiquetas fácticas, nunca culposas («qué detuvo la producción», no «error del
  operador») — el que reporta es el mismo soplador, un código culposo garantiza datos
  falsos.

### Preguntas que este benchmark deja para Luis / el dueño
- ¿Qué es exactamente **«GP»** en el pendiente del tracker? (¿gráfico de producción?
  ¿gestión de preformas? — define si ya está cubierto por G3/G4).
- ¿La receta real es 1 preforma = 1 botellón + 1 tapa, o hay variantes por gramaje de
  preforma según tipo? (define la BOM de G1 — Dynamic BOM estilo Fusion si hay variantes).
- ¿El molido/scrap PET se revende o se reusa? (define si G6b vale el esfuerzo).
- ¿Cuántos moldes hay y quién sabe su ciclo ideal y vida útil esperada? (G4/G5).

## 5. Backlog candidato para PLAN-M11-FINAL (impacto × esfuerzo)

| # | Ítem | Impacto | Esfuerzo | Se apoya en |
|---|---|---|---|---|
| 1 | **Backflush de preformas al aprobar** (G1) | ALTO — cierra el pendiente nº1 del tracker y conecta producción↔kardex | BAJO — BOM 1:1 + regla en la aprobación existente | V2, kardex M11 |
| 2 | **Duración en paradas + split planificado/no** (G2) | ALTO — desbloquea OEE, MTBF/MTTR, Pareto | BAJO — 2 campos + reclasificar 5 motivos | V3 |
| 3 | **Alertas de meta (SIC)** (G3) | ALTO — el jefe recupera el turno EL MISMO DÍA | MEDIO — cron de proyección + M15 (¿WhatsApp D-007?) | M15, reportes parciales |
| 4 | **OEE por máquina/molde** (G4) | MEDIO — número comparable (ISO 22400 parcial) | BAJO — cargar ciclo ideal; el resto sale de 1+2 | G2 |
| 5 | **Molde como entidad + mantención por ciclos** (G5) | MEDIO — previene la falla cara | MEDIO — entidad nueva + contador + OT | M15, motivos |
| 6 | **Merma fina: scrap de arranque + cavidades activas + molido** (G6) | MEDIO | BAJO | V3 |
| 7 | Trazabilidad lote preforma (G7) | BAJO hoy | ALTO | M04 F3, D-005 |

Sugerencia de fases: **F1 = ítems 1+2** (los dos «BAJO esfuerzo/ALTO impacto», cierran
los pendientes históricos del tracker) · **F2 = 3+4** (la meta viva y el número) ·
**F3 = 5+6** (molde y merma fina) · G7 espera a M04-F3/D-005.

## 6. Qué esperar de la vía B (reconciliación)

La vía B (Chrome AI/Cowork) debería: confirmar/desmentir los «no encontrado» de la tabla
(especialmente offline y aprobaciones — son claims negativos), aportar pantallas de demos
que el texto no muestra (¿cómo se VE un kiosk de piso?), y traer sistemas que esta
barrida no vio. Discrepancias se resuelven con la fuente primaria.

## 7. Fuentes por ángulo

Las fichas completas (13 ejes × 14 sistemas + 8 marcos, con URL por dato) están en el
resultado del workflow del Director (sesión 07-ago). Fuentes principales: soporte
oficial de Katana/MRPeasy/Odoo/Fusion/Fishbowl/Tulip, ayuda oficial de
Defontana/Softland/Manager/Random/Flexline, fichas de DELMIAworks/Mattec/PlantStar/
Intouch, y OEE.com, ISO 22400, MachineTracking/Evocon, LeanProduction (SIC),
GlobalReader, Microsoft Learn (backflush). URLs citadas inline en §1-§5.
