# Parte de Max-1 — 2026-08-19 · Dictado v76, F0-OPERACIÓN HECHO: mapa de hardcodes del módulo Operación (solo docs)

> Forjador A, stream 1 · commits de docs directo a main (sin rama, sin suite — lote
> solo-docs, tercer F0 del molde). **Cero código**: los veredictos son del dueño y la
> fase B llega por dictado.

## El mapa en una línea

**13 hallazgos** en el anexo §5.3: **3 propuestos nivel 1** (las ventanas de los
informes, los motivos de parada, las procedencias de preforma), **1 nivel 2** (los
strings `%preforma%`/`%dañada%`), **9 nivel 3** (4 con duplicado/adopción marcada) y
**2 grupos cross** (Mi producción y ST). Las 4 semillas + la 5ª regla de alcance,
respondidas una por una en el anexo.

## El titular (lo que más me llamó la atención)

**El módulo más denso del proyecto resultó ser el MEJOR parametrizado — y eso es un
hallazgo sobre el MÉTODO, no solo sobre el módulo.** M11 se construyó ya con la
doctrina D-003 («la respuesta del dueño es un ajuste de datos, no código») y se nota:
la duración del turno, los horarios, el umbral del semáforo SIC y el umbral de ajustes
del jefe son claves vivas de Configuración; la meta OEE es columna POR MÁQUINA, el
umbral de mantención POR MOLDE, el ciclo ideal POR RECETA; las bodegas son 100 % BD+UI
y el semáforo de preformas deriva su meta de las asignadas del turno. **Lo que en
Dashboard y Comercial fue cacería, acá fue mayormente censo.** El respeto de autor que
pedía el dictado terminó siendo reconocimiento: los porqués están escritos en el código
y calzan.

## Lo demás que vale destacar

1. **El nivel 2 del mapa**: qué cuenta como «preforma asignable» se decide por LIKE
   contra strings de negocio (`%preforma%`, `%dañada%`) en el controller — el primo
   exacto de `categorias_equipo` de ST, que ya es config de despliegue. Mismo
   tratamiento propuesto: moverlo en caliente puede vaciar el selector en medio de un
   turno, así que nivel 2, no 1.
2. **La lista-que-crece con dientes**: los motivos de parada (#9). El molde COM-1
   (LISTAS_SIMPLES) le calza, pero con dos matices que el lote deberá honrar: el
   subconjunto «planificado» debe ser ⊆ de la lista (par a validar, primo de
   PARES_ORDENADOS) y verifiqué que la clase se PERSISTE al crear la parada — cambiar
   la lista NO reescribe el OEE histórico (`claseDe()` corre al crear, no al calcular).
3. **El acoplamiento de los turnos, declarado**: los HORARIOS son clave viva
   (`produccion_turnos`) pero los NOMBRES (`dia`/`noche`) son constante — agregar un
   turno «tarde» a la clave no lo haría asignable. No es perilla (es cambio de flujo,
   nivel 3), pero el día que el dueño quiera un tercer turno, ese es el mapa.
4. **La semilla #1 respondida en bloque**: todos los catálogos de estado del módulo
   (reportes, paradas, moldes, kaizen) son claves de máquina con flujo colgado — nivel
   3 sin fila propia. Ninguna lista-que-crece escondida entre los estados.
5. **Higiene para anotar en la fase B**: el `max:100000` anti-dedazo vive ×6 en el
   controller (+ Mi producción), el 92 del tope de rango ×2, y los 2 `paginate(25)`
   del módulo esperan la adopción de `Controller::POR_PAGINA` (mecanismo COM-2 listo).

## Método (auditable)

Line-scan por sub-bloques (controller 838 líneas + vivo, los 4 servicios, 13 modelos,
el hub E1, BodegaController, vistas) + greps de red + cada `file:line` verificado con
`sed -n` sobre `32406f28` (main fresco de hoy). «Mi producción» respetado como otro
apartado: sus 45 días, sus catálogos de motivos y sus topes quedaron ANOTADOS cross con
la nota de que las constantes viven en modelos de este módulo pero la pantalla es de
allá.

## Para el radar del Director

- Mapa listo para veredictos. Si el dueño aprueba los 3 nivel-1: #1 es M (3 pantallas,
  molde DASH-1), #9 es M (el par planificados ⊆ motivos), #13 es S (molde COM-1
  exacto). El nivel 2 (#3) es S/M (config nueva estilo `categorias_equipo`).
- Sugerencia para el acta del módulo: el hallazgo-marco (M11 nació parametrizado)
  merece una línea en el lenguaje del dueño — su inversión en la doctrina D-003 se
  está pagando sola.
- Siguiente del orden: Logística — espero dictado, no lo arranco solo.

## Fuera de alcance (declarado)

Código (fase B) · «Mi producción» (cross anotado) · mudanzas de `categorias_equipo`
(sin drift) · RUTA-MAESTRA/Trello (Director) · territorio de Max-2 (MSG-3) y Marcos.
