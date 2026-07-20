# Backlog priorizado — 12 hallazgos del QA guiado 15-07 (M14 aprobaciones + M15 campanita)
> Triaje del Director (verificación adversarial + análisis, 17-07). Fuente: acta
> `partes/2026-07-15--max-1--acta-qa-m14-m15.md`. Ninguno bloqueó el QA — E2·M14 CERRADA.
> Los fixes de comportamiento #1/#3/#7 ya están en la rama `fix/qa-aprobaciones-ux` (ver
> nota de VERIFICACIÓN abajo — el lote tiene 2 bloqueadores de entrega, no de diseño).

## Próximo lote S (rama nueva, tras mergear el lote actual)
| Id | Título | Territorio | Prio | Recomendación |
|---|---|---|---|---|
| **#5** | Filas de notificación de la bandeja NO son accionables (sin link al destino) | cross (M14/M15) | **alta** | Cabeza del lote. Fila clickeable al destino por evento (`aprobacion.*` → bandeja del aprobador / mis-solicitudes del solicitante). El modelo ya carga `notificable`+`payload`. Cierra la regla de bitácora "toda alerta necesita superficie donde actuar". |
| **#9b** | Ficha del reporte muestra solo el ÚLTIMO ajuste; falta acceso a la traza completa | M14 | media | Casi gratis y responde directo la pregunta del dueño: enlace "ver historial de cambios" → `/admin/auditoria` filtrada. NO duplicar registro: la traza ya existe (auditoría + historial de aprobaciones). |
| **#8** | Correos esqueléticos (cuerpo = título + "no responder") | M15 | media | Sembrar plantillas ricas para `aprobacion.*` (motivo/magnitud/reporte + link). El motor M15 ya soporta plantillas por evento (`notif_plantilla_<evento>`). De paso, distinguir el TÍTULO por resultado (hoy los 3 dicen "Solicitud resuelta"). |

## Fichas M (análisis dedicado — NO lote rápido)
| Id | Título | Territorio | Recomendación |
|---|---|---|---|
| **#2** | Timestamps en UTC (historial +4h vs hora Chile) | infra | Delicada: `app.timezone` UTC→America/Santiago toca el "hoy" de prod (`whereDate`), la grilla `*/15` y los tests. Alto impacto (mina la confianza en la traza) pero riesgo real → análisis dedicado, con cuidado quirúrgico. |
| **#6** | Chips paramétricos configurables para el motivo del ajuste (idea de producto del dueño) | M14 | Reutilizar el patrón `<x-reason-chips>` (ya usado por soplador + rechazo), hacer los chips configurables en Configuración y aplicarlos al motivo del AJUSTE → lenguaje común solicitante↔aprobador. El Director dimensiona antes de comprometer alcance. |
| **#9** | Campanita in-app viaja por la cola de la grilla (latencia ≤15 min, no instantánea) | M15 | El canal `database` no tiene transporte externo → marcar `enviada` síncrono al despachar para campanita inmediata. Validar que no rompa el badge server-rendered ni la latencia aceptada del resto de la cola. |

## Vigilancia
| Id | Título | Estado |
|---|---|---|
| **I-01 (nota A8)** | 299 corridas del `aprobaciones.log` "> esperadas" | **DISUELTA por el Director 17-07**: `aprobaciones:escalar` corre `everyFifteenMinutes()` (routes/console.php:57). Desde el deploy M14 (14-07 13:12) a 17-07 ≈ 3 días × 96/día ≈ 288 → **299 es lo NORMAL para `*/15`**, no una anomalía. El "~126 esperadas" del acta usó una base mal calculada. Sin cron duplicado. Cerrada. |
| **#4** | El badge de la campanita no es push en vivo (se renderiza al cargar) | Sin acción — observación educativa, no bug. Comportamiento v1 correcto en este hosting (server-rendered, sin websockets). La mejora accionable de la latencia es #9. |

## Decisión del dueño (no es bug)
| Id | Título | Nota |
|---|---|---|
| **#9c** | Texto/badge ROJOS en la solicitud "Rechazada" | HOY cumple la paleta de 4 colores (rojo reservado a lo destructivo/negativo; "rechazada" sigue ese patrón, como "devuelto"). Cambiarlo a neutral es modificar la REGLA de paleta y arrastra decidir "devuelto" por coherencia. **Espera decisión del dueño.** |

## VERIFICACIÓN del lote `fix/qa-aprobaciones-ux` @ `f040791` (adversarial, 3 lentes)
- **Comportamiento (#1/#3/#7): CORRECTO** — rename toca solo las 2 entradas del historial (la bandeja conserva "Aprobaciones"); motivo+magnitud y diff anterior→nuevo con escape `{{ }}` y guards `?? []` correctos; campos del payload existen (los arma `ProduccionController::ajustar`).
- **Territorio: APROBADO** — 7 archivos, solo M14 aprobaciones + nav + test + bundle; cero cambios de motor/rutas/modelos; test aditivo.
- **🔴 BLOQUEADOR 1 (test rojo determinista):** `AprobacionBandejaTest::test_el_nav_distingue_bandeja_de_historial:184` usa `assertSee('>Aprobaciones<', false)`, pero el label va dentro de `<x-nav-link>` cuyo template pone `>` + salto + indentación + `{{ $slot }}` → el HTML real es `>\n    Aprobaciones`, nunca la cadena pegada. **La aserción falla siempre** → el lote NO pasa su propia suite. El parte declaró "634 verdes"; ese test nuevo no pudo haber pasado (probablemente no se corrió tras agregar esa aserción). El idiom `>X<` solo funciona con markup pegado (`>{{ $dgConteo }}<` de la campanita).
- **🔴 BLOQUEADOR 2 (bundle stale):** el bundle de la rama (`app-CQ-gaRIb.css`, construido en `f040791` desde `fe18d19`) NO tiene 6 clases de las vistas de M12 seguimiento que main SÍ tiene (`ring-4`, `border-amber-200`, `bg-amber-50`, `text-amber-800`, `top-11`, `last:pb-0` + `h-[calc(100%-1.75rem)]`). Mergear eligiendo ese bundle regresaría el boceto de M12 — el escenario campanita. Las 7 clases críticas de la flota SÍ están en ambos (flota a salvo). Resolución: refrescar main + `npm run build` en el árbol mergeado + commitear el bundle recompilado; NUNCA elegir manifest a mano.
- **(info, no bloquea)** robustez #7: `array_key_exists($campo, $nuevo)` reventaría si `$nuevo` fuera escalar — no alcanzable hoy (el productor siempre arma arrays); un `is_array()` guard es barato si se quiere.
