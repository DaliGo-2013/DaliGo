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
Contenedor 40', HINO 500 y HD35, verificadas contra sus cupos de
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

### 2.3 «Mover la carga»: se reordena la lista, no se arrastran bloques (06-08-2026)

El dueño pidió lo que vio en EasyCargo: *«me interesa el tema de la última foto donde se
puede mover la carga»*. Ahí se arrastran los bultos con el mouse. **Acá se resolvió
distinto y a propósito:** el selector «Orden de estiba» pasa a `lista` y las flechas ▲▼ de
cada renglón mueven el producto; **el primero de la lista va al FONDO**. El motor
recalcula, así que lo que queda en pantalla sigue siendo un acomodo que el motor verificó.

Arrastrar bloques a mano dejaría armar en pantalla una carga que el propio cálculo dice
que no cabe, y el simulador existe justamente para que eso no pase (§2, el credo). Con el
reordenamiento el dueño consigue lo que necesita —decidir qué va contra la cabina y qué
contra la puerta— sin poder inventar una estiba imposible.

`CalculoDeCarga::carga(..., bool $enOrdenDeLista = false)`. **El automático sigue siendo el
predeterminado** porque es el que reproduce las cargas verificadas contra fotos; un valor
de `orden` inventado se rechaza en la validación en vez de caer en silencio. Candados:
`test_el_orden_de_la_lista_decide_que_producto_va_al_fondo` y
`test_el_orden_automatico_sigue_siendo_el_predeterminado`.

## 3. Unidades: el vendedor habla en botellones, el motor en bolsas

Las cantidades del formulario van **en unidades sueltas** (200 botellones, 20
cajas). El controlador convierte a bultos redondeando **hacia arriba** (198
botellones = 40 bolsas: la bolsa viaja completa o no viaja) y lo cargado se
reporta **capado a lo pedido** (198, no 200 — decir más de lo que pidió confunde).

## 3.1 De pie o acostado: la estiba se ELIGE (05-08-2026)

Pedido del dueño: *«necesito la opción de poder acostar el pack de botellones ya que en
los camiones la mayoría se acuestan»*.

La bolsa medida son **130 × 26 × 51**: cinco botellones **PARADOS** en fila (el 51 es el
alto del botellón, el 26 su diámetro). Acostarlos pone el eje en horizontal, así que el
pack pasa a **130 × 51 × 26** — el mismo largo, y el diámetro pasa a ser la altura.
`TipoBulto::paraCalculo(bool $acostado)` intercambia ancho y alto; el motor no cambió.

**El número cambia, y hacia abajo:** en el HD35 son **420 de pie contra 270 acostados**,
porque acostada la bolsa mide 26 cm de alto y el tope de apilado (6) corta antes que los
220 cm de la caja.

Tres reglas que salen de eso:

1. **De pie es el predeterminado.** Es la orientación con la que el dueño verificó sus
   referencias (420 / 960 / 1500 / 1620). Si el default se diera vuelta, esos números
   dejarían de cuadrar y nadie sabría por qué. Candado:
   `test_acostado_da_menos_botellones_y_de_pie_sigue_siendo_el_predeterminado`.
2. **Se elige POR LÍNEA**, no por pantalla: en la misma carga puede ir un pack acostado y
   otro de pie.
3. **Solo se ofrece donde cambia algo**, o sea en los bultos de orientación FIJA
   (`TipoBulto::puedeAcostarse()`). A los demás el motor ya les prueba las 6 rotaciones y
   se queda con la mejor: ofrecerles «acostado» sería ofrecer empeorar el resultado. Si
   igual llega por URL, se ignora.

**La pantalla DICE con qué estiba calculó** (fila «Cómo viaja» en el cupo máximo, chapita
«Acostado» en el detalle de la carga mixta). Leer «entran 270» sin saber que fue acostado
invita a compararlo con los 420 de pie y a pensar que el simulador se equivocó.

Y el visor dibuja los botellones **tumbados de verdad** (`cilindroAcostado`, función
aparte de la vertical porque el sombreado y la tapa se calculan sobre planos distintos).
Si el lienzo mostrara los botellones parados mientras el cálculo dice «acostado», dejaría
de ser la prueba de lo que el motor hizo — que es todo lo que aporta.

## 3.2 El Chevy 3 se vendió (05-08-2026)

Aviso del dueño: *«el chevy 3 no está más, lo vendieron»*. Quedan **tres** camiones.

Sacarlo del array del seeder **no alcanza**: la fila ya había llegado a producción y
`updateOrCreate` no borra lo que dejó de estar en la lista. Por eso el seeder tiene
`VENDIDOS`, que **borra** esas filas en cada deploy. Se borra y no se desactiva porque una
fila desactivada seguiría apareciendo en cualquier listado que olvide filtrar por `activo`,
y cotizar contra un camión que la empresa ya vendió es prometer un viaje imposible — el
mismo criterio que con el «H1». Va en el seeder y no en una migración porque el seeder es
la fuente de verdad del catálogo y corre en cada deploy: una migración lo borraría una vez
y el próximo deploy lo volvería a sembrar. Candado:
`test_un_camion_vendido_se_borra_del_catalogo_aunque_ya_estuviera_sembrado`.

Efecto de rebote: **ya ningún camión usa la silueta genérica** `camion`, que queda solo
como respaldo para un camión sin silueta declarada.

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

### 4.1bis Controles: zoom de ESCRITORIO y nombres por bloque (05-08-2026)

**El zoom es solo de escritorio, a pedido del dueño**: *«no lo quiero para celular,
no quiero que se quede pegada o se ponga lento»*. Se cumple **por construcción y no
por un `if` de ancho**: entra por la **rueda del mouse** (que un táctil no emite) y
por botones que la vista esconde con `hidden lg:flex`. **No hay ningún handler de
`touch` ni de pinza** — lo vigila `test_el_visor_no_registra_gestos_tactiles`. En
celular sigue andando el arrastre para girar, que ya existía. Si algún día se quiere
la pinza, que sea a propósito y midiendo antes que no se ponga lento.

El zoom **ancla el punto bajo el cursor**: apuntar a la carga y girar la rueda acerca
la carga. Anclarlo al centro geométrico del camión dejaba la carga fuera de cuadro —
y lo que se pidió fue «zoom a la carga».

**Cuánto se ve cargado** (pedido del dueño 05-08): el visor abre **VACÍO** y se carga
con los pasos `+1 / +5 / +10 / Todo / Vaciar`, o con `▶ Cargar`, que reproduce la
estiba de a poco (para ver en qué ORDEN va la carga). Cualquier paso **corta la
animación** si estaba corriendo: si no, seguiría sumando sola y pelearía con el botón
recién tocado.

> Se probó abrir **lleno**, para que el dibujo coincidiera con el «entran 420» del
> título, y **el dueño lo descartó**: *«no quiero que el camión esté contabilizado a
> cuánto tiene que llegar»* — perseguir el tope no es lo que hace cuando arma una
> carga. El visor es una herramienta que se maneja, no una foto del máximo. No volver
> a cambiar el default sin preguntarle.

**Nombres**: UNA etiqueta por BLOQUE, no por bulto (con 324 bultos serían 324 textos
ilegibles y lentos). Un bloque = un producto, así que son 2-4 etiquetas. Llevan el
punto del color del bloque, que es la misma leyenda que la lista «producto por
producto». Se pueden apagar, y un bloque que la animación todavía no cargó **no** se
rotula: la etiqueta señalaría un lugar vacío.

### 4.1ter La bolsa de bidones se dibuja como bidones (05-08-2026)

Es la carga diaria de Dali y era la que menos se parecía a la realidad: un ladrillo
naranja. Con la foto del dueño (los 5 picos gathered arriba, y las medidas 130 × 26 × 51
que cuadran con 5 × 26 cm de diámetro) se dibuja como **N bidones parados en fila** con
la película de la bolsa por encima.

Los **N salen de la geometría**, no de un número fijo: `largo / ancho` da 5 tanto en la
bolsa de 20 L (130/26) como en la de 10 L (110/21). `TipoBulto::formaVisor()` decide
por categoría (`botellones` → bidones; el resto → bulto rectangular) y viaja en la
escena como `forma`. Es **dato de dibujo**: no está en `paraCalculo()`, así que no puede
mover un cupo — con candado (`test_la_forma_no_cambia_el_cupo`).

**Tope de detalle, medido y no elegido a ojo.** Un bidón son dos prismas de 8 lados, así
que una bolsa cuesta ~6 veces más polígonos que un bulto rectangular. Medido en el HD35
con 84 bolsas: **4.046 polígonos y 17,8 ms por frame (~56 fps)**. El contenedor con 324
bolsas daría ~11.000 polígonos y ~20 fps al arrastrar, así que por encima de
`TOPE_BIDONES = 150` bolsas se cae al bulto rectangular — a ese tamaño en pantalla se ve
prácticamente igual. Si algún día se quiere el detalle también ahí, hay que subir el
tope A SABIENDAS de que cuesta ~35 fps.

Dos cosas que se probaron y salieron mal, para no repetirlas:
1. **La bolsa como caja translúcida completa**: tres caras por bolsa × 74 bolsas
   apiladas y la carga entera se veía de vidrio. Quedó solo la película de ARRIBA, que
   es donde se ve en la foto.
2. **Sombrear el cilindro por profundidad de pantalla**: los bidones salían angulosos y
   el brillo saltaba al girar. Va por el ÁNGULO de cada cara contra una luz fija del
   mundo, que se lee igual desde cualquier cámara.

**Chapa de atrás** (pedido del dueño 05-08): un rótulo con el MODELO en la cara trasera
de la caja, abajo. No es una patente: el catálogo del simulador son cajas de carga TIPO.
Sale de `vehiculo.nombre` quitándole el paréntesis y, si sigue largo, las palabras de
adelante hasta que entre legible en la chapa — «Hyundai HD35» → **HD35**, «HINO 500 (FC
1118)» → **HINO 500**, «Contenedor 40'» → **40'**. El texto se pinta en la pasada de
arriba y se saltea cuando la cara trasera no mira a la cámara (saldría espejado) o
cuando en pantalla mide menos de 34 px (no se leería).

### 4.1quater La cabina del tracto, moldeada sobre fotos (05-08-2026)

El dueño pasó fotos de su **Actros 2545** (el que tira el contenedor) y pidió ir
moldeando **un camión por vez**. La cabina del tracto (`cabinaTracto()`, que solo usa el
semirremolque) se corrigió contra esas fotos:

| En las fotos | Lo que se dibujaba antes |
|---|---|
| Cabina alta **de una pieza**; arriba solo el deflector, fino | Cuña + un **cajón** de dormitorio encima |
| Parabrisas **casi vertical** | Reclinado un 30% del largo |
| Banda oscura ancha bajo el vidrio | No existía |
| Parrilla en **tres franjas** + faros en las esquinas bajas | Una franja |
| Espejos **grandes sobre brazos** | Dos palitos |
| Guardabarro blanco (color carrocería) + estribo | No existían |
| Tracto **6×4**: eje delantero + tándem trasero | Un solo eje delantero |

### 4.1quinquies El HD35, moldeado sobre sus fotos (05-08-2026)

El dueño pasó las fotos del **Hyundai HD35** y pidió «un modelo por cada camión». Su
cabina tiene función propia (`cabinaLiviana()`).

**Lo que más lo delataba**: en el HD35 con furgón, la CAJA es más **ancha** y bastante
más **alta** que la cabina —el furgón sobresale por los costados y queda muy por encima
del techo— y acá se dibujaba todo del mismo ancho y casi del mismo alto. Por eso la
cabina lleva su propio `anchoCab` (máx. 1,78 m) en vez de heredar el de la caja, y su
`altoCab` bajó de 0,86 × alto a **0,60 × alto**.

El resto sale de las fotos: cabina corta de techo plano (sin dormitorio), parabrisas de
~35% de la cara y más angosto que ella (quedan los parantes blancos), panel blanco del
logo, **parrilla negra**, paragolpes de parte baja negra, faros verticales con el ámbar
hacia afuera, espejos negros grandes sobre brazos que pasan el ancho de la caja, calco
gris diagonal en la puerta y estribo.

Y un error que las fotos destaparon: **el HD35 lleva ruedas gemelas atrás** (se ve en la
foto de la trasera del chasis) y se le dibujaba una sola por eje. Ahora los dos camiones
de reparto llevan simple adelante y doble atrás.

### 4.1sexies El HINO 500 FC 1118, moldeado sobre sus fotos (05-08-2026)

Silueta propia `camion_hino` y función `cabinaHino()`. Lo que la distingue en las fotos,
en orden de cuánto se nota:

1. **Espejos enormes sobre brazos largos**, montados alto y bien salidos — pasan el ancho
   del furgón y son lo primero que se reconoce del frente. Con espejos chicos la cabina
   se veía de cualquier camión.
2. El **furgón le gana en alto** (el techo de la cabina queda a ~2/3 de la caja) y un
   poco en ancho. `altoCab` bajó de 0,78× a **0,68×** el alto de la caja.
3. Parrilla con **marco plateado y el óvalo del logo al centro**, con listones negros.
4. Paragolpes claro con la placa al medio, faldón negro abajo y antiniebla.
5. Faros angulares grandes en las esquinas; techo plano con una ceja al frente.

**Una silueta por camión.** El dueño pidió «un modelo por cada camión», así que cada uno
tiene la suya y su propia función de cabina: `semirremolque` (Actros + Tremac),
`camion_hino` (HINO 500), `camion_liviano` (HD35) y `camion`, la **genérica que queda
solo para el Chevy 3** hasta que lleguen sus fotos. Candado:
`test_cada_camion_moldeado_sobre_fotos_tiene_su_propia_silueta`. **No volver a
colapsarlas en una función con banderas** — el `switch` está para eso.

De paso: luces de gálibo ámbar en las esquinas de adelante del furgón (están en las fotos
del HD35 y del HINO). El contenedor no las lleva.

**La cabina va BLANCA y en NEUTROS.** Blanca porque así es la flota real (se ve en las
fotos de carga del 05-08; antes era un azul inventado). Y en neutros, sin franja de
color, porque **dentro del lienzo el color es DATO**: pintar la cabina de naranjo o azul
la haría confundible con un producto de la carga. El contraste lo dan los grises de
paragolpes, parrilla, espejos y ruedas.

El bloque del visor vive en el partial `admin.carga._visor`: estaba copiado idéntico
en los dos modos de la pantalla y los controles nuevos habrían quedado duplicados.

### 4.1septies Lo que se le tomó a EasyCargo (05-08-2026)

El dueño pasó capturas de EasyCargo y pidió ideas para el visor. **El artículo de su blog
no sirve** (siete generalidades de marketing: 3D, arrastrar y soltar, web, API, 16
idiomas); las capturas sí. De ellas señaló tres cosas como lo que más le sirve, y son las
tres que se hicieron:

1. **Vistas fijas** («la capacidad para mostrar los diferentes opciones para ver la
   carga»). Cuatro botones: 3D, Costado, Planta, Puerta. Cada vista es un par de ángulos,
   no una cámara aparte. Los ángulos salen de la proyección, no del ojo: costado = `yaw 0`
   (el ancho deja de entrar en la x de pantalla); puerta = `yaw −π/2` (el largo sale de la
   x y el fondo queda detrás de la carga); planta = `pitch −1,35`, **no −π/2**, porque a
   90° exactos las caras verticales degeneran en líneas y la carga pierde el volumen.
   **Van también en celular**: sin zoom táctil, cambiar de vista es la única forma de
   mirar la carga desde otro lado.
2. **Códigos escritos en las cajas.** Cada producto tiene una LETRA (A, B, C… por orden de
   la lista) que va en el renglón de la lista, en el cartel del bloque y **escrita sobre
   cada bulto**. Antes solo había color, que es peor de dos maneras: un color no se puede
   nombrar en voz alta («cargá el verde» con dos verdes al lado no sirve) y con ocho
   productos los tonos se confunden. `SimuladorCargaController::letra()` es la única
   fuente, así que lienzo y lista no pueden desalinearse.
   · Una cara por bulto, **la más cercana a la cámara**: escribir en las tres visibles
     triplica el texto sin aclarar nada, y elegir «la de la puerta» dejaría los códigos
     invisibles en la vista de costado.
   · Las entradas de texto viajan en la MISMA cola de profundidad que las caras, para que
     una caja de adelante tape el código de la de atrás.
   · `CODIGO_MIN = 8` px está **medido**: con 11 no se escribía ni un código en la carga
     real (la cara visible de una bolsa de 26 × 51 cm, girada y en fuga, mide ~17 px de
     lado corto y pedía letra de 12). De paso hace de LOD gratis: alejado no se escribe
     nada, al acercarte aparecen.
3. **Metros de piso libres** («Free meters»). Más accionable que el porcentaje de
   ocupación para la pregunta de todos los días: «¿le sumo algo más a este viaje?». Se
   mide hasta el bloque que llega más adelante, así que lo que informa es un rectángulo de
   TODO el ancho y TODO el alto — conservador a propósito, en la misma dirección que el
   resto del motor.

4. **El panel de cubicaje en la esquina** (pedido explícito del 06-08). Por producto: su
   letra sobre su color, cuántas van de cuántas y un punto verde o rojo, **al lado del
   camión**. Repite el detalle que está más abajo **a propósito**: el valor es no levantar
   la vista del dibujo para saber qué es cada bloque. Se muestra desde `sm` — en un celular
   esos 13 rem se comerían media pantalla del camión, y ahí el detalle de abajo queda a un
   scroll.

**Descartado a propósito:** arrastrar y soltar bultos a mano. El acomodo lo calcula
`CalculoDeCarga` y es conservador y verificado; permitir mover bultos dejaría armar en
pantalla un plan que el motor dice que no cabe. Lo que sí se hizo es reordenar la lista y
recalcular — ver §2.3. También queda afuera el peso por eje: pide distancias entre ejes y
peso vacío por eje que no existen en el catálogo, y los límites legales salen de la
normativa del MOP, no de una app.

### 4.1septies-bis El COSTADO de la cabina (06-08-2026)

Pedido del dueño: *«la cabina del camión, ¿no hay chance de dejarla un poco más real o con
más detalle?»* — mirando la vista de **Costado**, que es donde se veía el problema. De
frente las tres cabinas ya tenían parrilla, faros, paragolpes y espejos; **de costado eran
una lámina blanca sin una sola línea**, y el costado es una de las cuatro vistas fijas.

`costadoDeCabina()` agrega, y lo llaman las **tres** cabinas con sus propias medidas:
vidrio de la puerta, junta de la puerta, manija y zócalo. `visera()` suma la lengüeta
sobre el parabrisas (no en el tracto: ya tiene el deflector rompiendo el plano, y sumarle
una visera sería inventar algo que las fotos no muestran). Y el **arco de la rueda
delantera**, que faltaba en los dos camiones de reparto — atrás ya estaba, y era lo que
más delataba la cabina como un cajón: la rueda salía de un costado liso.

Dos detalles de implementación que importan:

- **El vidrio y las líneas se separan 6 mm hacia AFUERA** de la cara del cuerpo. Eso
  resuelve dos cosas de una sola vez: los pone delante de la chapa cuando ese costado mira
  a la cámara, y los deja detrás —invisibles— cuando mira para el otro lado. Sin decidir
  nada, sin un `if` de ángulo.
- Es un **helper con parámetros, no una cabina más**: se sigue cumpliendo «una función de
  cabina por camión». Candado: `test_las_tres_cabinas_llevan_los_detalles_del_costado`,
  que mira el cuerpo de CADA función y no el archivo entero (ahí una sola llamada daría
  falso verde).

**El costado de la CAJA (06-08).** También era una sábana lisa de punta a punta. Ahora los
tramos de pared van con el tono **alternado** —nervios—, más marcados en el contenedor
(es corrugado) que en el furgón (paneles con junta). Sale gratis: los tramos ya existían
por el orden de dibujo. Y el furgón lleva **puerta lateral** de dos hojas con manijas
(`puertaLateral`), pedida por el dueño; **el contenedor no**, porque un 40' no tiene puerta
al costado y dibujársela sería mostrarle algo que su contenedor no tiene. Va translúcida
con borde, no opaca: la pared deja ver la carga a propósito y una puerta sólida taparía
justo lo que se vino a mirar. Va en `z = 0`, el costado que mira a la cámara en el ángulo de
apertura. Candado: `test_la_puerta_lateral_es_del_furgon_y_no_del_contenedor`.

**El eje delantero (06-08).** En el tracto estaba al 60% de la cabina y quedaba a 19 cm del
tándem: se veían las tres ruedas juntas y ninguna parecía el eje delantero (reporte del
dueño). Pasó al **82%**, que es donde va en un cab-over real, y se separa 1,68 m. Sale de
`M.ejeDel`, **un solo valor** para la rueda y para el guardabarro: estaban escritos por
separado y corregir uno dejaba el otro flotando sobre la nada. Candado:
`test_el_eje_delantero_tiene_una_sola_definicion`.

**El techo de este motor.** Lo que sí se puede seguir sumando son facetas planas: molduras,
más vidrios, biseles, calcos. Lo que **no** se puede sin cambiar de tecnología son curvas
suaves, texturas y reflejos — el visor son quads planos con sombreado por cara sobre
canvas 2D. Si algún día se pide realismo fotográfico hay que discutir Three.js otra vez,
con el costo en la mano (~150 KB gzip en una PWA contra los 7,8 KB de hoy).

### 4.1octies El encuadre mide el DIBUJO, y el recuadro toma la forma del camión

Reporte del dueño: «se sigue viendo apretado o pequeño» y «se ve muy hacia la derecha».
Las dos cosas eran el mismo error y se midieron antes de tocar nada:

- El encuadre medía **los 8 vértices de la caja de carga**, que no es lo que se pinta.
  Reservaba adelante un hueco que la cabina no llenaba (**medido: 221 px muertos a la
  izquierda contra 23 a la derecha**) y se le escapaba la sombra por abajo, que quedaba
  **cortada contra el borde** (0 px de margen). Ahora `medirEncuadre()` dibuja la silueta
  a una cola descartable y mide sus vértices ya proyectados: espejos, paragolpes,
  guardabarros y sombra incluidos. Quedó en 1,4% de desbalance.
- El recuadro era apaisado 2,21:1 y el camión en 3/4 proyecta una silueta de ~1,49:1: el
  alto se llenaba al 95% y **del ancho sobraba una cuarta parte**. Ahora `proporcionar()`
  le da al recuadro la proporción del camión dibujado. Se fija UNA vez con los ángulos de
  apertura y no cambia al cambiar de vista — si cada vista redimensionara el recuadro, la
  página entera saltaría al tocar «Planta».

**Girar REENCUADRA (06-08-2026).** Esto revierte a propósito la decisión de medir el
encuadre una sola vez por vista. Reporte del dueño: *«quiero que en el cuadrado el camión
esté en el centro, ahí lo estoy girando y se ve cortado la última parte»*. El motivo por el
que la escala fija estaba mal: al girar, el ancho proyectado de un acoplado de 12 m pasa de
12 m (de costado) a 2,4 m (desde la puerta), así que **cualquier ángulo distinto del medido
queda cortado contra el borde o diminuto en el medio**. Que el camión cambie de tamaño al
girar molesta mucho menos que verlo cortado.

- Solo con **zoom 1**: si el usuario se acercó a mirar un bulto, reencuadrar le sacaría de
  golpe el zoom que acaba de hacer.
- Costo medido: **6,9 ms por frame** girando el contenedor lleno (el reencuadre solo mide
  la silueta, no los bultos). Verificado en 7 ángulos: no toca ningún borde y el desbalance
  horizontal queda en ≤ 22 px de 1403.
- La sombra declara el **90%** de su radio al medir. Con el 100% descentraba el camión
  hacia arriba (130 px arriba contra 186 abajo); con el 75% se pasaba al otro lado y el
  borde de la sombra tocaba el filo de abajo.

Dos reglas que se derivan y no hay que romper:

1. **El encuadre mide la silueta y NO los bultos.** Si entraran, el encuadre cambiaría
   según cuánto haya cargado y el camión daría un salto de tamaño en cada «+10». La caja
   de carga es parte de la silueta, y la carga nunca sale de la caja.
2. **El alto del recuadro lo fija el CSS (`aspect-ratio`), no el atributo del lienzo.** El
   visor ajusta el mapa de bits al recuadro real para no salir borroso en un monitor
   ancho; si el alto saliera del mapa de bits, tocarlo movería el recuadro y el recuadro
   volvería a mover el mapa de bits. Candado:
   `test_el_recuadro_del_visor_fija_su_alto_por_css_y_no_por_el_lienzo`.

Y como el mapa de bits ya no es de 1240 px fijos, **todo el dibujo se hace en píxeles
lógicos** (los del CSS) con la escala en la matriz del contexto (`AW`/`AH`). Sin eso, un
lienzo al doble de resolución mostraría letras y carteles a la mitad de tamaño.

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
