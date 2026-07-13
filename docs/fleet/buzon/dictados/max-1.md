# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-13. Este archivo manda sobre instrucciones anteriores.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño usarlo); si no, Opus 4.8 · high.

VEREDICTO PREVIO: P-M14-04 y P-M14-05 [x] VERIFICADOS por el Director (7a01da2 — escalar con
everyFifteenMinutes()+withoutOverlapping(15) y test de grilla ✓, ajustar() cableado con la
batería completa de 10 tests exacta al plan ✓, gotcha del docblock */15 bien cazado). E2·M14
va 5/7. Tu parte formal de 04/05 nunca llegó — escríbelo al buzón (partes/) junto con el de
esta tarea, con /usage si Mauricio te lo pasa.

⚡ ACTUALIZACIÓN 13-07 (v2): P-M14-06 [x] VERIFICADO (parte del buzón leído — impecable:
whereDate con test de borde, sin N+1, 6 tests, preview 3 anchos). E2·M14 = 6/7.

TAREA NUEVA 0 — HOTFIX URGENTE (main está ROJO): el stream M12 cambió el start_url del
manifest a /dashboard (fix legítimo: a los no-sopladores la app les abría con 403 —
rama fix/pwa-start-url-403) pero PwaTest:41 sigue asertando '/produccion/mi-reporte' →
Tests de main en FAILURE desde 2c396b7. ARREGLO (tu territorio, NO toques el cambio de
Marcos): actualiza la aserción del test al nuevo start_url '/dashboard' + evalúa si algún
otro assert del PWA quedó desalineado + suite completa verde + push a main. Anota el gotcha
en la bitácora: "cambio de manifest.json requiere alinear PwaTest — CI de main es la red".
NO implementes redirect por rol todavía (pregunta de producto en manos de Mauricio).

⚡ 13-07 tarde v3 — HOTFIX AUTORIZADO por Mauricio (excepción de territorio M12, puntual):
DIAGNÓSTICO DEL DIRECTOR (causa raíz confirmada, read-only): el test
`ServicioTecnicoManagementTest::test_reparado_exige_diagnostico_final` (lo agregó Marcos en
2d8fd73) es **FLAKY** — por eso el CI alternó verde/rojo entre pushes idénticos de solo-docs.
La factory `OrdenServicioFactory` asigna `estado => fake()->randomElement(OrdenServicio::ESTADOS)`
(ALEATORIO). El test crea la orden sin fijar estado, intenta PUT a 'reparado' con causa_falla=''
(la validación lo RECHAZA bien — no hay update), y luego `assertNotSame('reparado', estado)`
FALLA cuando el estado aleatorio inicial cayó en 'reparado' por azar. El código de Marcos
(validación del descuento/causa_falla) está CORRECTO — el defecto es solo del test.
FIX (mínimo, 1 línea, SOLO ese test): que la orden nazca con estado ≠ 'reparado', igual que
Marcos ya hizo en su test hermano del descuento:
  `OrdenServicio::factory()->create(['facturacion' => 'reparacion', 'estado' => 'en_revision'])`
Revisa de paso `test_sin_solucion_exige_diagnostico_final` (mismo patrón, línea ~691) — si
también crea sin estado fijo, tiene el mismo flaky latente; aplícale el mismo fix.
VERIFICACIÓN OBLIGATORIA (es flaky — una corrida verde puede ser suerte): corre ESE test
20+ veces seguidas (`php artisan test --filter=ServicioTecnico` en bucle, o
`--repeat=20` si tu versión lo soporta) y confirma 20/20 verdes ANTES de pushear. Suite
completa verde. Push a main.
TERRITORIO: tocas SOLO ese(os) test(s), NO el controller ni la factory (arreglar la factory
para no-aleatoria es refactor de M12 que le toca a Marcos — no lo hagas). Anota en la bitácora
el gotcha: "factory con estado aleatorio → tests que asertan estado deben FIJARLO en el create".
Deja constancia en tu parte de que tocaste territorio M12 por autorización del dueño (I-06).

TAREA SIGUIENTE — P-M14-07 (con main verde): re-sellado de PLAN-M14 (guard omitido +
eventos en 02) + suite completa + gate /pre-merge (R-31) + MERGE COORDINADO: parte al buzón
con hash del merge EN TU RAMA + grep + conteo ANTES de pushear a main; push solo con doble
llave (Director + Mauricio) → deploy → QA staging desde el celular de Mauricio (ajuste
grande de producción → campanita+correo → aprobar desde el teléfono → aplicado).

TAREA ANTERIOR (ya cumplida, se conserva por contexto): P-M14-06 · Historial de aprobaciones
- /admin/aprobaciones (permiso 'view aprobaciones'): filtros estado/tipo/solicitante/
  aprobador/rango de fechas (whereDate, NUNCA whereBetween — bitácora) + resumen por
  aprobador/solicitante. Componentes x-* del catálogo, paginación con withQueryString.
- «Mis solicitudes» del lado del solicitante ya existe (P-M14-03) — enlázala desde donde
  corresponda si falta.
- Transiciones visibles en /admin/auditoria (los modelos ya están en MODELOS).
- Responsive 3 anchos, build + grep del bundle, suite verde, RUTA mismo push.

DESPUÉS (no arranques sin dictado nuevo): P-M14-07 = re-sellado de PLAN-M14 (anotar guard
omitido + eventos en 02) + suite completa + gate /pre-merge + MERGE COORDINADO con doble
llave (Director + Mauricio) + QA staging desde celular real.

CIERRE: parte a docs/fleet/buzon/partes/2026-07-13--max-1--p-m14-06.md + push.
