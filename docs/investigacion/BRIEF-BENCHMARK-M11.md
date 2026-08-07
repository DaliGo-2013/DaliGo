# Brief de investigación — Benchmark del módulo de Producción (M11)

> Preparado por el Director el 2026-08-07 a pedido del dueño (orden de Luis: retomar
> producción). **Este texto se pega TAL CUAL en la IA de Google Chrome o en un asiento
> de Claude Cowork.** La respuesta vuelve al Director para reconciliarla con su propia
> investigación (vía A) y armar el backlog de la versión final de M11.

---

## TEXTO PARA PEGAR (desde aquí hasta el final)

Eres un investigador de software industrial. Necesito un benchmark de sistemas de
gestión de producción para comparar contra nuestro módulo propio. Contexto de nuestra
empresa y sistema:

**Quiénes somos:** Importadora y fabricante chilena. Nuestra planta SOPLA BOTELLONES DE
PET desde preformas (blow molding): máquinas sopladoras, moldes por tipo de botellón,
operarios llamados «sopladores». Tenemos una app web propia (Laravel + PWA móvil) y
nuestro módulo de producción hoy hace: el jefe asigna una meta diaria a cada soplador
(máquina + tipo de botellón + cantidad), el operario reporta lo producido desde su
celular (funciona sin señal y sincroniza después), indica el motivo si no cuadra con la
meta (faltaron preformas, falla de máquina, mantención, cambio de molde, molde dañado u
otro), el jefe aprueba o devuelve el reporte, y lo aprobado alimenta un kardex interno.
Hay historial por operario (45 días), rendimiento por máquina y por tipo de botellón.
NO tenemos todavía: descuento automático de materia prima (preformas consumidas),
tablero de meta del día en vivo, OEE ni KPIs de planta, registro de paradas de máquina
con duración, mantenimiento preventivo de máquinas/moldes, ni trazabilidad por lote.

**Qué investigar (mínimo 6 sistemas, idealmente 8-10):**
1. MES/MRP livianos para manufactura pequeña-mediana: Katana MRP, MRPeasy, Odoo
   Manufacturing, Autodesk Fusion Operations (ex Prodsmart), Fishbowl Manufacturing,
   Tulip — y cualquier otro relevante que encuentres.
2. Al menos 1-2 ERPs de Chile/LatAm con módulo de producción (Defontana, Softland,
   Manager, u otros).
3. Si encuentras algo específico para plásticos/soplado/inyección PET (aunque sea
   enterprise), inclúyelo: nos interesa qué miden (ciclos, cavidades, scrap).

**Para CADA sistema, entrega una tabla con estos 13 ejes exactos** (si un eje no aplica
o no hay información, escribe «no encontrado» — no lo omitas):

| Eje | Qué anotar |
|---|---|
| 1. Orden/meta de producción | ¿Cómo se modela? ¿Orden de trabajo, meta por turno, por operario, por máquina? |
| 2. Captura en planta | ¿Quién registra, con qué dispositivo (tablet/celular/terminal/sensor)? ¿Funciona offline? |
| 3. Consumo de materia prima | ¿BOM/receta? ¿Descuento automático al reportar? ¿Backflush o manual? |
| 4. Merma / scrap | ¿Cómo se registra? ¿Motivos tipificados? ¿% de scrap visible? |
| 5. Paradas de máquina | ¿Registro de downtime con motivo y duración? ¿Códigos de parada? |
| 6. KPIs | ¿OEE? ¿Rendimiento, disponibilidad, calidad? ¿Ciclos/hora, unidades/turno? |
| 7. Metas y alertas | ¿Meta del día visible en vivo? ¿Alertas por atraso/desviación? |
| 8. Supervisión/aprobación | ¿El supervisor valida los reportes? ¿Flujo de corrección? |
| 9. Trazabilidad | ¿Lotes? ¿Qué lote de materia prima entró en qué producción? |
| 10. Mantenimiento | ¿Preventivo por horas/ciclos de máquina o molde? ¿Avisos? |
| 11. Reportes | ¿Qué informes trae de fábrica (por turno, máquina, operario, producto)? |
| 12. Integración | ¿Se conecta con inventario/ventas/contabilidad? ¿API? |
| 13. Precio y segmento | ¿Cuánto cuesta (USD/mes)? ¿A qué tamaño de empresa apunta? |

**Después de las tablas, cierra con dos síntesis:**
- **«Top 10 transversal»**: las 10 capacidades que MÁS se repiten entre los sistemas
  investigados y que nuestro módulo (descrito arriba) NO tiene.
- **«Ideas no obvias»**: 3-5 cosas que viste en UN solo sistema pero que calzan
  especialmente bien con una planta de soplado PET chica (2-4 máquinas, un turno).

**Formato de salida:** Markdown. En español. Cada tabla precedida por el nombre del
sistema y la URL principal. Cita la URL de la fuente de cada dato no obvio (página de
features, docs, video de demo). Si viste un video/demo con pantallas, describe la
pantalla de captura en planta: qué botones ve el operario.
