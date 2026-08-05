# Simulador de carga (módulo LOGÍSTICA)

> Pedido del dueño, 04-08-2026. Construido el mismo día en dos tandas: el cupo
> máximo (sesión Mac) y la **carga mixta** (esta). Contexto largo en
> `docs/EXPLORACION-CARGA-3D.md`; los datos del negocio que costó levantar
> (bolsa de 5, orientación fija, jaulas, UN3480) están en el parte
> `docs/fleet/buzon/partes/2026-08-04--claude-code--traspaso-simulador-carga.md`.

## 0. Los camiones del simulador NO son los de la flota

**Decisión del dueño, 05-08-2026** («yo no quiero que esté enlazado con los
vehículos de la flota»). El simulador tiene su **catálogo propio**:
`camiones_simulacion` (`App\Models\CamionSimulacion`), sembrado por
`CamionesSimulacionSeeder` en cada deploy con las medidas que él dictó —
Contenedor 40', HINO 500, Chevy 3 y HD35, verificadas contra sus cupos de
referencia. Son cajas de carga **TIPO** («un HD35»), no patentes.

La lección que motivó el cambio, para no repetirla: la primera versión leía las
medidas desde los vehículos de la flota, y cargarlas era un `.sql` manual por
phpMyAdmin. **Ese paso nunca ocurrió y producción quedó mostrando «falta medir»
para todo.** Un dato sin el cual la pantalla no funciona no puede depender de un
paso manual: o viaja en el deploy (seeder) o la pantalla no debió salir. Las
columnas `*_util_cm` de `vehiculos` quedaron sin uso por ahora; si la flota
algún día las necesita para otra cosa, ahí están.

El «H1» del dictado original no se siembra (vendido en 2021, descartado por el
dueño el 04-08): cotizar contra un camión que no existe es prometer un viaje que
no se puede hacer. Las medidas del seeder son **fuente de verdad del repo**
(`updateOrCreate`): una corrección viaja al deploy y una edición externa se
revierte — son datos verificados contra cálculo, no preferencias.

## 1. Las dos preguntas de la pantalla

La misma página (`admin.carga.index`, permiso `simular carga`) responde dos
preguntas **distintas**, con un conmutador:

| Modo | Pregunta | Motor |
|---|---|---|
| ¿Cuánto entra? | el MÁXIMO de un producto en el camión vacío | `CalculoDeCarga::cupo()` — rejilla exacta |
| ¿Cabe esta carga? | una lista de (producto, cantidad) concreta | `CalculoDeCarga::carga()` — acomodo por zonas |

El segundo es el caso textual del pedido original: *«200 botellones + 20 cajas de
tapas + 10 dispensadores → ¿entra en el camión X?»* — y responde **qué queda
afuera y por qué** (espacio / peso / eje de la caja), que es con lo que el
vendedor negocia.

## 2. El credo: exagerar es el único pecado

Un simulador que promete carga que no cabe manda al vendedor a comprometerse y la
carga queda en el andén — peor que no tener la herramienta. TODO redondeo va
hacia abajo. Reglas del motor mixto, deliberadamente conservadoras:

1. **Acomodo por zonas (guillotina), no empaque 3D genérico.** Bloques de rejilla
   exacta sobre regiones rectangulares de piso: lo grande al fondo, el piso
   restante se parte en «detrás» y «al costado» del bloque. Reproduce el patrón
   real de estiba de las fotos (muro de bolsas, máquinas al costado, cajas en el
   resto). El bin-packing 3D es NP-difícil y toda heurística genérica es
   inverificable a mano — esto es verificable, y ese es el punto.
2. **El espacio SOBRE un bloque es espacio muerto.** No se apila un tipo encima
   de otro. La estiba real a veces lo hace; prometerlo sin regla de soporte por
   kilo sería exagerar. Candado: `test_no_apila_un_tipo_sobre_otro`.
3. **Un bloque parcial reserva solo su huella real** (columnas de a `apilable_max`,
   en rebanadas a lo ancho), no la rejilla completa — lo contrario regalaría piso.
   Candado: `test_un_bloque_parcial_no_roba_el_piso_que_no_usa`.
4. **El peso es global a la carga**: lo que consumió una línea se descuenta a la
   siguiente, y el recorte dice `peso`.
5. **CENTÍMETROS ENTEROS**, nunca metros con coma flotante (regla heredada de
   cupo(): `2.00 // 0.40` da 4 en binario, y eso son 125 botellones fantasma).

### 2.1 El candado de consistencia

**Una carga de UN solo tipo, pedida de sobra, da EXACTAMENTE el cupo máximo.**
Si `carga()` y `cupo()` divergieran, la pantalla se contradiría a sí misma según
la pestaña. Es el primer test de `CargaMixtaTest` y el que hay que mirar si
alguien toca cualquiera de los dos motores.

### 2.2 Orden de colocación

Por **volumen de bulto descendente** (lo grande primero, como en la práctica),
sin importar el orden en que se escribieron las líneas — pero el reporte respeta
el orden escrito. Determinista: a igual volumen, el orden de entrada.

## 3. Unidades: el vendedor habla en botellones, el motor en bolsas

Las cantidades del formulario van **en unidades sueltas** (200 botellones, 20
cajas). El controlador convierte a bultos redondeando **hacia arriba** (198
botellones = 40 bolsas: la bolsa viaja completa o no viaja) y lo cargado se
reporta **capado a lo pedido** (198, no 200 — decir más de lo que pidió confunde).

## 4. El visor 3D y sus colores

La escena viaja SIEMPRE como **lista de bloques** (posición, orientación,
rejilla, cantidad, color, nombre); el cupo máximo es el caso particular de un
bloque. `carga3d.js` sigue sin librerías (prismas a mano sobre canvas).

### 4.1 Tres siluetas, no una (05-08-2026)

Pedido del dueño: «que los camiones se vean más reales y no cuadrados». La causa
no eran las luces: **los cuatro camiones se dibujaban idénticos** (una caja de
cabina pegada a una caja de carga), y así el visor no ayudaba a reconocer contra
qué se cotiza. El Contenedor 40' salía con cabina propia cuando en realidad viaja
sobre el semirremolque, y el HD35 de 4,3 m salía como un camión de reparto.

`camiones_simulacion.silueta` declara con qué dibujar
(`CamionSimulacion::SILUETAS`): `semirremolque` (tracto con dormitorio, quinta
rueda, patas de apoyo y tridem), `camion` (cabina separada, ruedas dobles atrás) y
`camion_liviano`. Es **dato de dibujo**: `paraCalculo()` no lo mira, así que una
silueta mal elegida afea el lienzo pero no puede mover un cupo. Es nullable y una
silueta ausente o desconocida cae a la deducida del largo
(`SimuladorCargaController::silueta()`), para que la pantalla nunca quede sin
dibujo por un dato que falte. Candados: `CamionesSimulacionSeederTest` (todo
camión sembrado declara una silueta que el visor conoce) y
`SimuladorCargaMixtaPantallaTest` (un acoplado y un camión de reparto no se
dibujan igual).

**Se descartó Three.js**, que era la recomendación externa: lo que faltaba era
geometría, no una librería, y son ~150 KB comprimidos en una PWA. El chunk del
visor quedó en 7,9 KB (3,5 KB gzip).

### 4.2 El orden de dibujo y los cuerpos largos

El visor ordena por la profundidad del **centro de cada cara**. Con eso, un cuerpo
largo (el piso, un riel o una pared de 12 m) se ordenaba como si estuviera entero
a la distancia de su punto medio y **se pintaba encima de la carga del fondo**: se
veía un parche gris en medio del bulterío y la cabina salía lavada. Por eso las
paredes van en **paneles** de ~0,6 m (`paredes()`) y el piso, los rieles y el
chasis en **tramos** de ~0,8 m (`tira()`). Y por eso el larguero bajo la caja son
dos vigas angostas y no una placa del ancho completo: dos superficies grandes casi
a la misma altura, partidas en tramos distintos, no las puede resolver este
algoritmo. Si mañana se agrega otro cuerpo largo, va partido.

Rendimiento: se descartan las caras que miran para el lado contrario a la cámara y
los bultos tapados por sus seis vecinos (invisibles dentro de un bloque macizo).
Sin eso, un cupo de 900 bultos son 5.400 polígonos ordenados por frame y el
arrastre se arrastra en celular. Los degradados van solo en la silueta (decenas de
caras), nunca en los bultos (miles).

**Los colores dentro del lienzo son DATOS**: distinguen un producto de otro y la
leyenda de la lista «producto por producto» pinta el MISMO color
(`SimuladorCargaController::COLORES_3D`, pública justo por eso). Es la excepción
sancionada tipo D-013 — fuera del canvas rige la paleta de 4.

## 5. Mercancía peligrosa

Si una línea lleva un bulto `peligrosa` (los cajones `UN3480` de baterías de
litio), la pantalla lo dice y aclara que **el cálculo es solo de espacio**: que
quepa no significa que se pueda cargar así. El simulador no valida normativa de
transporte — eso quedó explícitamente fuera de alcance (§2quater de la
exploración).

## 6. Lo que falta, en orden

1. **Calibrar**: contar UNA carga real y ajustar el factor (`conFactor()`) hasta
   reproducirla. Mientras tanto los números son un TECHO (pasillo 0, factor 1) y
   la pantalla lo dice.
2. **Medir las jaulas de máquinas** (llenadora 100/200, osmosis 500/1 tera,
   lavadora, sopladora): sin medidas no se siembran — no se inventan números.
3. **Cálculo inverso** («¿cuánto puedo vender en este camión?») — pedido
   explícito del dueño; mismo motor al revés.
4. Escenarios guardados y PDF para adjuntar a la cotización.

## 7. Nota sobre la especificación externa (EasyCargo-like)

El dueño entregó el 04-08 una especificación técnica y un `packer.js` (Extreme
Points, Crainic et al. 2008) como referencia. **Se tomó de ahí lo que aplica** —
rechazados con motivo, colores por tipo con leyenda, la advertencia de nunca
perseguir el 100% de llenado — y **se descartó a conciencia** el empaque por
puntos extremos: para el mix real de Dali (cientos de bultos IDÉNTICOS y
regulares) la rejilla por zonas es exacta y verificable a mano, mientras que la
heurística genérica coloca menos en el caso dominante y sus resultados no se
pueden auditar contra un cálculo manual. Si algún día se cargan decenas de tipos
irregulares distintos, ahí vale revisitarla (el `.js` de referencia quedó con el
dueño). El centro de gravedad y el reparto por eje (§6 de esa espec) quedan para
cuando el simulador se use para carga real y no para cotizar — hoy sería
precisión decorativa.
