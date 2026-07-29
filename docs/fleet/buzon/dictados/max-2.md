# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-28 (v12 — los 4 hallazgos VERIFICADOS OK; falta 1 candado nuevo de main y P-DSP-04 entra). Manda sobre lo anterior.

MODELO: Opus 4.8 · high (queda un arreglo de 3 líneas).

## ✅ Los 4 hallazgos: CERRADOS y verificados por el Director
Spot-checks sobre tu `fix/qr-hallazgos-gate` mergeada contra main:
1. **Lock**: el docblock ahora dice la verdad (`SQLiteGrammar::compileLock()` devuelve `''`, el
   lock no es asertable en la suite) y **`LockParaMySqlTest` da cobertura REAL a nivel grammar
   — lo corrí, 2 verdes**. Tomaste el camino correcto: el assert de `DB::listen` que el revisor
   sugería habría estado rojo sobre código sano.
2. **Flag `parcial`**: patrón checkbox+hidden clásico (`hidden value="0"` + checkbox
   `name="parcial" value="1"`). Ya no depende de que Alpine corra. ✓
3. **`user_id` de la evidencia**: cubierto en el camino HTTP. ✓
4. **Poll del monitor**: pasó de comparar el total a comparar una **firma** del contenido
   (`d.firma !== base`). Una carga que entra y otra que sale ya no se cancelan. ✓

Bundle: rebuildeé y verifiqué **96/96 clases del QR presentes** y **superset de main
(0 perdidas de 485)**. El conflicto de manifest es el de siempre y se resuelve con rebuild.

## 🔴 Lo único que falta: un candado NUEVO de main que tu lote no conocía
`MarcoHorizontalTest::ninguna_tarjeta_cobra_su_padding_de_escritorio_en_movil` está **ROJO**.
Main endureció el marco mobile-first mientras trabajabas (es el 4º contrato nuevo de la semana,
después de ancho por layout, botón único de volver y errores amables).

**3 líneas, todas la misma forma** — `p-6` sin variante:
- `resources/views/admin/despachos/cola.blade.php:38`
- `resources/views/admin/despachos/escanear.blade.php:92`
- `resources/views/admin/despachos/escanear.blade.php:140`

Las tres son `<div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm">`.
El candado pide **mobile-first**: `p-4 sm:p-6` (o `p-3 sm:p-6` si lo quieres más compacto en
celular). Ver «Marco horizontal» en CLAUDE.md. Corre `MarcoHorizontalTest` para confirmar y
**la suite completa** después.

**Ojo con el monitor de bodega**: la cola está pensada para pantalla grande, así que ahí el
`sm:p-6` es lo natural; pero el candado igual aplica y el operador puede abrirla en celular.

## 🟢 Después de eso: GO en firme, sin esperar otra llave mía para ARRANCAR
- **P-DSP-04 se mergea** en cuanto ese test esté verde. Yo hago la doble llave con el dueño
  (ya me delegó su llave para este lote) — tú solo avisa por parte.
- **GO P-DSP-05 (PWA del conductor, M08-MVP)**: arranca en **rama nueva desde main fresco**
  apenas P-DSP-04 esté mergeada. No encadenes sobre `fix/qr-hallazgos-gate`.
  Tu propio plan: hoja de ruta por zona con lectura offline, entrega con firma+foto+hora, cola
  IndexedDB `entregas` con `entrega_uuid` + unique + `lockForUpdate` + `ValidationException` +
  rama `expectsJson()` (patrón de la cola del soplador, bitácora 2026-07-02). Las columnas
  `capturado_at`, `entrega_uuid`, `firma_path`, `foto_path` y la regla del parcial ya existen.

## Nota de alcance que el dueño debe saber (no la cambies tú)
El gate objetó —con razón— que la doc afirmaba un control que el código no aplica: P-DSP-04
cierra **«una carga no sale dos veces»** (sólido, no se pudo romper), pero **no** cierra
«retirar una carga que no te corresponde», porque el panel reparte la URL firmada a cualquiera
con `manage despachos` y no hay scoping por zona. Ya lo informé al dueño como decisión de
producto. Si él pide cerrarlo, será un paso propio.

## Recordatorios
Suite COMPLETA (main hoy ronda 1138 tests; tu lote suma ~19). Blade tocado → build + grep
superset. Resolver conflictos con `git checkout origin/main -- <archivo>`, nunca con `>`
(el `>` de PS 5.1 mete BOM y revienta Vite). Parte al buzón.

CIERRE: parte a docs/fleet/buzon/partes/ + push.
