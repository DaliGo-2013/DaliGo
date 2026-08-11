# Parte de cierre — Max-2 · P-M11-22 · Semáforo de preformas + notas del jefe (cierra F2 stream B)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **P-M11-22** — GO del dictado v21 (PLAN-M11-FINAL §4-F2, último lote de F2)
ESTADO: **HECHA** — pide doble llave
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: doble llave de `feature/m11-soplador-recibe` → **F2 de M11 queda 100%
cerrada** (SIC+vivo y OEE ya están en producción) → se abre F3 (moldes, Max-1;
kaizen P-M11-23, mío, cuando se dicte).

## EVIDENCIA

Rama **`feature/m11-soplador-recibe`** desde main fresco (`c5eda7a`), pusheada —
2 commits de código, suite COMPLETA verde en cada uno:

| Commit | Qué | Suite |
|---|---|---|
| `a344107` | Semáforo de preformas: service SemaforoPreformas + badge en la cabecera de mi-reporte + 11 tests | 1843 / 13.168 |
| `90b3528` | Notas del jefe: tabla + modelo Auditable + CRUD + banner en mi-reporte + 8 tests | (final) |

**Los 5 candados del dictado, verificados:**
1. Semáforo exacto con stock 500/80/0 vs meta 100 → alcanza (neutral) / parcial
   (brand) / sin stock (danger) — paleta-4 con candado estilo Vehiculo::variante.
2. Sin espejo o sin preforma → SIN semáforo. MUTADO ejecutado: gate de
   `bsale_variant_id` cegado → `test_sin_enlace_bsale_es_silencio` ROJO (la suma
   vacía daba danger en vez de null) → restaurado → verde.
3. Nota vencida o de otro soplador NO se pinta; la global y la propia sí
   (frontera de fechas con whereDate en ambos bordes, testeada día a día).
4. CRUD de notas: GET sin permiso = redirect+aviso, POST = 403; soplador
   rechazado; validación de texto y cronología.
5. Soplador sin costos: candado con la forma de RecetaCrudTest (texto percibido
   sin scripts — los magics de Alpine llevan `$` legítimos; el assertDontSee('$')
   ingenuo se descartó por eso) + regex de monto.

## Decisiones que valen registro

- **Silencio antes que rojo falso, con un gate EXTRA no dictado**: además de los
  del dictado (sin espejo / sin preforma), el semáforo calla si el soplador no
  tiene sucursal o si su sucursal no tiene bodegas `enOperacion()` — sin ese
  gate, un `whereIn([])` suma 0 y gritaría «sin stock» por un hueco de
  CONFIGURACIÓN (p. ej. bodega en wizard de baja, que sale del scope
  temporalmente).
- **`stock_disponible`, no `stock_real`**: lo reservado por documentos no está
  disponible para soplar (criterio del filtro con_stock de la ficha de bodega).
- **Sin filtro por propósito de bodega**: el contrato operativo es
  `enOperacion()` (M04-F1); limitación conocida documentada en el docblock — una
  bodega `transito` en operación cuenta preformas que van en el barco. Refinar
  por propósito sería inventar una regla sin decisión del dueño.
- **Notas**: `admin.produccion.notas.*` DENTRO del prefijo del ítem Producción a
  propósito (notas no tiene ítem de menú; recetas usó prefijo propio por la
  razón inversa). Test propio de aria-current único (el barrido de SidebarTest
  no cubre rutas que no son ítems). Banner ANTES del split de ramas de
  mi-reporte: se ve también sin asignación o con reporte enviado. Modelo
  Auditable (traza de quién escribió qué).
- Las claves de turnos (`produccion_turnos` + `produccion_minutos_turno`) NO se
  tocaron — la unificación sigue siendo pulido de F3 como lo dejaste anotado.

## Verificación adicional (gate propio)

- Volcado 375/768: badge del semáforo en la cabecera + banner brand de nota,
  sin overflow.
- Vecinos verdes en bloque: SidebarTest + MenuPrincipalTest + ProduccionTest
  (113/647).
- Bundle sin cambios (todas las clases existentes) — cero riesgo de purga.
