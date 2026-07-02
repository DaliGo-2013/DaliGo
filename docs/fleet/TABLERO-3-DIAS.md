# TABLERO — 3 días de flota (2026-07-03 → 2026-07-05)

> Lo opera el DIRECTOR (única cuenta que edita este archivo). Marcas: `[ ]` pendiente,
> `[~]` en curso, `[x]` verificada con evidencia, `[!]` bloqueada. Cada tarea indica la
> talla estimada (S/M/L/XL — ver `CONSUMO.md`) y el dictado modelo+esfuerzo.

## Día 1 (2026-07-03)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-1 | [x] **P-SPK-01** · Spike PWA: manifest + service worker sobre `mi-reporte` (instalable, cache de assets, detección online/offline) — *VERIFICADO por Director 02-07: `ee01204` (23 archivos), RUTA-MAESTRA [x] mismo push, PwaTest 4 tests, `public/build/` ✓, bitácora ✓, manifest VIVO en staging verificado independiente (scope "/", standalone, 4 iconos), e instalación en celular real CONFIRMADA por Mauricio (captura 02-07). Diseño P-SPK-02 anotado: iOS sin Background Sync → cola se vacía en evento 'online' + carga de página* | L | Fable 5 · xhigh (diseño) → Opus 4.8 · high (impl.) | Instalable en un celular real; assets cachean; indicador online/offline funciona; tests verdes |
| Max-2 | [x] `docs/planes/PLAN-M15.md` con sello de vigencia → visto bueno → **P-M15-01** (migraciones) y **P-M15-02** (Dispatcher + canales) — *VERIFICADO por Director 02-07: plan `01f219a` aprobado (Max-1, 2 ajustes); GATE cumplido en `ff27f40` (plan ajustado + 2 migraciones MISMO push; `Notificacion` sin audit con comentario, `PreferenciaCanal` auditable, test guard línea 244); `771f278` dispatcher+3 canales+job+14 tests (valida catálogo EVENTOS); unique compuesto con evento a 100 chars (prefijo 767 utf8mb4); territorio limpio (0 archivos de producción, 0 compartidos tocados); main intacto (`35e72db`); RUTA-MAESTRA marcada en el mismo push. Suite 378 declarada (no ejecutable por Director; 2ª revisión QA día 2)* | L | Opus 4.8 · high | Plan aprobado; migraciones y servicios con tests en la rama |
| Director | [x] Constituirse (leer kickoff + docs), validar PLAN-M15, abrir ledger día 1, dictar modelo/esfuerzo a cada cuenta — *hecho 02-07: docs leídos completos; PLAN-M15 validado con spot-checks del sello contra clon fresco (queue=database, seeder aditivo, nav lg:flex, 4 syncs sin queue:work, User Notifiable — todo coincide); ledger abierto; dictado día 1 entregado a Mauricio* | M | Sonnet 5 · high | Tablero y ledger al día; dictados entregados a Mauricio |
| QA | [ ] Guion de regresión local de M11 (flujo completo con pasos exactos) + primera revisión adversarial del diff de la rama M15 (`git fetch && git diff origin/main...origin/feature/m15-notificaciones`) | M | Sonnet 5 · high | Guion entregado + hallazgos del diff con file:línea (o "sin hallazgos") |
| Investigador | [ ] Matriz D-002 pre-llenada módulo×rol (leer `RolesAndPermissionsSeeder` + `config/permissions.php` + biblia §7) lista para enviar a Luis + prompt INVESTIGACION-DECISION para P-S0-10 (44 errores sync-clients) | M | Sonnet 5 · medium | Ambos textos listos para copy/paste de Mauricio |
| Escriba | [ ] Refresh de `docs/GUIA-DALIGO.md` (drifts conocidos: sync ya corre por cron; seed también a nivel workflow) | S | Haiku 4.5 · low | Diff solo de ese archivo; entregado al Director para commit vía su parte |

## Día 2 (2026-07-04)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-1 | [~] **P-SPK-02** · Cola IndexedDB para `registroStore` offline con idempotencia (UUID cliente) — *arrancada 02-07 (adelantada al día 2 con OK del dueño). OJO consumo: sesión al 86% al arrancar; ventana resetea 13:30 — si corta, parte PARCIAL y se retoma* | XL | Opus 4.8 · xhigh | Tanda creada offline se sincroniza al volver la señal, sin duplicados ante reintento; tests |
| Max-2 | [~] **P-M15-03** (cola database + redactar prompt del cron `queue:work` para IA-cPanel) + **P-M15-04** (plantillas por evento + seeds) — *P-M15-04 arrancada 02-07 con go del Director y aprobación de Mauricio; P-M15-03: el prompt de delegación pasa por revisión del Director ANTES del despacho* | L | Opus 4.8 · high | Prompt de delegación entregado a Mauricio; seeds idempotentes; tests |
| Director | [ ] Verificación papel↔código de día 1 (commits vs tablero), ledger día 2, replanificar si algo se atrasó | M | Sonnet 5 · high | Tablero actualizado con evidencia por tarea |
| QA | [ ] Ejecutar guion de regresión M11 en local + probar el spike PWA con modo avión (checklist del Forjador A) + segunda revisión del diff M15 | M | Sonnet 5 · high | Partes de QA con veredicto por flujo |
| Investigador | [ ] D-007: opciones reales 2026 de WhatsApp Business API (Meta directo vs BSP, costos/plazos CL) + consolidar paquete D-005 para Víctor (huecos BSALE_API + sandbox + bodegas) | M | Sonnet 5 · medium | Ficha D-007 con opciones/costos citados + email listo para Víctor |
| Escriba | [ ] Borrador `docs/manuales/BORRADOR-soplador.md` (1 página, nombre del sistema como placeholder por D-001) | S | Haiku 4.5 · low | Página lista siguiendo el flujo real de mi-reporte |

## Día 3 (2026-07-05)

| Cuenta | Tarea | Talla | Modelo·Esfuerzo | Hecho cuando |
|---|---|---|---|---|
| Max-1 | [ ] **P-SPK-03** · Prueba de campo (modo avión, matar app a mitad de cola) + memo `docs/SPIKE-PWA.md` con la arquitectura elegida para M08 | L | Opus 4.8 · high (memo con Fable 5) | Memo sellado; RUTA-MAESTRA marca P-SPK-01..03 |
| Max-2 | [ ] **P-M15-05** (reintentos backoff + vista `/admin/notificaciones`) + **P-M15-06** (campanita nav, cambio mínimo, build + grep del bundle) | L | Opus 4.8 · high | Vistas funcionando en su local; suite verde en la rama |
| Director | [ ] Informe ejecutivo de los 3 días para Mauricio (avance, consumo real por cuenta, tabla de tallas calibrada) + tablero de los próximos 3 días (propuesta) | M | Sonnet 5 · high | Informe entregado; CONSUMO.md con la tabla calibrada |
| QA | [ ] QA integral del spike (guion adversarial: doble envío, foto grande, matar app) + guion QA-FUNCIONAL-STAGING para M15 (se usará post-merge) | M | Sonnet 5 · high | Ambos guiones entregados; hallazgos del spike reportados a Max-1 vía Director |
| Investigador | [ ] Consolidado: estado de las 10 decisiones D-0xx con lo investigado (qué falta de quién) — insumo del informe del Director | S | Sonnet 5 · medium | Tabla actualizada entregada |
| Escriba | [ ] Borrador `docs/manuales/BORRADOR-jefe-bodega.md` (1 página: asignar → cola → aprobar → kardex) | S | Haiku 4.5 · low | Página lista |

## Reglas del tablero

1. Solo el DIRECTOR edita este archivo. Las demás cuentas leen su columna y entregan el parte
   de cierre (formato en `FLOTA.md` §5).
2. Una tarea pasa a `[x]` únicamente con evidencia (commit/archivo/texto) revisada por el Director.
3. Si una cuenta agota su ventana de 5h a mitad de tarea: parte de cierre PARCIAL + el Director
   reasigna o reprograma (por eso las tallas L/XL viven en las cuentas Max).
4. Los merges a `main` de la rama M15 NO ocurren en estos 3 días (siguen el plan de E1).
5. **Directiva del dueño (02-07):** los días del tablero son referencia, no camisa de fuerza —
   lo que importa es completar las tareas EN ORDEN dentro de cada columna. Si una cuenta
   termina su día, avanza a la siguiente tarea de su columna sin esperar la fecha.
