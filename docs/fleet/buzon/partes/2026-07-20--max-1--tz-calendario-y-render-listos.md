# Parte — Max-1: dictado v20 COMPLETO (fix calendario + P-TZ-02 render) · 2 ramas esperan doble llave

> De Max-1 (Forjador A) al Director · 2026-07-20. Un parte por los DOS lotes (el dictado lo
> permitía si ambos cerraban en la sesión). Ramas SEPARADAS como se ordenó.

## Tarea 1 — `fix/tz-calendario-agenda` @ `d315a09` (excepción de territorio, quirúrgica)

- **Controller** (`AgendaTrabajoController::calendario`): defaults año/mes →
  `FechaNegocio::ahora()`; día seleccionado default derivado de `FechaNegocio::hoy()`
  (Carbon::parse del Y-m-d de negocio = medianoche, equivalente al `startOfDay()` viejo).
- **Blade**: los **DOS** `isToday()` del archivo → `FechaNegocio::esHoy(...)`. El dictado
  listaba el resalte de la grilla (`$d->isToday()`, línea 65); su **gemelo `· HOY` de la
  cabecera del día** (`$diaSel->isToday()`, línea 78, mismo archivo) es la misma familia y el
  mismo síntoma que motiva la excepción — dejarlo habría dejado la pantalla contradictoria
  consigo misma (grilla bien, cabecera marcando mañana). Mismo criterio del precedente
  P-TZ-01 (sitios extra de la misma familia, documentados). **Nada más del calendario se
  tocó** (grupos, horas, rutas: intactos).
- **Test de frontera** (caso nuevo en `FechaNegocioTest`, 8 aserciones): a las 23:00 de Chile
  el calendario abre con `diaSel` = día chileno, el botón «Agendar trabajo» lleva
  `fecha=2026-07-20` (marcador por ruta), la cabecera dice «· HOY», y el resalte se verifica
  **scoped a la celda** (regex sobre el `<a>` de cada día: la del día chileno CON
  `font-bold text-brand-600`, la del día UTC SIN él — anti verde-engañoso página-completa).
  Además **frontera de MES**: 31-07 22:00 Chile (= 01-08 UTC) abre JULIO con el 31
  seleccionado, no agosto-UTC.
- **Mutación verificada ROJA** (doctrina de la bitácora): revertí temporalmente el sitio de
  la grilla a `isToday()` → el test falló; restaurado desde git. (De paso cacé un gotcha de
  tooling: editar el Blade vía PowerShell 5.1 corrompe el UTF-8 — entrada nueva en la
  bitácora, viaja en la rama de la Tarea 2.)
- Suite completa **679 verdes (2.182 aserciones)**. Bundle `app-C7iXzaRH.css`, superset
  **18/18** (los 14 de la flota + M12 seguimiento + `min-h-14`/`font-bold`/`text-brand-600`/
  `min-w-5` del calendario).

## Tarea 2 — `fix/tz-render` @ `5a88330` (P-TZ-02, capa render)

- **Macro** `Carbon::macro('enChile')` en `AppServiceProvider::boot()` (donde lo ordenó el
  dictado) → `->copy()->tz(config('daligo.tz_negocio'))`. El `copy()` importa: Carbon es
  mutable y un `->tz()` directo movería también lo que se calcule después sobre ese instante.
- **Los 8 formatos absolutos de §1b** convertidos: historial de aprobaciones (admin
  creada+resuelta, «Mis solicitudes»), auditoría, bandeja admin de notificaciones
  (`enviada_at` y su fallback `created_at`), tandas (mi-reporte ×2 + reporte del jefe) **+**
  el `now()->format` del payload de prueba de `NotificacionController`.
- **Lo que NO se tocó, a propósito**: los 4 `diffForHumans` (un delta no depende del tz de
  render); los `format('d-m-Y')` sobre **casts `date` puros** (convertirlos retrocedería el
  DÍA: su valor es medianoche UTC); los nombres de archivo CSV (`Ymd_His`) y el motor.
- **`RenderEnChileTest` (5 tests, 12 aserciones)**: conversión conocida del QA
  (15:45 UTC → **11:45** invierno), DST de verano (enero → **-3**), dos superficies E2E
  (historial y bandeja: `assertSee('11:45')` + `assertDontSee('15:45')` — el par positivo/
  negativo sobre el mismo instante es inmune al verde-engañoso), y relativos intactos
  comparando contra el `diffForHumans()` computado (independiente del locale del entorno).
- Suite completa **686 verdes (2.196 aserciones)**. Bundle regenerado: **mismo hash**
  `app-C7iXzaRH.css` (cero clases CSS nuevas — el macro es PHP), superset 18/18.

## Para la doble llave (nota de orden)

Ambas ramas traen el **mismo bundle idéntico** (`app-C7iXzaRH.css`, construido desde
`b4f0019` y `ca636b1` respectivamente con hash igual — prueba de que el lote-ingreso de
Marcos tampoco agregó clases). El primer merge entra limpio; el segundo no debería
conflictuar ni en el CSS (mismo contenido) ni en el manifest (misma entrada) — si main
avanza con OTRO build antes, rige el protocolo de siempre: regenerar, jamás resolver a mano.

Tras la doble llave: **P-TZ-03** (QA de borde) lo corre el dueño ~21:30 Chile — con estos
dos lotes ya puede verificar además que el calendario resalte el día correcto y que el
historial muestre la hora chilena en filas nuevas (las históricas conservan sus dígitos,
limitación aceptada del plan). #6 chips sigue en manos del Director.
