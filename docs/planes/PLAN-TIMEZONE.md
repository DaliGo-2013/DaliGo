# PLAN-TIMEZONE · UTC → hora chilena SIN romper el motor — análisis y propuesta
> **Estado: PROPUESTA (solo plan, CERO código) — sellado contra el código el 2026-07-20 (main @ `0e85281`)**
> **GATE: visto bueno del Director + dueño ANTES de una línea de código** (dictado v17).

> **Origen:** hallazgo #2 del QA 15-07 — el historial mostraba 15:45 cuando en Chile eran
> 11:45 (+4h). `app.timezone` es `UTC` (default Laravel jamás tocado, `config/app.php:68`).
> **Lo que destapó el análisis:** el síntoma reportado es el MENOR de los dos problemas.
> **Método:** inventario por 3 exploradores paralelos (archivo:línea verificado) + refutación
> adversarial de 2 lentes — las afirmaciones técnicas (grilla inmune, deltas puros, casts,
> whereDate) quedaron SIN hallazgos; el inventario ganó 5 familias faltantes que ya están
> incorporadas en §1a (marcadas «cazado por refutación»).

## 0. Síntesis ejecutiva (la decisión en 1 minuto)

Hay **dos problemas distintos** enredados en "la hora está mala":

| Problema | Quién lo sufre | Lo arregla render-only | Lo arregla flip de `app.timezone` |
|---|---|---|---|
| **P1 · Timestamps absolutos +4h** (el del QA: "15:45") | Quien lee historiales/auditoría | ✅ sí | ⚠️ solo filas NUEVAS — las históricas siguen mostrando los mismos dígitos, y los `diffForHumans` históricos se ROMPEN («dentro de 4 horas» en la campanita) |
| **P2 · El "hoy" del servidor corre en UTC** (destapado por este inventario, NO reportado): desde las **20:00 Chile (invierno) / 21:00 (verano)**, hoy-UTC ya es MAÑANA → el soplador nocturno NO VE sus producciones del día, la cola del jefe se vacía, las alertas de atrasados callan, el pulso del dashboard marca ceros, el prefill de asignar ofrece la fecha de mañana y un ingreso QR nocturno queda fechado mañana | **Toda la operación en tarde-noche** — y la planta trabaja turno noche | ❌ no | ✅ sí, pero pagando la ventana de transición (abajo) |

**Recomendación de Max-1 — opción C, dos capas quirúrgicas, `app.timezone` NO SE TOCA:**
1. **Capa "día de negocio"** (arregla P2): helper central `FechaNegocio::hoy()` =
   `now('America/Santiago')->toDateString()`, reemplazando los **~20 sitios** de superficie
   operativa (no solo `toDateString()`: también los prefills `format('Y-m-d')`, los
   `isToday()`, las cabeceras `translatedFormat` de "hoy", la validación `after_or_equal:today`
   y el fallback del scope `delDia()` — ver §1a, completada por refutación adversarial).
   Storage intacto, motor intacto, cero ventana de transición.
2. **Capa render** (arregla P1): macro central `Carbon::macro('enChile')` (→
   `->tz('America/Santiago')`) aplicado a los **8 formatos absolutos** inventariados.
   Los 4 `diffForHumans` NO se tocan (los diffs relativos ya son correctos).

Esto **valida a medias** la recomendación preliminar del Director (render-only): correcta
para P1, insuficiente para P2 — y el análisis "prueba lo contrario" solo en eso: no hace
falta flipear `app.timezone` (opción A) para arreglar P2; basta el helper de día de negocio.

## 1. Inventario (verificado archivo:línea; detalle completo en las notas de cada franja)

### 1a. Superficies de "hoy" (P2) — cambiarían de comportamiento, y ESO es lo deseado

| Superficie | Evidencia | Riesgo hoy |
|---|---|---|
| Dashboard: `$hoy = now()->toDateString()` gobierna excepciones + pulso | `DashboardController.php:31` (whereDate en :63/:133/:138) | ALTO — pulso en ceros y alertas falsas en la noche chilena |
| Panel del jefe: cola `delDia`, atrasados, pendientesOtrosDias, resumen | `Admin/ProduccionController.php:33` (:38/:48/:63/:69-74) | ALTO |
| **Pantalla del soplador**: «mis producciones de hoy» | `Produccion/MiProduccionController.php:28` (:31/:41) | **ALTO — a las 22:00 Chile el turno noche NO VE su producción** |
| Prefill de asignar (fecha default del form) | `admin/produccion/asignar.blade.php:38` | MEDIO — a las 21:30 ofrece MAÑANA |
| **QR público: `fecha_ingreso` y entrega estimada SE PERSISTEN desde now()** | `Publico/IngresoTallerPublicoController.php` (2 puntos) | MEDIO — ingreso nocturno queda fechado mañana (dato de negocio ya guardado mal) |
| Aging taller / serie 7 días / rangos default / drill-down día / historial mes | `DashboardController.php:142/180`, `ProduccionController.php:117/257/354` | MEDIO/BAJO — solo el borde se corre |
| **Visita industrial pública: `after_or_equal:today` + `min=` del input** — de noche chilena RECHAZA al cliente que pide visita para su "hoy" | `Publico/VisitaIndustrialPublicoController.php:65`, `publico/taller/create-visita.blade.php:71` | ALTO — rechazo real al usuario (cazado por refutación) |
| **Prefills `format('Y-m-d')` que se PERSISTEN**: fecha_ingreso de ST (form + lote) e instalaciones — misma clase que el prefill de asignar | `admin/servicio-tecnico/_form.blade.php:69`, `servicio-tecnico/lote/create.blade.php:109`, `admin/instalaciones/_form.blade.php:11` | MEDIO (cazados por refutación — el grep de `toDateString` era ciego a esta variante) |
| **`isToday()` ×5**: el «· HOY» de la agenda de terreno y el «Hoy llevas» del soplador marcan el día equivocado de noche | `admin/agenda-terreno/index.blade.php:87-90`, `produccion/mi-reporte.blade.php:181/259` | MEDIO (cazados por refutación) |
| **Cabeceras `now()->translatedFormat('l d de F')`** ×3: el título del día en las pantallas de producción — a las 22:00 el soplador vería el título de MAÑANA sobre la lista corregida de hoy | `produccion/mis-producciones.blade.php:8`, `admin/produccion/index.blade.php:60`, `produccion/mi-reporte.blade.php:11` | MEDIO (cazados por refutación) |
| Menores: campo fecha readonly del QR público (`publico/taller/create.blade.php:170`), mes default de la agenda (`AgendaTrabajoController.php:34`), **fallback UTC latente del scope `delDia()`** (`ProduccionReporte.php:262` — hoy sin callers, pero P-TZ-01 debe cambiarlo DENTRO del scope), nombres de CSV con hora UTC (trivial, se acepta) | (refutación) | BAJO |
| Columnas DATE de negocio comparadas entre sí (asignar del form, kardex, filtros explícitos) | `ProduccionController.php:431/586` | NULO — fecha humana contra fecha humana, inmune |

### 1b. Superficies de render (P1)

- **8 formatos absolutos** (`format('d-m-Y H:i')` / `H:i`) que hoy muestran +4h: bandeja
  admin de notificaciones, historial de aprobaciones (admin y «Mis solicitudes»), auditoría,
  tandas de producción (mi-reporte y reporte del jefe) + 1 `now()->format` en
  `NotificacionController` (payload de prueba).
- **4 relativos** (`diffForHumans`: campanita, bandejas, «resuelta hace X») — **ya correctos**
  (un delta entre dos instantes no depende del tz de render). NO tocar.
- **No existe ningún helper central** (cero `serializeDate`, cero macros, cero `->tz()` en
  todo el código): cada Blade formatea inline — por eso la capa render se hace con UN macro.
- Casts `date` puros (fecha, fecha_ingreso…): su render no se desplaza. Inmunes.

### 1c. Scheduler, colas y motor — inmunes en la opción C (cero cambios)

- **La grilla `*/15` no se mueve con NINGUNA opción**: todas las tareas son por-minuto
  (`hourly/hourlyAt/everyFifteenMinutes`, cero `dailyAt`), y CDT (server), UTC y Santiago
  difieren en horas ENTERAS — los minutos :00/:15/:30/:45 coinciden, incluso a través del
  DST chileno (salta horas completas).
- Comparaciones del motor = **deltas puros** (`subMinutes`/`addMinutes` con `now()` en ambos
  lados): escalamiento, backoff, reclamo de huérfanas, aging — inmunes si el storage no cambia.
- Cola database: `available_at` es unix integer — inmune.
- Cero usos de `config('app.timezone')`/`->timezone()` en el código: nadie compensa hoy.

## 2. Por qué NO la opción A (flip de `app.timezone` a America/Santiago)

1. **No arregla P1 hacia atrás**: MySQL 5.7 guarda datetimes *naive* — el flip reinterpreta
   las filas históricas como hora chilena sin convertirlas: los dígitos en pantalla no
   cambian, y los `diffForHumans` históricos quedan 4h EN EL FUTURO («dentro de 4 horas»).
2. **Ventana de transición real**: por la misma reinterpretación, durante el cutover las
   edades se subestiman ~4h y los `programada_para` de reintentos se corren hasta +4h
   (una vez, decae solo — pero es el motor de notificaciones en producción).
3. **Migrar los datos no es viable barato**: `CONVERT_TZ` con zonas nombradas exige las
   tablas tz de MySQL (no confiables en hosting compartido) y un offset fijo rompe con DST.
4. Cambia el semántico de TODOS los `now()` persistidos de una vez (big-bang), contra el
   cambio quirúrgico por capas de la opción C.

## 3. Matriz de riesgo (opción C)

| Área | Riesgo | Mitigación |
|---|---|---|
| Helper día-de-negocio con tz fija | DST chileno lo resuelve tzdata de PHP; el string `America/Santiago` vive en UN lugar (config `daligo.tz_negocio` o constante del helper) | Test de frontera: freeze 23:00 Chile / 03:00 UTC |
| Filtros `whereDate` sobre `created_at` (historial aprobaciones, `AprobacionController::historial`) | Siguen filtrando por día-UTC (±4h en el borde del día) — `whereDate` extrae el día del string guardado y convertirlo requeriría `CONVERT_TZ` (ver §2.3) | **Limitación documentada y aceptada en v1** (imprecisión solo en el borde 20:00-24:00 para filtros de fecha del historial) |
| Blades con superficie de fecha olvidados | El inventario lista todas las familias conocidas; la refutación demostró que un solo patrón de grep es ciego | Gate del lote greppea la FAMILIA completa: `toDateString\|format('Y-m-d\|format('d-m\|isToday\|translatedFormat\|after_or_equal:today\|diffForHumans` |
| Tests existentes | `freezeTime`/travel son relativos; columnas date puras | Correr suite entera (regla de la casa) |
| Móvil/PWA del operario | El "hoy" que ve el soplador POR FIN calza con su reloj | Es el objetivo |

## 4. Batería de tests propuesta (se construye CON el lote, si se aprueba)

1. **Frontera nocturna** (la joya): freeze a las 23:00 América/Santiago (= 03:00 UTC del día
   siguiente) → el soplador VE su producción del día chileno (y el título/`isToday` dicen
   «hoy»); la cola del jefe la lista; el pulso no está en ceros; el prefill de asignar ofrece
   el día chileno; **la visita industrial pública ACEPTA `fecha_preferida` = hoy chileno**.
2. QR nocturno: ingreso a las 23:00 Chile → `fecha_ingreso` = día chileno.
3. Render: macro `enChile` convierte un instante conocido (UTC 15:45 → 11:45 invierno) y
   respeta DST (fecha de verano → -3).
4. Relativos intactos: `diffForHumans` de una notificación reciente sigue diciendo «hace N min».
5. Grilla: el test existente de `ScheduleBsaleTest`/`AprobacionesEscalarTest` no cambia (la
   grilla no se mueve — asserts intactos = prueba de no-regresión).
6. Historial: filtro de fecha documentado como día-UTC (test que FIJA la limitación aceptada).

## 5. Pasos si se aprueba la opción C (talla M total)

| Paso | Entregable | Talla |
|---|---|---|
| P-TZ-01 | Helper `FechaNegocio::hoy()` (+`esHoy()`/`ahora()`, config del tz) + reemplazo de los **~20 sitios** de §1a (incl. validación `today`, `isToday`, prefills, cabeceras, fallback del scope) + tests de frontera nocturna | M/L |
| P-TZ-02 | Macro de render `enChile` + los 8 formatos absolutos de §1b + tests de render/DST | S |
| P-TZ-03 | Gate R-31 + merge doble llave + QA de borde con el dueño (abrir la app ~21:30 Chile y ver el día CORRECTO) | S |

## 6. GATE

**Cero código hasta el visto bueno del Director + dueño sobre la opción** (C recomendada;
A descartada con evidencia §2; B sola deja vivo el bug del turno noche). Si el Director
prefiere partir SOLO por P1 (render, riesgo mínimo) y dejar P2 para su propio lote, la
opción C se parte limpia: P-TZ-02 primero, P-TZ-01 después — son independientes.
