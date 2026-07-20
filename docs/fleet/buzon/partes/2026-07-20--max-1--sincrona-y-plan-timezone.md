# Parte — Max-1 · Dictado v17 ejecutado: #9 campanita síncrona LISTA + PLAN-TIMEZONE sellado

> De: Max-1 · Para: Director / Mauricio

## 1. #9 Campanita síncrona — rama `fix/campanita-sincrona` @ `13de78b` (espera doble llave)

- **El cambio (quirúrgico):** en el dispatcher, el canal `database` nace **ENVIADA** con
  `enviada_at` y NO se encola (sin transporte externo no hay nada que "enviar") — la
  campanita cuenta la notificación **al tiro**, adiós latencia ≤15 min. `mail`/`whatsapp`
  siguen por la cola exactamente igual.
- **Lo que el dictado pedía validar, validado:**
  - Badge server-rendered: intacto (cuenta ENVIADA — ahora disponible antes).
  - Resto de la cola: solo cambia qué NO entra (database); el job **conserva su rama
    database** para filas pendientes previas al deploy (ventana de migración cubierta con
    test propio `test_el_job_aun_procesa_una_database_pendiente_legada`).
  - Claim atómico del reintentador: intacto — reclama fallidas `whereNotNull(programada_para)`
    y huérfanas pendientes; las database ya no pisan ese estado.
- **Deuda de doctrina PAGADA en el mismo lote** (el dictado decía "cuando vuelvas a tocar
  campanita" — esto ES tocar campanita): `CampanitaTest:97` migrado de `'>3<'` al marcador
  accesible `'Notificaciones (3 sin leer)'` (sr-only + aria-label), positivo Y negativo.
- **Suite completa: 653 verdes (2.092 aserciones), exit 0.** Sin Blade tocado → sin build
  (el bundle vigente de main sigue).

## 2. PLAN-TIMEZONE sellado (`docs/planes/PLAN-TIMEZONE.md`, main) — GATE abierto

**El análisis probó "lo contrario" que el dictado invitaba a probar, pero en una dirección
inesperada:** el síntoma del QA (timestamps +4h) es el MENOR de dos problemas.

- **P1 (el reportado):** 8 formatos absolutos muestran +4h → lo arregla la capa RENDER
  (macro central). Los 4 `diffForHumans` ya son correctos — no se tocan.
- **P2 (destapado por el inventario, NO reportado):** el "hoy" del servidor corre en UTC —
  **desde las 20:00/21:00 Chile el soplador no ve sus producciones, la cola del jefe se
  vacía, el pulso marca ceros, y la visita industrial pública RECHAZA al cliente que pide
  su "hoy"**. Render-only NO lo arregla; y el flip de `app.timezone` lo arregla pagando
  caro (no corrige lo histórico en pantalla, ROMPE los diffForHumans históricos —
  «dentro de 4 horas» — y corre backoff/escalamiento en el cutover; migrar datos es
  inviable barato: CONVERT_TZ exige tablas tz y el offset fijo rompe con DST).
- **Recomendación (opción C, `app.timezone` NO se toca):** dos capas quirúrgicas —
  helper `FechaNegocio::hoy()` (America/Santiago) en ~20 sitios de superficie operativa +
  macro de render para los 8 formatos. Motor/grilla/colas: CERO cambios (verificado: todas
  las tareas son por-minuto, deltas puros, `available_at` unix — la grilla no se mueve con
  ninguna opción).
- **Método y calidad:** 3 exploradores (inventario archivo:línea) + 2 refutadores — las
  afirmaciones técnicas SIN hallazgos; el inventario ganó **5 familias faltantes** por la
  refutación (validación `after_or_equal:today`, prefills `format('Y-m-d')`, `isToday()`,
  cabeceras `translatedFormat`, fallback del scope) — ya incorporadas y el gate del futuro
  lote greppea la familia completa, no un solo patrón.
- Pasos propuestos: P-TZ-01 (día de negocio, M/L) · P-TZ-02 (render, S) · P-TZ-03 (gate+QA
  de borde ~21:30 con el dueño). Partibles: si el Director prefiere solo P1 primero, son
  independientes.

**GATE: visto bueno del Director + dueño antes de una línea de código** (como dictó v17).

## Recordatorios de estado

- #6 chips paramétricos: NO arrancado (espera dimensionamiento Director+dueño, como se dictó).
- Nota v17 sobre `st-industrial-kpis` de Marcos sin mergear: mi lote no toca Blade → sin
  riesgo de bundle en este merge.

— Max-1
