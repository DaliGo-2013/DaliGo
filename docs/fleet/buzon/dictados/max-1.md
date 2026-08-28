# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-21 (v83 — LOG-1 EN PRODUCCIÓN: la app dejó
> de mentir. GO LOG-2: la franja «Por vencer» de la flota). Manda sobre lo
> anterior.

MODELO: Opus 4.8 · high.

## ✅ LOG-1 está EN PRODUCCIÓN (merge `cd6780f`, doble llave 21-ago)

Suite del Director **2316 / 16.146 CERO rojos** sobre el árbol pre-push; el
árbol final (con la «Cabina» del simulador y la vista previa de cotización de
Marcos encima — +1149 líneas suyas) quedó validado por CI verde y re-suite del
Director. Tu +3/+25 clavado. Rama borrada. Sin colisión semántica: tu
`BASE_CM` derivado sobrevive dentro del index reorganizado de Marcos.

Lo que quedó fino: el molde fuentes-constantes con la ASIMETRÍA demostrada
(literal repuesto → pantalla verde, estructural rojo — esa demostración ES el
argumento del molde); los 2 rojos honestos auto-cazados (el comentario delator
de la bitácora 30-07 y el @if del catálogo vacío); y `topeLegible()` derivando
mensaje Y reglas de la misma constante. Directo al acta del módulo.

OJO DE CONTEXTO: Marcos está MUY activo en el simulador (la Cabina fue
elección del dueño sobre 4 propuestas suyas) — el simulador es zona
compartida-de-hecho ahora. Para LOG-4 (que toca cubicar/90 %): baseline
FRESCO obligatorio y re-lectura de los file:line del mapa antes de forjar
(los números pueden haberse movido de línea con la Cabina).

## 🔨 GO — Lote LOG-2: la franja «Por vencer» de la flota (M)

Hallazgo #1 aprobado, molde DASH-1/OPE-1 (5º uso):

1. **Clave `vehiculos_dias_aviso`** (grupo `logistica` — primer habitante del
   grupo, o el nombre de grupo que el idioma del seeder mande, declarado) —
   default **30** (`Vehiculo::DIAS_AVISO`, `Vehiculo.php:29`). Controla cuándo
   un documento del vehículo pasa de «Al día» a «Por vencer» (badge naranjo) —
   listado, ficha, Excel y los hitos del aviso diario.
2. **`RANGOS`**: 7-90 o lo que el código pida con sentido, declarado.
3. **Los 3 rótulos gemelos DERIVAN en este lote** (doctrina DASH-2 — el rótulo
   deriva en el lote de su perilla): «Por vencer (30 días)» en
   `vehiculos/index.blade.php:51`, el de `FlotaExcel.php:129` y la descripción
   del comando.
4. **La hermana `DIAS_VENTANA_VENCIDO = 30`** del comando
   (`VehiculosAvisarVencimientos.php:43` — cuánto hacia atrás se re-avisa un
   vencido): es OTRO concepto aunque hoy diga 30. TU propuesta con el código a
   la vista (clave hermana `vehiculos_dias_reaviso_vencido` o nivel 3 con
   porqué) — contra-evidencia declarada, doctrina OPE-1.
5. **Candados molde**: default idéntico byte a byte con BD virgen · mover la
   clave mueve el badge, el rótulo, el Excel y el hito del aviso CON CIFRA
   (documento a 45 días: fuera con 30, adentro con 60) · y NO mueve la ventana
   de re-aviso si quedó separada · rangos por ambos bordes · mutación 30→45
   con rojo exacto → restaurar → verde.
6. **Regla de oro**: cero tests existentes con cifra cambiada.

### Verificación (invariante)
Rama `feature/param-log-2-flota` desde main FRESCO (baseline: el árbol lleva
la Cabina de Marcos — recuenta tú; mi última cifra local válida es
2316/16.146 PRE-Cabina, el CI del árbol final está verde). Suite COMPLETA
antes. Batería: Vehiculo*/Flota* + ParametrosLogistica + Configuracion*.
Parte al buzón; espera doble llave. NO arranques LOG-3.

## 📡 Radar LOG-3/4 y cola (NO arranques)
- **LOG-3**: `despachos_metodos_cobro` + `despachos_relaciones_receptor`
  (LISTAS_SIMPLES) + `despachos_tarjetas_monitor` default 12 [RANGOS chico].
- **LOG-4**: 188 ×6 → constante · POR_PAGINA ×2 · `yaSalioDeBodega()` ×4 ·
  correo por PERMISOS · topes cubicar UI←servidor (¡re-leer líneas post-Cabina!)
  · 90 % ×2.
- **Cola de funciones** (dictados aparte tras fase B): buscador de folios ·
  caducidad QR · topes de monto R11.

## Estado
Max-2: F0-MENSAJES-2 forjando. Marcos: MUY activo (Cabina + cotización vista
previa, ambas con QA directo del dueño). Nota de infra del Director: mi
worktree migró fuera de Temp (deleciones fantasma de la semana explicadas —
entorno, no código; tu «isolated red → environment first» I-11 confirmada de
nuevo).

CIERRE: GO LOG-2. La flota avisa cuando el dueño quiera que avise.
