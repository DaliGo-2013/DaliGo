# Parte de Max-1 — 2026-08-20 · Dictado v81, F0-LOGÍSTICA HECHO: mapa de hardcodes del módulo Logística (solo docs)

> Forjador A, stream 1 · commits de docs directo a main (solo-docs, cuarto F0 del
> molde). **Cero código**: los veredictos son del dueño y la fase B llega por
> dictado. Nota de método al pie.

## El mapa en una línea

**14 hallazgos destacados** en el anexo §5.4 (+3 bloques nivel 3 en masa): **4
propuestos nivel 1** (la franja de 30 días de la flota, los métodos de cobro en
puerta, la relación del receptor, las 12 tarjetas del monitor), **0 nivel 2**, **10
nivel 3** (6 con duplicado/drift marcado) y **2 semillas que se responden sin
parametrizar**. Las 5 semillas del dictado, respondidas una por una.

## El titular

**La estructura está parametrizada; lo que falta son PANTALLAS y lo que sobra son
RÓTULOS GEMELOS.** El catálogo del simulador ya es 100 % columna-por-fila (el motor
no tiene ni un número de camión hardcodeado) — pero los valores solo se editan por
seeder: **el pedido de Trello del dueño («capacidad de carga») se resuelve con una
pantalla CRUD sobre la tabla que ya existe, no con una parametrización**. La flota
M18 es el módulo mejor parametrizado-por-fila del proyecto. El lastre es la familia
de **textos-que-mienten**: rótulos que repiten a mano el número que ya vive en el
dato — y uno miente HOY: la ficha del traslado promete «el espejo se refresca cada
15 minutos» y el sync corre `hourlyAt(45)` (1 vez por hora). Ese es fix inmediato
de la fase B.

## Lo demás que vale destacar

1. **El QR anti-fraude NO tiene ventana de validez** (semilla #3): `signedRoute`
   permanente a propósito (el QR va impreso en la carga); el control temporal es el
   ESTADO (doble retiro). Introducir caducidad sería función nueva, no perilla —
   decisión del dueño si la quiere.
2. **La cadena R11 no tiene umbrales de monto**: las 3 llaves son puramente
   secuenciales y de permiso — una hoja de $50.000 y una de $50.000.000 se
   autorizan con el mismo click. No es hallazgo de parametrización; queda
   constatado por si el dueño algún día quiere topes.
3. **Correo por ROLES vs campanita por PERMISOS en el MISMO evento** (entrega
   rechazada): la lista de roles a mano del correo es la misma forma de la falla
   del técnico industrial (bitácora 14-08). Higiene propuesta: unificar por
   permisos.
4. **Topes desalineados UI vs servidor** en el cubicar del simulador (1.200 vs
   1.500 cm; 9.999 vs 100.000 unidades) — drift real entre las dos puertas.
5. Higiene de moldes listos: `paginate(25)` ×2 → POR_PAGINA, el `188` ×6 → constante
   única, `[RETIRADO, EN_RUTA]` ×4 → un método con nombre.

## Método (auditable — desviación declarada)

Este F0 usó **censo por fleet**: 5 censadores en paralelo (uno por sub-bloque:
despachos, hojas de ruta, carga/simulador, vehículos+conductores, traslados
residual) que levantaron 232 hallazgos crudos con `file:line` + línea textual.
**La garantía sigue siendo la mía**: verifiqué con `sed -n` sobre `bea00037` cada
`file:line` que cita el anexo, dicté TODOS los veredictos con la vara de
`daligo.php`, y censé por lectura directa lo que el reparto no cubría (la PWA del
conductor completa + `offline-queue.js`). Dos correcciones mías sobre el censo: el
deslinde de `TrasladoServicio` (es de ST — anotado cross con sus `ROLES_AVISO_*`) y
el hash del HEAD auditado. Misma vara, más ojos, el juicio no se delegó.

## Para el radar del Director

- Mapa listo para veredictos. Si el dueño aprueba los 4 nivel-1: #1 es M (clave +
  3 rótulos derivados + la hermana del comando a decidir), #2+#3 van juntos
  (molde OPE-2, S/M), #4 es S. El fix del «cada 15 minutos» puede viajar en
  cualquier lote de la fase B (una línea).
- La pantalla CRUD de camiones del simulador (el pedido de Trello) es lote de
  FUNCIÓN, no de parametrización — se dicta aparte si el dueño lo prioriza.
- Siguiente del orden tras los veredictos: fase B de Logística, mía. NO la
  arranco solo.

## Fuera de alcance (declarado)

Código (fase B) · `TrasladoServicio` (ST, cross anotado) · la pantalla CRUD de
camiones (función, no parametrización) · RUTA-MAESTRA/Trello (Director) ·
territorio de Max-2 (Mensajes fase 2) y Marcos.
