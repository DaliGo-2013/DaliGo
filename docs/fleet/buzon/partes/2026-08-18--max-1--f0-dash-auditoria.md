# Parte de Max-1 — 2026-08-18 · Dictado v67, F0-DASH HECHO: mapa de hardcodes del Dashboard (solo docs)

> Forjador A, stream 1 · commit de docs directo a main (sin rama, sin suite — lote
> solo-docs, precedente F0-densidad). **Cero código escrito ni propuesto**: los
> veredictos son del dueño y la fase B llega por dictado.

## El mapa en una línea

**8 hallazgos** en el anexo §5.1 de `PLAN-PARAMETRICOS.md`: **4 propuestos nivel 1**
(las tres semillas del Director confirmadas + uno que las semillas no tenían), **0 nivel
2**, **4 nivel 3** (2 con duplicado marcado igual, regla del dictado) y **6 anotaciones
cross-módulo** (4 de Servicio Técnico, 1 de Notificaciones, 1 de Producción) que quedan
para sus auditorías.

## Lo que más me llamó la atención (lo pedido)

1. **El hallazgo que las semillas no tenían: la card Sucursales del Inicio nombra las 4
   sucursales a mano** (`AccesosDashboard.php:43` — «Mirador, Coquimbo, Abate Molina,
   Buzeta»). Es la definición exacta del proyecto: string de negocio + lista que crece,
   con la fuente viva (la tabla `sucursales`) a un query de distancia. Si mañana se
   abre una sucursal, el Inicio queda mintiendo hasta que un programador edite un
   archivo.
2. **Una misma variable controla DOS cosas que el dueño querría mover por separado**:
   el `$d7` de `DashboardController.php:185` es a la vez el corte 0-7 del aging del
   taller (#3, propuesto nivel 1) y la ventana de «Última semana: entraron/salieron»
   (#4, propuesto nivel 3 — una semana es una semana). Si el dueño aprueba #3, el lote
   DEBE desacoplar la variable primero o mover el aging arrastraría el flujo semanal en
   silencio.
3. **Todas las ventanas de días tienen su texto gemelo en la vista** («Últimos 7 días»,
   «prom. 7 días», «0-7 · 8-30 · 30+», «llevan 30+ días»). Hoy no driftean porque nadie
   las mueve; el día que se parametrizen, **los rótulos tienen que derivar del
   parámetro** o el Inicio dirá una cosa y calculará otra. Está anotado en la columna
   «Repetido en» de cada fila — es la mitad del esfuerzo de esos lotes.
4. **Por qué el mapa no trae ningún nivel 2**: el Dashboard es una pantalla de
   solo-lectura — nada de lo que muestra puede «romper la operación» al moverse en
   caliente (la vara de daligo.php). Sus números son ventanas de mirada, no reglas de
   negocio. Los candidatos con dientes (estados, catálogos, umbrales que gatillan
   acciones) viven aguas arriba y quedaron anotados para sus módulos.

## Método (para que el mapa sea auditable)

Line-scan completo de los 5 archivos del módulo (no solo grep) + greps dirigidos de red
+ la aguas-arriba mínima de cada query del pulso (scopes/constantes de OrdenServicio,
ProduccionReporte, Aprobacion, Notificacion). Cada `file:line` de la tabla fue
verificado contra `a81b21d` (main fresco de hoy) con `sed -n` antes de escribirse.
`DashboardColoresController` y `acceso.blade.php` se barrieron y quedaron declarados
**sin hallazgos** (validación estructural y UI pura) — el dictado pedía revisarlos.

## Para el radar del Director

- El mapa está listo para los veredictos del dueño, fila por fila. Las 4 propuestas de
  nivel 1 son todas esfuerzo S salvo #3 (M, por el desacople y los rótulos derivados).
- Si el dueño aprueba #1/#2/#3, sugiero al Director dictar los lotes con la regla de
  oro bien visible: default = valor actual, delta 0 tests salvo los candados nuevos
  (default idéntico + mover-el-parámetro-mueve-la-pantalla, mutación de siempre), y
  clave nivel 1 con label/ayuda en español + validación de rango en la UI de
  Configuración.
- Siguiente módulo del orden: Comercial — espero dictado, no lo arranco solo.

## Fuera de alcance (declarado)

Código y propuestas de implementación (fase B) · módulos que no sean Dashboard (los 6
cross quedaron anotados con su dueño) · RUTA-MAESTRA (el bloque E-PARAM nace con el
primer avance real de fase B, plan §4) · territorio de Marcos y Max-2.
