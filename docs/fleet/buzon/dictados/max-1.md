# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-19 (v74 — COM-1 EN PRODUCCIÓN. GO COM-2: la higiene de duplicados — cierre del módulo Comercial). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ COM-1 está EN PRODUCCIÓN (merge `8f40a96`, doble llave 19-ago)

Suite del Director sobre el árbol combinado CON MSG-1 de Max-2 (la secuencia I-08 del
seeder compartido funcionó de libro): **2227 / 15.510, CERO rojos**, delta exacto +6.
Rama borrada.

Lo que quedó fino: la UX «una por línea» (el criterio que te delegué, resuelto con la
vara correcta: el JSON crudo no era digno del dueño), `getLista()` como clamp del
consumidor, y `LISTAS_SIMPLES` como tercer mecanismo declarativo por clave — RANGOS,
PARES_ORDENADOS y ahora listas: la familia completa para lo que viene del proyecto.

## 🔨 GO — Lote COM-2: higiene de duplicados (S) — cierra el módulo Comercial

Tu propio radar del v73, sin sorpresas. Los 4 duplicados nivel 3 del mapa §5.2 a
constante única:
1. **25 por página ×3** (`ClienteController:21`, `ProductoController:54`,
   `ListaPrecioController:42`) → UNA constante compartida (propón dónde vive: ¿una
   clase de convenciones del módulo? ¿constante por controller que referencia una
   común? — lo que el idioma de la casa pida, con el porqué).
2. **50 errores mostrados ×3** (`productos/importar.blade.php:33,41,43`) → una
   variable única en la vista.
3. **chunk(500) ×2** (`ProductoController:314`, `:366`) → constante.
4. **Topes de peso/medidas ×2** (las 4 reglas repetidas en `:251-254` y `:433-436`) →
   las reglas a una constante compartida (import y form las referencian).

**Regla de oro estricta**: CERO cambio de conducta — delta 0 tests EXACTO (ni un test
nuevo salvo que decidas fijar una constante con candado, declarándolo). La suite
entera debe dar la MISMA cifra que la baseline. Es el lote más chico del proyecto:
que sea el más limpio.

### Verificación (invariante)
Rama `feature/param-com-2-higiene` desde main FRESCO (baseline: **2227 / 15.510** en
`8f40a96`). Suite COMPLETA antes y después — misma cifra. Batería dirigida: las
carpetas de los 3 controllers + el import completo. Parte al buzón; espera doble
llave. Con COM-2 en producción, el módulo Comercial queda listo para el QA del dueño.

## 📡 Después de COM-2
QA del dueño del módulo Comercial (las 2 listas editables + todo lo demás idéntico) →
**v75 abre F0-OPERACIÓN** (el tercero del orden: Producción, kardex, inventario,
configuración de producción — territorio grande, con los cross-módulo del mapa DASH
esperando: `ProduccionReporte::pendientes()`).

## Estado
Max-2 forjando MSG-2 (pantallas del chat — puede tocar rutas/vistas, disjunto de tu
lote). Marcos activo. Trello espejando. Baseline: **2227/15.510** en `8f40a96`.

CIERRE: GO COM-2. El lote más chico — delta cero exacto y a cerrar Comercial. Fierro.
