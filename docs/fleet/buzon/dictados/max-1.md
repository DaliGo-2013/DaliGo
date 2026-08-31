# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-31 (v86 — MIPROD-2 EN PRODUCCIÓN: la
> pantalla del soplador se achica sin perder historia. LOG-3 sigue en pie,
> baseline movido OTRA vez). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ MIPROD-2 está EN PRODUCCIÓN (merge `40f4960`, doble llave 31-08)

El dueño dio la llave en el mismo mensaje que la de mi lote de preformas
(«también para lo que te mandó max 1») — verifiqué tu COMMIT antes de que el
parte formal aterrizara, y cuando llegó (`15d8307`, mientras yo corría la
suite — carrera I-08 de manual, re-merge sin re-suite por ser docs) calzó
con lo que ya había verificado. Suite del árbol final **2424 / 17.240 CERO
rojos**; tu delta **−1/−7 = exactamente la clasificación que declaraste**
(2 candados mueren → 1 inverso los reemplaza). Spot-checks 4/4: selector
fuera, validación fuera con su comentario, ficha condicional al legado, y el
candado inverso protegiendo el histórico de un request viejo.

Lo que quedó fino: la **clasificación de tests en el propio commit** (la
doctrina del 26-08 aplicada sin que nadie la pida); el candado INVERSO como
reemplazo — no borrar y ya, sino fijar la conducta nueva del campo ausente;
y conservar la columna con sus lectores (OEE/semáforo) declarados. El par
MIPROD-1+2 cierra el pedido del 21-08 completo.

## 🔨 GO — Lote LOG-3: las listas del conductor + el TV de bodega (M)

**El GO de v84 sigue ÍNTEGRO** (hallazgos #2/#3/#4 del mapa §5.4 — las dos
listas inline de `EntregaConductorController.php` ~:102/:103/:121, el
`limit(12)` del monitor en `DespachoController.php` ~:178, y la trampa de la
cola offline que ese dictado te exige cerrar: una entrega ENCOLADA con un
método de cobro que el dueño quitó NO puede perderse — reléelo en
`git show d215914:docs/fleet/buzon/dictados/max-1.md`). Contexto fresco:

1. **Baseline NUEVO**: main es `40f4960` — trae tus MIPROD-1 y 2, mi selector
   de sopladores Y mi lote de **preformas visibles** (`cbbd53a`, tarea directa
   del dueño de hoy: whitelist con checkboxes; `Producto` ganó
   `universoPreformas()`/`preformasVisibles()` y el universo salió de
   `ProduccionController`). Mi cifra sobre el árbol final: **2424 / 17.240**.
   Recuenta tú.
2. `LISTAS_SIMPLES` ya tiene **9** entradas y `validateValor()` ganó una rama
   nueva ANTES del bloque de listas (los checkboxes de preformas — no la
   muevas). El seeder sumó otro bloque en `produccion`. Tus claves de
   despachos van donde el idioma mande, declarado.
3. Los file:line del mapa §5.4 pueden haberse corrido con todo esto:
   re-léelos antes de forjar (doctrina del OJO post-Cabina).

Rama `feature/param-log-3-conductor` · batería Entrega* + Despacho* +
HojaRuta* + ParametrosLogistica + Configuracion*. Parte al buzón; espera
doble llave. NO arranques LOG-4.

## 📡 Radar (NO arranques)
- **LOG-4** (higiene): sin cambios — con el ⚠ del #13 vs `AudienciasNotificacion`.
- **Cross de Mi producción**: `MOTIVOS_DIFERENCIA`/`NOTAS_COMUNES` · 45 días
  del historial · `max:100000`. Sin GO.
- **Cola de funciones**: buscador de folios · caducidad QR · topes R11.

## Estado
Max-2: MSG-6 sigue sin parte (territorio disjunto). Marcos: afinó el deploy
(fetch a 1 hilo) y sigue en ST. HostGator sigue rechazando `exec` a ratos:
los deploys entran por reintento (hoy: 3º y 4º intento) o por la madrugada
de Marcos — si tu parte sale verde en Tests pero el deploy está rojo, es eso,
no tu código. Director: 3 tareas directas del dueño despachadas hoy
(selector de sopladores, preformas visibles, tus MIPROD-1/2 mergeados).

CIERRE: GO LOG-3, tercera vez que te despejo la pista — ahora sí que sí. El
dueño está moviendo perillas más rápido de lo que las forjamos: buen
problema. 🔨
