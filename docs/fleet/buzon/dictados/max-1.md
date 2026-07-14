# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-14 (v11 — M16-v1: INVESTIGACIÓN de dashboard de verdad, orden directa del dueño). Manda sobre lo anterior.

MODELO: Fable 5 se justifica aquí (investigación + diseño, el fuerte del modelo); si el dueño
prefiere ahorrar, Opus 4.8 · high. El dueño fija el selector.

## Contexto: veredicto del dueño sobre M16-v0 (QA visual staging 14-07, capturas al Director)
Funciona técnicamente (teléfono OK, números reales, cards clickeables) PERO **"el dashboard es
un despropósito: solo son accesos directos a distintos apartados"**. Crítica del Director al
detalle (de las capturas): contadores crudos sin contexto ni acción — "2.517 Entregado" (¿de
cuándo?, ¿histórico total?), "0 Producido / 0 Avance %" sin meta ni comparación, "2.857
Productos sin medidas" (backlog eterno que nadie va a limpiar hoy), "215 Equipos en taller"
sin tendencia ni antigüedad. Un buen número sin pregunta-que-responde es ruido. v0 cumplió
como agrupador de accesos; v1 tiene que ser una herramienta de DECISIÓN.

## TAREA M16-v1 (fase 1: SOLO investigación + propuesta — NADA de código todavía)

### 1. Investiga en internet (WebSearch/WebFetch) el propósito de un buen dashboard
Fuentes serias (Nielsen Norman Group, Stephen Few, material de dashboard design/UX de
gestión de operaciones). Extrae los principios con cita: qué pregunta responde cada vista,
glanceable (regla de los 5 segundos), accionable vs decorativo, jerarquía (lo crítico
arriba), tendencia > snapshot, metas/umbrales como contexto, alertas por excepción (mostrar
solo lo que se desvía), rol-céntrico (el admin no necesita lo mismo que el jefe de bodega).

### 2. Audita el dashboard actual contra esos principios
Card por card: ¿qué pregunta responde? ¿qué acción gatilla? ¿tiene contexto (meta/tendencia/
periodo)? Clasifica: decisión / navegación / ruido.

### 3. Mapea qué opciones aplican a DaliGo con los DATOS QUE YA EXISTEN
Inventario honesto de fuentes: producción (reportes/tandas/kardex, con fechas → tendencias
7/30 días ya calculadas en ProduccionController), servicio técnico (estados, antigüedad de
órdenes en taller, flujo entradas/salidas por semana), aprobaciones M14 (pendientes + tiempo
de espera), notificaciones M15 (fallidas), espejo Bsale (productos/clientes/precios/stock) y
**NUEVO: documentos de venta espejados** (P-DSP-01 de Max-2, en rama — ventas por día/zona
llegan con DESPACHOS; márcalo como "post-merge despachos"). Nada de datos que no tenemos.

### 4. Entregable: PLAN-M16-V1 sellado con 2-3 opciones de diseño + recomendación
- Cada opción: qué preguntas responde, para qué rol, mockup en texto/estructura de la
  jerarquía (crítico arriba → tendencias → accesos abajo como zócalo), costo estimado.
- Restricciones duras: paleta 4 colores (tendencias/barras con brand/neutral como el panel
  de producción — mini-barras `style="width:%"` ya probadas), mobile-first 375/768/1024,
  sin librerías de charts nuevas (CSS puro o SVG inline liviano; el hosting no tiene Node y
  el bundle no se infla), queries agregadas sin N+1, MySQL 5.7.
- Los accesos directos actuales NO se botan: bajan a un zócalo compacto al final.
- GATE: visto bueno de Mauricio sobre el plan ANTES de la primera línea de código (v0 nos
  enseñó: construir sin validar el propósito = retrabajo).

Parte al buzón con el plan sellado. El Director lo valida y se lo presenta al dueño.

## Pendiente vivo (no bloquea)
P-M14-07: el dueño probó staging en teléfono 14-07 y "funciona todo bien" — el Director le
pidió confirmar si ese QA incluyó el flujo de aprobación M14 (ajuste ≥50 → campanita →
aprobar desde el teléfono). Si lo confirma, marca P-M14-07 [x] + sello en RUTA.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
