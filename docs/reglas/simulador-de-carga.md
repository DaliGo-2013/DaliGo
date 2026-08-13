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
`CamionesSimulacionSeeder` en cada deploy. Son cajas de carga **TIPO** («un HD35»), no
patentes. Desde el **11-08-2026** las medidas son **de huincha** —el dueño midió el interior
de las cuatro cajas— y el catálogo tiene **cinco**: Contenedor 40', HINO 500, Chevy 3, H3 y
HD35. Ver §3.5bis, que es donde vive la tabla.

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
2. **SEGUNDO PISO, con regla de soporte** (11-08-2026 — antes esta regla decía que
   el espacio sobre un bloque era espacio muerto). Un tipo se apoya encima de otro
   solo si **los dos declaran `soporta_peso_encima`**, nunca sobre la misma línea,
   y **un solo nivel**. Ver §2bis: lo que la regla vieja prohibía no era apilar,
   era prometerlo **sin una regla de soporte por kilo** — y esa regla llegó.
   Candados: `SegundoPisoTest` (6) + `test_no_apila_un_tipo_sobre_otro`, que sigue
   verde porque su tarima no declara soporte.
3. **Un bloque parcial reserva solo su huella real** (columnas de a `apilable_max`,
   en rebanadas a lo ancho), no la rejilla completa — lo contrario regalaría piso.
   Candado: `test_un_bloque_parcial_no_roba_el_piso_que_no_usa`.
4. **El peso es global a la carga**: lo que consumió una línea se descuenta a la
   siguiente, y el recorte dice `peso`.
5. **CENTÍMETROS ENTEROS**, nunca metros con coma flotante (regla heredada de
   cupo(): `2.00 // 0.40` da 4 en binario, y eso son 125 botellones fantasma).

### 2bis. SEGUNDO PISO: un tipo encima de otro (11-08-2026)

El dueño mandó una carga donde **200 botellones de 10 L quedaron afuera** con el motivo
«no queda espacio», y su comentario: *«lo más bien pueden agregarse arriba de los de 20
lts o al lado, porque son livianos y no rompen nada»*. Tenía razón, y el motivo era
verdad **del piso** y mentira **del camión**: arriba del muro quedaban 26 cm sin usar.

**Por qué se pudo dar vuelta la regla 2.** No prohibía apilar: prohibía prometerlo **sin
una regla de soporte por kilo**. La regla llegó con el pedido, y encima ya estaba en el
catálogo, curada producto por producto: `soporta_peso_encima`.

**Las cuatro condiciones**, todas con candado:

1. El bloque de abajo **declara** que aguanta peso. Un dispensador (`false`, jaula
   rotulada «keep off») no recibe nada — preguntado explícitamente al dueño el 11-08, y su
   respuesta fue **no por ahora**.
2. **La categoría de arriba tiene que estar admitida por la de abajo** (`SOPORTA_ENCIMA`).
   La primera versión pedía el mismo flag de los dos lados, y estaba mal: el dueño lo
   corrigió el 12-08 — *«arriba de las bolsas de 10 o 20 comúnmente no se pone nada pesado
   porque los bidones están vacíos, pero lo que se hace es que se cargan cajas y arriba se
   acomodan los bidones»*. La caja y la bolsa **las dos** «aguantan peso», pero no aguantan
   lo mismo, y con un solo booleano el motor apilaba **cajas sobre bolsas**, que es lo que
   nadie hace.

   | Abajo | Recibe arriba |
   |---|---|
   | `cajas` | `botellones` — la caja es la BASE de la estiba |
   | `botellones` | `botellones` — solo otras bolsas (respuesta del dueño, 12-08) |
   | `dispensadores` | nada (no está en la matriz) |

   El flag del catálogo **no se reemplaza**: sigue siendo el veto por producto, así que una
   bolsa que mañana se declare frágil deja de recibir carga sin tocar código.
3. **Nunca sobre la misma línea.** Un tipo no se apila sobre sí mismo acá: para eso está su
   `apilable_max`. Sin esta condición **una línea sola dejaba de dar el cupo verificado** —
   llenaba el piso y seguía apoyándose sobre su propio muro—, y ese número es el que
   reproduce los cuatro cupos de referencia.
4. **Un solo nivel.** Lo que se apoya no vuelve a ser techo.

**Arriba se prueba de pie y, si no entra, ACOSTADO** sobre su cara más grande — que es lo
que uno hace a mano. Y nada más: la primera versión permitía rotación libre y una plancha
de 200×100×50 quedaba **parada en punta**, 200 cm de alto, con dos donde no iba ninguna.
De las seis permutaciones, las cinco que apoyan sobre una cara chica no se prueban.

**El campo se llama `apoyo` y no `base`:** en un bloque de pallet `base` ya significa el
grosor de la tarima y el visor lo usa así. Dos alturas con el mismo nombre habrían dado un
pallet flotando, en silencio. El visor ya sabía dibujar en altura (los pallets lo hacían),
así que fue pasarle el número: sin eso, el motor contaba las bolsas de arriba y el lienzo
las dibujaba **atravesando** el muro.

### 2ter. SE PRUEBAN VARIOS ACOMODOS Y GANA EL QUE USE MÁS CAJA (12-08-2026)

> Esta sección nació como **pendiente** el mismo día y se cerró horas después. Se deja el
> planteo original abajo porque explica el problema mejor que la solución.

`carga()` ya no tiene UN acomodo: arma hasta tres **planes**, corre el motor con cada uno y
se queda con el mejor. Pedido del dueño, textual: *«el motor prueba las dos y se queda con
la que meta más carga»*.

| Plan | Qué cambia |
|---|---|
| 1 | El de siempre: lo grande primero (o el orden de la lista si el usuario lo pidió) |
| 2 | **La base primero**: lo que aguanta peso (las cajas) al piso, para que lo liviano tenga dónde apoyarse |
| 3 | **La base más baja**: como el 2, recortando el apilado de la base para que quede aire utilizable arriba. El recorte se calcula con la medida **más chica** de lo que podría subir — lo que ese bulto necesita acostado — y no a ojo |

**Cómo gana un plan:** primero que la carga entre **completa**, después por **volumen
ocupado** (literalmente «cuánta caja se usó»). El volumen y no la cantidad de bultos:
comparar «183 bolsas» contra «420 cajas» no dice nada.

**El desempate es el orden de los planes, y el primero es el de antes**, así que ningún
número existente se mueve salvo que otro plan lo supere **estrictamente**. Ahí quedan
atados los cuatro cupos de referencia.

**Los planes son POCOS a propósito:** cada uno es una corrida completa del motor y esto
responde a un submit de pantalla. Se generan solo cuando hay algo que pueda subir y algo
que pueda sostenerlo; si no, un plan y listo.

#### Lo que el buscador contestó, y no era lo esperado

Con el catálogo real, **lado a lado casi siempre gana**: la bolsa de botellones es grande y
apilarla sobre cajas desperdicia más espacio del que aprovecha. Medido en el Chevy 3 con 500
cajas + 60 bolsas: **35,4 m³ lado a lado contra ~31,5 apilado**.

Que la estiba del andén (cajas abajo, bidones arriba) pierda en volumen no la vuelve
irracional: el cargador no está maximizando metros cúbicos, está haciendo entrar **un pedido
concreto**. Y para eso el motor ya prioriza `cabe_todo`, así que **apila cuando es la única
forma de que entre todo** — candado
`test_el_motor_apila_cuando_es_la_unica_forma_de_que_entre_todo`.

### 2ter-bis. El planteo original: el motor se tapaba su propio techo

Hallazgo al probar la estiba que dictó el dueño (cajas abajo, bidones arriba) en el Chevy 3:
**no subió ni una bolsa**, y no es un bug.

La caja de tapas mide 42 cm y el motor **apila la base lo más alto que puede**: 5 cajas son
210 cm de los 230 útiles, y arriba quedan 20 cm donde no entra ni una bolsa acostada (26).
Con las cajas de a **4** —168 cm— las bolsas suben de pie. O sea que el motor, maximizando
la base, se tapa a sí mismo la posibilidad de hacer lo que hace el cargador: en el andén las
cajas se apilan de a 4 **dejando el aire para los bidones**.

Es la misma pregunta que el dueño ya contestó para el orden: *«que el motor pruebe las dos y
se quede con la que meta más carga»* (12-08), pero aplicada a la **altura de la base**. **Ya está hecho** — ver §2ter arriba. El planteo era:

1. orden: base primero (cajas) contra volumen descendente (bolsas primero) — hoy solo el
   segundo;
2. altura de la base: al tope, o recortada para dejar techo utilizable.

Y además se puede reproducir **a mano** en cualquier momento: bajando el tope de
apilado de la caja en el formulario, que es un control que ya está a la vista.

### 2.2bis «Usar todo el espacio»: el motor gira el bulto en lo que sobra (10-08-2026)

Pedido del dueño: *«que se pueda cargar el camión completo hasta la puerta y que se ocupe
todo el espacio posible»*. A mano él pone el grueso acostado y, en la franja de 40 cm que
queda contra la puerta, **bolsas paradas y cruzadas** — de largo no entran.

El motor no lo hacía por un motivo concreto: un pack de **orientación fija** se calcula con
UNA sola orientación, así que en las regiones sobrantes probaba la misma que no entraba y
las dejaba vacías.

Ahora hay un interruptor **Usar todo el espacio** (opt-in) con una regla precisa:

> El **primer** bloque de cada línea conserva **siempre** la estiba elegida. Solo del
> segundo en adelante —o sea, ya en las regiones que sobraron— el bulto puede girar.

Eso es lo que hace que la opción no toque nada de lo verificado: el bloque principal sigue
siendo el mismo de siempre, y lo que aparece es carga en piso que antes se regalaba.

**No relaja el credo.** Cada bloque extra sigue saliendo de una **rejilla exacta sobre una
región real**, así que se verifica a mano igual que antes; no hay heurística nueva. Medido
en el HD35 con la bolsa acostada y apilado 8: **480 → 505 botellones**, en 3 bloques.

**Apagado no mueve ni un número**, y eso es lo que protege §2.1: el candado de consistencia
entre pestañas compara sin la opción, así que sigue en pie. Candados:
`test_usar_todo_el_espacio_llena_lo_que_sobra_girando_el_bulto` y
`test_apagado_da_el_mismo_resultado_de_siempre`.

**Por ahora es solo de la carga mixta**, que es donde el motor reparte el piso en regiones.
`cupo()` es una rejilla única sobre la caja entera y devolver varias rejillas rompería su
contrato; extenderlo es un paso aparte.

### 2.1 El candado de consistencia

**Una carga de UN solo tipo, pedida de sobra, da EXACTAMENTE el cupo máximo.**
Si `carga()` y `cupo()` divergieran, la pantalla se contradiría a sí misma según
la pestaña. Es el primer test de `CargaMixtaTest` y el que hay que mirar si
alguien toca cualquiera de los dos motores.

### 2.2 Orden de colocación

Por **volumen de bulto descendente** (lo grande primero, como en la práctica),
sin importar el orden en que se escribieron las líneas — pero el reporte respeta
el orden escrito. Determinista: a igual volumen, el orden de entrada.

### 2.3 «Mover la carga»: se reordena la lista, no se arrastran bloques (06-08-2026) — SUPERADO por §2.3bis

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

> **Esto quedó SUPERADO el 11-08-2026.** El reordenamiento sigue existiendo y sigue siendo
> el camino verificado, pero ya no es la única respuesta: ver §2.3bis. El razonamiento de
> arriba se conserva porque explica de dónde sale el cartel de advertencia.

### 2.3bis SE ACOMODA A MANO, con el cartel puesto (11-08-2026)

El dueño lo pidió **tres veces**. La tercera, textual: *«Te lo pido encarecidamente… que te
dé la opción de dar vuelta la caja y acomodar como uno quiero. ¿Se entiende o es muy
difícil?»*. Las dos anteriores la respuesta fue el §2.3 de arriba.

**Se hace.** El reparo del §2.3 es cierto y no cambió —arrastrar deja armar en pantalla una
carga que el cálculo dice que no cabe—, pero la decisión es de quien carga los camiones, no
del programa. Lo que sí queda del reparo es la honestidad del resultado, y eso son cuatro
reglas que el código sostiene (`App\Services\Carga\AcomodoManual`):

1. **Un acomodo NO cambia las cuentas.** Mover un bloque cambia dónde va, no cuántos
   entran. Si mover subiera el cupo, el tablero sería una forma de sacarle al motor un
   número que no calculó. Candado: `test_acomodar_no_cambia_cuantos_entran`.
2. **Lo que quedó mal se DICE, no se corrige.** Bloques encimados o fuera de la caja se
   reportan (`choques`, `fuera`) y salen en rojo. No se reacomodan solos: separarlos sería
   volver a decidir por el usuario. Tocarse **no** es pisarse — así dos bloques pegados,
   que es como se carga, no salen marcados.
3. **Una posición vale si sigue siendo del MISMO PRODUCTO.** Aplicarla sobre un bloque que
   ahora es otra cosa pondría carga ajena en el lugar equivocado, en silencio y con cara de
   verificada. Viaja con `acomodo_para` (un id de producto por bloque) y se compara uno a
   uno; lo que no coincide vuelve al lugar del cálculo y se cuenta. **Reemplazó al contador
   de bloques el 13-08 — ver §2.3ter.**
4. **El cartel viaja con el plan.** «Acomodo a mano · el cálculo no verificó estas
   posiciones» sale en la pantalla, en el **link compartido** y en el **Excel** — que es la
   hoja que se imprime y se le da al chofer.

**Girar es sobre el PISO, no volcar.** Se intercambian largo↔ancho del bulto y de la
rejilla a la vez (el bloque rota 90° con las cajas adentro) y el alto no se toca, así el
tope de apilado que calculó el motor sigue valiendo. Volcar una caja cambia cuántas se
apilan: eso es una pregunta para el motor («Cómo viaja»), no para el mouse.

**Se mueven BLOQUES, no cajas sueltas** — que es la unidad con la que el motor coloca y con
la que se carga de verdad (una estiba entera, no una caja). La caja suelta igual se puede:
una línea de UNA unidad es un bloque de uno, y ahí está el «cargar de a un bulto» del
pedido.

**Cómo está hecho.** Un tablero de **vista de planta** debajo del lienzo (`_acomodo.blade
.php`), no un editor 3D: el motor razona en huellas sobre el piso, así que la planta habla
el mismo idioma que el cálculo; arrastrar en perspectiva obliga a adivinar la profundidad
con el mouse. Los bloques se **imantan** a las paredes y a los cantos de los vecinos (4 cm)
porque si no quedan huecos de 2 o 3 cm que se acumulan hasta un «no entra» que no existe.

**Viaja en la URL** (`acomodo[i]=x,y[,g]` en centímetros + `acomodo_para`), como todo lo demás:
el link ES el escenario, así que un plan acomodado a mano se comparte y se baja a Excel sin
tabla nueva ni migración. Se aplica **en centímetros y antes de pasar a metros**, por dos
razones: comparar huellas en metros haría que `0,44 × 3 = 1,3199999999999998` se «pise» con
un vecino en 1,32 y la pantalla marcaría en rojo una carga perfecta; y así el giro de un
pallet arrastra su carga de arriba por el mismo camino que ya usaba el giro del motor
(`interiorDelPallet`), sin código nuevo.

Aplica a los **tres modos** (cupo máximo, carga mixta y sobre pallet). Candados en
`AcomodoManualTest` (13) y `PlanDeCargaExcelTest::test_avisa_cuando_los_bloques_se_acomodaron_a_mano`.

### 2.3ter CAMBIAR UNA CANTIDAD NO BORRA LO ACOMODADO A MANO (13-08-2026)

Decisión del dueño, textual: *«muchas veces los botellones se acomodan por cantidad y las
cajas se acomodan a mano, yo creo que lo mejor es conservar ambas»*.

**El problema era la clave.** El acomodo viajaba con un CONTADOR de bloques (`acomodo_de`) y
si el resultado cambiaba de tamaño se tiraba entero. Medido contra el motor: 40 cajas + 100
bolsas se reparten en **tres** bloques y 40 + 300 en **dos** — o sea que subir los botellones
borraba el acomodo de las cajas, que es justo lo que él había hecho a mano.

**Ahora la clave son los PRODUCTOS** (`acomodo_para=2,1,1`: un id de tipo de bulto por
bloque, en orden) y cada posición se aplica solo si el bloque que hoy ocupa ese lugar es del
mismo producto. Lo que coincide se conserva; lo que no, vuelve a donde lo puso el motor y la
pantalla dice cuántos («N bloque(s) volvieron al lugar del cálculo: cambió el producto que
iba ahí»). Si no sobrevive ninguno, se dice «se descartó» como siempre.

**El id y no el número de línea.** Fue mi primer intento y tiene un agujero: cambiarle el
producto a una línea —o reordenar la lista con los botones de mover— deja el mismo índice
apuntando a otra cosa, así que la posición se aplicaba igual. Con el id no. El candado que lo
destapó es `test_cambiar_el_producto_no_le_pasa_la_posicion_al_nuevo`, que quedó rojo con la
versión por índice.

**Y el contador viejo se sigue aceptando**, con su comportamiento de siempre: el link ES el
escenario y hay planes acomodados a mano circulando desde el 11-08. Lo que dejó de hacer es
escribirse.

**Lo que sigue sin conservarse, a propósito:** si una línea cambia de cuántos bloques ocupa,
los ordinales de las que vienen detrás se corren y esas posiciones se descartan. Se podría
indexar por producto+ocurrencia, pero cuando el reparto cambia las huellas también cambian:
la posición vieja es tan probable que se pise como que sirva, y volver a lo verificado es la
salida honesta. Dos líneas del mismo producto tampoco se distinguen entre sí, y no hace
falta: mover carga idéntica al lugar de su gemela no mueve carga ajena.

Candados en `AcomodoConservaTest` (11), incluido el **puente**: que el tablero escriba
`acomodo_para` con el producto de cada pieza. Un candado sobre el servicio no prueba que la
pantalla lo use — ya pasó tres veces en este módulo.

## 3. Unidades: el vendedor habla en botellones, el motor en bolsas

Las cantidades del formulario van **en unidades sueltas** (200 botellones, 20
cajas). El controlador convierte a bultos redondeando **hacia arriba** (198
botellones = 40 bolsas: la bolsa viaja completa o no viaja) y lo cargado se
reporta **capado a lo pedido** (198, no 200 — decir más de lo que pidió confunde).

## 3.1 La ESTIBA se elige entre TRES (05 y 06-08-2026)

Pedido del dueño: *«necesito la opción de poder acostar el pack de botellones ya que en
los camiones la mayoría se acuestan»* (05-08), ampliado el 06-08 con la tercera: *«hacela
hasta donde se pueda»* (la de «pico a la puerta», que él había descrito el 05-08 mirando
sus fotos de carga).

La bolsa medida son **130 × 26 × 51**: cinco botellones **PARADOS** en fila (el 51 es el
alto del botellón, el 26 su diámetro, el 130 la fila de cinco). De ahí salen las tres, y
cada una es una **rotación distinta del mismo pack** (`TipoBulto::ESTIBAS`):

| Estiba | Medidas | Eje del botellón | Cupo en el HD35 |
|---|---|---|---|
| `pie` | 130 × 26 × 51 | vertical | **420** |
| `costado` | 130 × 51 × 26 | cruzando el camión | **270** |
| `pico` | 51 × 130 × 26 | mirando a la puerta | **240** |

`costado` y `pico` se diferencian en un giro de 90° sobre el piso. **Parece lo mismo y no
lo es**, porque el motor no rota estos bultos (son de orientación fija): «pico a la
puerta» cruza la fila de 130 cm en una caja de 200 y desperdicia 70 cm de ancho, y por eso
da el peor cupo. No es un error — es la razón por la que en la práctica se elige según el
camión.

**El número cambia con cada una, y hacia abajo respecto de `pie`**: acostada la bolsa mide
26 cm de alto y el tope de apilado corta antes que los 220 cm de la caja.

Tres reglas que salen de eso:

1. **`pie` es el predeterminado.** Es la orientación con la que el dueño verificó sus
   referencias (420 / 1500 / 1620). Si el default se diera vuelta, esos números dejarían
   de cuadrar y nadie sabría por qué. Una estiba desconocida cae a `pie` en vez de
   calcular con medidas que nadie pidió. Candado:
   `test_cada_estiba_da_un_numero_distinto_y_de_pie_es_el_predeterminado`.
2. **Se elige POR LÍNEA**, no por pantalla: en la misma carga puede ir un pack de costado,
   otro de pie y otro con el pico a la puerta.
3. **Se ofrece en TODOS los productos, con `auto` adelante** (regla DADA VUELTA por el
   dueño el 06-08: *«que los dispensadores, cualesquiera que sea, tengan la opción de pie
   y acostado»*). Antes solo los de orientación fija la tenían — al resto el motor le
   probaba las 6 rotaciones y ofrecerle «acostado» era ofrecer empeorar el resultado. El
   argumento del dueño gana: un dispensador VIAJA parado aunque tumbado entren más, así
   que forzar la estiba es una necesidad real. Forzarla en un bulto libre significa
   exactamente **sacarle la rotación al motor** (`orientacion_fija = true` con las
   medidas giradas). Lo que protege los números verificados es el default
   **`auto`** (`TipoBulto::ESTIBAS_ELEGIBLES`): en auto, el fijo se calcula de pie y el
   libre rota como siempre — nada cambia hasta que alguien elige a propósito. Candado:
   `test_forzar_la_estiba_de_un_bulto_libre_le_saca_la_rotacion_al_motor`.

**La pantalla DICE con qué estiba calculó** (fila «Cómo viaja» en el cupo máximo, chapita
con el nombre de la estiba en el detalle de la carga mixta). Leer «entran 240» sin saber
cuál se usó invita a compararlo con los 420 de pie y a pensar que el simulador se equivocó.

Y el visor dibuja **la estiba que se calculó**, no una aproximación: `cilindroTumbado()` es
una función aparte de la vertical porque el sombreado y la tapa van sobre otros planos, y
recibe el EJE —a lo largo del camión para «pico a la puerta», a lo ancho para «de costado»—
porque las dos se ven distinto. Ahí la bandera está bien: es literalmente el eje del
cilindro, no dos cuerpos disfrazados de uno. Si el lienzo mostrara los botellones parados
mientras el cálculo dice «pico a la puerta», dejaría de ser la prueba de lo que el motor
hizo — que es todo lo que aporta. Candado: `test_el_visor_dibuja_la_estiba_que_se_calculo`.

## 3.2 El Chevy 3 se vendió (05-08-2026) — y volvió el 11-08 (ver §3.5bis)

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

## 3.3 SOBRE PALLET: un pallet es una caja de carga (06-08-2026)

Tercer modo de la pantalla. El dueño lo pidió así: *«que el pallet aparezca al lado del
camión en el piso con la opción de armarlo y luego subirlo al camión, que tenga un botón
para ajustar medidas»*.

**LA IDEA QUE EVITÓ ESCRIBIR UN MOTOR NUEVO: un pallet es una caja de carga.** Cuántas
unidades entran ENCIMA de un pallet se responde con el mismo `cupo()` que responde cuántas
entran en un camión, cambiando la caja de 12 m por una de 1,20 m. Y el pallet armado vuelve
al motor como un BULTO más, así que cuántos pallets entran en el camión también sale de
`cupo()`. **Dos llamadas al mismo cálculo verificado, cero heurísticas nuevas** — y por eso
el resultado hereda rejilla exacta, centímetros enteros y redondeo hacia abajo.

`App\Services\Carga\PalletSimulado`. Reglas:

1. **Los dos estándar que dictó** (120 × 100 y 120 × 80) son el punto de partida, **no una
   jaula**: largo, ancho, alto total y alto de la tarima son editables detrás del botón
   «Ajustar medidas» («deja la opción de modificar», textual).
2. **La tarima son 15 cm enteros** aunque la ficha del EPAL diga 14,4. El motor trabaja en
   centímetros enteros (§2.5) y redondear la base HACIA ARRIBA deja menos altura útil: el
   error va hacia abajo, que es el credo.
3. **Rotación solo HORIZONTAL** (`CalculoDeCarga`, `rotacion: 'horizontal'`). Un pallet gira
   90° sobre el piso pero no se tumba. Sin esta regla había que elegir entre dos mentiras:
   `orientacion_fija` perdía el giro válido (cupo más bajo que el real) y liberarlo dejaba
   al motor tumbarlo y prometer un acomodo que en la vida se hace.
4. **No se apila un pallet sobre otro** (`apilable_max: 1`), por el mismo motivo que no se
   apila un tipo sobre otro: sin regla de soporte por kilo sería exagerar.
5. **Si no entra ni un bulto encima, se reportan CERO pallets**, no «14 pallets vacíos».
   Pasa de verdad: la bolsa de botellones mide 130 cm y un pallet estándar tiene 120, así
   que sobresale. La pantalla dice el motivo y la medida que falta — un «0» pelado se lee
   como que la app se equivocó.
6. **EL PALLET ES PARA CAJAS, y solo para cajas** (dueño, 07-08-2026: *«el pallet va a ser
   para cargar cajas, solamente cajas»*). No es una limitación técnica —el motor palletiza
   cualquier bulto que quepa— sino cómo se trabaja en bodega. Explica de paso el «0» que
   el dueño vio en pantalla: había elegido la bolsa de botellones, que no va en pallet.

   **Las medidas de las cajas están PENDIENTES** — bodega las está tomando (07-08). Hoy el
   catálogo tiene dos ya medidas y con ellas el modo funciona: *Caja de soportes*
   (79 × 24 × 43) da **20 por pallet** y *Caja de tapas* (46 × 37 × 42) da **18**. Las que
   falten **se siembran cuando lleguen las medidas reales**, nunca con números estimados:
   misma regla que las jaulas de máquinas (ver el bloque «PENDIENTE DE MEDIR» de
   `TiposBultoSeeder`). Un bulto con medida inventada es peor que un bulto ausente, porque
   el ausente se nota y el inventado se cotiza.

El visor dibuja el pallet **en el piso al lado del camión** mientras se arma (tarima de
madera con tablas, tacos y patines) y el botón **«Subir al camión»** lo mete: ahí cada
bulto del camión es un pallet armado, con su base y su carga encima. El dibujo de la carga
sale de `rejillaDeBultos()`, la MISMA función que dibuja la carga suelta — duplicar ese
bucle habría dejado dos versiones que driftean (el descarte de interiores, el LOD de los
bidones y los códigos se habrían quedado en una sola).

## 3.4 El tope de apilado se puede pisar (06-08-2026)

El dueño marcó el hueco que quedaba arriba de la carga: *«ahí también se pueda cargar
bidones porque en la vida cotidiana se usa todo el espacio»*. **No era un error del dibujo
ni del acomodo: era `apilable_max` del catálogo**, que corta antes que la altura de la caja
(la bolsa dice 6, y acostada mide 26 cm, así que dejaba libre casi la mitad del HINO).

Ahora la pantalla acepta un tope por SIMULACIÓN que pisa el del catálogo
(`TipoBulto::paraCalculo($estiba, $apilado)`). Medido en el HINO con la bolsa acostada:
**180 bolsas con el tope 6 contra 300 con el tope 10** — el camión pasa a ir lleno de piso a
techo.

**El del catálogo sigue siendo el predeterminado**: es el dato que él dictó y con el que se
verificaron los cupos de referencia. Cuántas aguanta la bolsa de abajo es dato de terreno,
no de geometría, así que la decisión es suya y no del código. Candado:
`test_apilar_mas_alto_usa_el_espacio_que_quedaba_libre`.

### 3.3bis UN PALLET ES UNA LÍNEA MÁS DE LA CARGA MIXTA (10-08-2026)

Pedido del dueño: *«si cargo botellones y tapas, también tengo que tener la opción de poder
cargar pallets, porque en la vida real cargamos a veces pallets y de paso bidones o
dispensadores. Dame la chance de cargar cosas mixtas sin sacarme de la interfaz o sacarme
todo del camión sin dejarme hacer la prueba»*.

**«Sobre pallet» era un MODO que se comía el camión entero.** Para ver tres pallets de tapas
y cien botellones sueltos había que elegir uno de los dos, y al cambiar de pestaña el camión
quedaba vacío. No es un caso raro: es cómo se carga.

Ahora cada línea de «¿Cabe esta carga?» tiene un **«Cómo va»**: *Suelto* (lo de siempre) o
*Sobre pallet* con uno de los dos estándar. Con pallet, `tipo` pasa a ser **lo que va encima**
y `cantidad` cuenta **pallets**.

**No hay motor nuevo, y ese es el punto.** Vuelve a aplicarse la idea de §3.3: un pallet es
una caja de carga. Se le pregunta al mismo `cupo()` cuántas cajas entran encima, y el pallet
ARMADO entra a la carga mixta como **un bulto más** (`PalletSimulado::comoBulto()`, rotación
solo horizontal, sin apilar uno sobre otro). El acomodo por zonas lo reparte junto a los
bultos sueltos sin enterarse de que es un pallet. El modelo que viaja es un `TipoBulto`
**sin guardar** — el mismo truco del bulto a medida, y por eso la fila, la letra, el color y
el Excel siguen andando sin un solo `if`.

Decisiones que importan:

1. **La línea habla en PALLETS** (`unidadesEncima: 1` a propósito). «3 de 3 pallets · 18 por
   pallet = 54 en total». Si el bulto viajara con sus 18 unidades, «cargadas 54 de 3» sería
   un número sin sentido.
2. **El selector de producto solo ofrece cajas** (§3.3.6), y al pasar una línea a pallet el
   producto **se corrige** en vez de solo esconderse: si no, la línea quedaba apuntando a la
   bolsa de botellones —que no entra en un pallet, mide 130 y la tarima 120— y el resultado
   era un «no cabe» que el usuario no había pedido.
3. **Un pallet sin ni una caja encima no se sube vacío** (§3.3.5). El motor no puede verlo
   solo: para él la línea pidió cero pallets y los colocó todos, así que **el veredicto
   `cabeTodo` se arma con las filas** y no con `cabe_todo` del motor. La fila dice «no entra
   ni una encima del pallet» y el camión queda sin tarimas fantasma.
4. **«Apilar hasta» apunta a las cajas DE ARRIBA del pallet**, no a los pallets: un pallet no
   se apila sobre otro (§3.3.4), así que ahí el número siempre sería 1. Es el mismo control
   señalando el único lugar donde sirve, y los dos números del aviso salen del cupo INTERIOR.
5. **Se dibuja como pallet**, con el mismo `forma: 'pallet'` + `interior` que ya usaba el modo
   dedicado: **cero JS nuevo**. La rotación de 90° del interior se resolvía en
   `escenaEnPallet()` y se extrajo a `interiorDelPallet()`, que ahora usan las dos pantallas —
   dos copias de esa lógica es exactamente como se desincronizan el dibujo y el cálculo.
6. **El modo «Sobre pallet» se queda.** Responde otra pregunta —«¿cuántas unidades me llevo si
   lleno el camión de pallets?»— y es el único que deja **editar las medidas** del pallet y
   verlo armarse en el piso. La línea mixta ofrece los dos estándar y la altura; para un
   pallet a medida, el modo dedicado sigue estando.

**Un `+` que casi lo rompe en silencio:** el bloque del pallet se armaba con
`$bloque + ['forma' => 'pallet', …]`, y el operador `+` de PHP **conserva la clave de la
izquierda** — `forma` seguía valiendo `'caja'` y el pallet se dibujaba como un cajón liso, sin
error ni aviso. Va con `array_merge`. Lo cazó
`test_el_pallet_de_la_carga_mixta_se_dibuja_como_pallet_y_no_como_un_cajon`.

De paso, el Excel imprime el motivo **en castellano** (`PlanDeCargaExcel::MOTIVOS`) en vez del
código del motor: la planilla circula por correo y la lee gente que nunca vio la pantalla.

Candados: `test_un_pallet_es_una_linea_mas_de_la_carga_mixta` (con los 18 por pallet y los
205 kg verificados a mano), el del dibujo, y
`test_un_pallet_donde_no_entra_ni_una_caja_no_se_sube_vacio`.

### 3.4bis El tope de apilado también se elige POR LÍNEA (10-08-2026)

Reporte del dueño mirando el HINO cargado: *«necesito que los bidones también lleguen hasta
el techo de la caja; ahí solo veo las cajas de tapas que llegan hasta el techo y necesito
ocupar todo el espacio del camión»*.

**El control de §3.4 existía solo en «¿Cuánto entra?».** En la carga mixta cada línea se
quedaba con el `apilable_max` de su catálogo, y ahí está lo que hacía que el hueco no se
entendiera: **los dos productos apilaban los MISMOS 6 y llegaban a alturas muy distintas**,
porque el 6 se aplica a bultos de distinto alto.

| Producto | Alto del bulto | 6 de alto | De los 266 del HINO |
|---|---|---|---|
| Caja de tapas | 42 cm | 252 cm | toca el techo |
| Bolsa 5× botellón, acostada | 26 cm | 156 cm | **110 cm de aire** |

Por eso en pantalla parecía un error del dibujo: dos columnas al lado, una llena y la otra a
media caja, sin nada que explicara la diferencia. Tres cambios:

1. **`lineas.*.apilado`**, con el mismo contrato que la estiba: se elige por línea (una bolsa
   y una caja no aguantan lo mismo encima) y **vacío deja el del catálogo**, que es el
   comportamiento verificado. El campo muestra ese número como marcador, así que se sabe cuál
   manda sin abrir la ficha del producto.
2. **La fila del resultado dice los dos números**: «Van 6 de alto y la caja da para 10». Los
   saca del bloque que el motor YA colocó —su rejilla y la orientación con que lo puso—, no de
   un cálculo paralelo: si divergieran, la pantalla estaría explicando una carga distinta de
   la que dibujó.
3. **Un botón «Apilar 10»** al lado del aviso, que escribe el tope y recalcula. Sin él el
   camino era leer el aviso, buscar la tarjeta, abrirla y tipear. Está también en «¿Cuánto
   entra?», donde el número que la altura permite tampoco se decía en ninguna parte.

**El aviso solo aparece cuando subir el tope SIRVE**, y esa distinción es el punto. La caja de
tapas del ejemplo deja 36 cm de aire y NO lo muestra: el motor la giró y apiló 5 de 46 cm, que
es todo lo que dan 266 — ahí el que corta es el alto, no el tope, y ofrecer «apilar más» sería
prometer una fila que no entra.

Medido en el HINO con la bolsa acostada: **900 → 1.500 botellones** y la ocupación de 56% a
94%; en la carga mixta del reporte, el bloque pasa de 156 a **260 cm de los 266**.

**Lo que NO cambió: el default sigue siendo el del catálogo.** Cuántas aguanta la bolsa de
abajo es dato de terreno y no de geometría (§3.4), así que la decisión es del dueño y el
código no la toma solo. Candados:
`test_el_tope_de_apilado_se_elige_por_linea_y_vacio_deja_el_del_catalogo`,
`test_la_fila_dice_cuanto_aire_queda_arriba_y_ofrece_llenarlo` (mutado: pedido el tope, el
aviso se apaga).

**Pendiente de terreno:** si la bolsa de abajo aguanta 9 encima, el tope del catálogo debería
pasar de 6 a 10 y este aviso dejaría de aparecer en el caso más común. Hasta que él lo
confirme, el 6 se queda.

### 3.4ter Las líneas se ordenan por índice, no por lo que devuelve `validate()` (10-08-2026)

Encontrado al escribir el candado del botón «Apilar N», que necesita saber a qué tarjeta del
formulario le escribe. **`validate()` no devuelve los elementos de un array en el orden en que
llegaron**: arma el resultado recorriendo REGLA por regla, así que la primera línea que
aparece es la que trae la primera clave con la que se topó. Una línea **sin `tipo`** —un bulto
a medida— salía DETRÁS de las del catálogo aunque se hubiera escrito antes.

No es cosmético en esta pantalla: con «Como armé la lista» el primero va al **fondo** del
camión (§2.3), y de esa misma posición salen la **letra y el color** con que el producto
aparece en el lienzo. Se restaura con un `ksort` sobre las claves, que son los índices del
formulario. Candado: `test_las_lineas_se_calculan_en_el_orden_en_que_se_enviaron`.

**Y la lista de resultados se indexa por el índice de la línea**, no por su posición en la
lista. Una línea sin producto ni medidas no llega al resultado, así que las de abajo corrían
un lugar mientras los bloques seguían viajando con el índice original (`$b['linea']`):
`escena()` resuelve el nombre de cada bloque con `$mixta['lineas'][$b['linea']]` y **reventaba
con «Undefined array key»**; la letra, el color y el Excel señalaban al producto de al lado.
Con la clave puesta, los cuatro lugares hablan del mismo producto por construcción. Candado:
`test_una_linea_descartada_no_le_corre_la_letra_a_las_de_abajo`.

### 3.4quinquies La bolsa NO TIENE TOPE de apilado, y pesa 3,75 kg (11-08-2026)

Dos datos de terreno que llegaron juntos y corrigen §3.4quater el mismo día:

> *«No hay un máximo para apilar, se llenan todos los camiones siempre y no pasa nada.»*
> *«Cada preforma que se sopla y se convierte en botellón pesa 750 gr, o sea que una bolsa de
> 5 bidones vacíos pesa 3,750 kg.»*

**SIN TOPE.** El `apilable_max` queda en 30, que no es un tope real: es un número por encima de
lo que cualquier caja del catálogo permite —el peor caso es la bolsa de 10 L acostada en el
HINO, 266/21 = 12 capas—, así que **el que manda es siempre la altura del camión**.

El candado no fija el 30 sino **la propiedad**, que es lo que él dijo: para cada bolsa, cada
camión y cada estiba, el tope tiene que ser mayor que lo que da la altura. Fijar el número
dejaría pasar el error que ya ocurrió **dos veces**: con 6 mordía en todos lados, y con 10
mordía igual pero **parecía correcto por casualidad** —en el HINO la bolsa de 20 L acostada da
exactamente 10 capas— mientras le recortaba una capa entera a la de 10 L en el contenedor.

**PESO: 3,75 kg.** Hasta hoy la bolsa viajaba **sin peso**, o sea 0 kg para el motor. Era
inofensivo mientras no existía el aviso de sobrepeso (§3.6) y dejó de serlo en el momento en
que existió: una carga de botellones no lo habría disparado nunca, por pesada que fuera.

No mueve ningún cupo, y eso **confirma** lo que la nota del catálogo decía desde el 04-08: el
contenedor aguanta 7.680 bolsas por kilos y el espacio deja 324. Acá el límite es volumen.

**El de 10 L queda SIN peso a propósito.** Los 750 g son del botellón de 20 L —el que él
nombró— y el de 10 sale de una preforma más chica. Ponerle el mismo número sería inventar un
dato que ahora alimenta un cartel de advertencia, que es peor que no tenerlo. Falta pedirlo.

### 3.4quater La bolsa apila 10, no 6 — y eso cierra el hueco solo (11-08-2026) — AFINADO por §3.4quinquies

Dato de terreno del dueño, que era lo único que faltaba: *«si las bolsas aguantan 9 encima
porque están vacías, nada se rompe»*. **Nueve encima de la de abajo son DIEZ de alto.**

El 6 del catálogo era un número prudente puesto sin medir, y era **la causa** del hueco que
se venía parcheando desde el 06-08 con el control de apilado (§3.4) y el aviso de la fila
(§3.4bis). Con el dato real, el parche deja de hacer falta en el caso común: **el camión sale
lleno por defecto**, sin que nadie toque un control.

| | Antes (tope 6) | Ahora (tope 10) |
|---|---|---|
| HD35 de pie | 420 | **420** — igual |
| HD35 acostada | 480 *pisando el tope a 8* | **480** — sin pisar nada |
| HINO acostada | 900 | **1.500** (56% → 94% de ocupación) |

**Por qué el HD35 no se mueve, que es lo que hace segura la corrección:** sus 220 cm de alto
solo dan para 4 capas de pie (4 × 51 = 204) y 8 acostadas (8 × 26 = 208). Ahí manda la
ALTURA, no el tope. Donde cambia es en los camiones altos —HINO 266, contenedor 239—, que es
exactamente donde el dueño veía el aire.

Va en el **seeder** y no en la pantalla porque el catálogo es fuente de verdad del repo (§0)
y esto vale para toda simulación. Estrena candado —`TiposBultoSeederTest`, que el seeder no
tenía— y ata el número a los tres cupos de arriba: si alguien lo cambia, se entera por qué no
puede.

**El control por línea y el aviso NO se sacan.** Siguen sirviendo para el resto del catálogo
(las cajas siguen en 6, sin medir) y para probar «¿y si apilo 12?». Lo que cambió es que el
producto que se carga todos los días ya no los necesita.

### 3.1bis «No se tumba»: la cuarta opción del selector (11-08-2026)

Pedido del dueño mostrando las capturas del panel de EasyCargo, donde cada bulto declara cómo
puede girar: *«para las otras cajas que hay que cubicar… a veces se cargan cajas de diferentes
tamaños»* + la imagen de una caja girando.

**No es una cuarta estiba del pack de botellones** —por eso vive en `ESTIBAS_ELEGIBLES` y no
en `ESTIBAS`—: es una **restricción de giro**. La caja viaja con sus medidas naturales y el
motor todavía puede girarla 90° sobre el piso, pero no acostarla.

Sin ella había que elegir entre dos mentiras, que es el mismo argumento que ya justificaba
`rotacion: 'horizontal'` en el pallet (§3.3.3):

- **Libre** → el motor la tumba y promete un acomodo que en la vida se hace con una caja
  marcada «este lado arriba».
- **De pie** → pierde el giro válido de 90° y el cupo sale **más bajo que el real**.

Medido con una caja de 90 × 60 × 120 en el HD35: **de pie 12, no se tumba 14, automático 18**
(la automática la acuesta a 60 y gana). Las tres respuestas son distintas y las tres son
correctas para preguntas distintas.

Dos detalles de implementación:

1. **`rotacion` le GANA a `orientacion_fija`** en el motor, y está bien que gane: pedir «no se
   tumba» es aportar información que el catálogo no tenía. Por eso la opción también sirve
   para la bolsa de botellones, que es de orientación fija.
2. `orientacion_fija` queda en **false**, porque no es una estiba forzada. En el resultado no
   se nota —la bandera nunca se consulta cuando hay `rotacion`—, así que el candado lo fija
   sobre el contrato de `paraCalculo()` y no sobre el número. Sin eso, marcarla como forzada
   pasaba la prueba y dejaba la bandera mintiendo para el próximo que la lea.

Candado: `test_no_se_tumba_conserva_el_giro_de_90_que_de_pie_pierde`, mutado por los dos lados.

### 3.1ter El bulto a medida se DIBUJA mientras se tipea (11-08-2026)

Pedido del mismo mensaje: *«un tablero donde se pueda simular el tamaño de una caja para
agregarla al camión»*, sobre la captura del panel de ítems de EasyCargo.

El motor aceptaba bultos a medida desde el 07-08 y el panel los dejaba cargar desde el 10-08,
pero eran **tres números sueltos**: nada decía si la caja que uno acaba de escribir tiene la
forma que uno tenía en la cabeza. Ahora se dibuja en isométrico al lado de los campos, con sus
medidas y su volumen, y **se redibuja mientras se tipea**.

- Es **SVG calculado en la vista**, no el lienzo 3D: son tres polígonos: techo, frente y
  costado, con la misma opacidad decreciente que usa el visor para que se lea como el mismo
  objeto. No toca el motor ni el canvas.
- **Con una medida faltante no dibuja nada** y lo dice: media caja mentiría sobre la forma.
- Lo que aporta de verdad es delatar el **cero de más** y las dos medidas cambiadas de lugar,
  que en tres `<input>` no se ven y sí cambian el cupo.

**Gotcha que costó un rebuild:** el `<svg>` lleva su tamaño por **atributo** (`width`/`height`)
y no por clase de utilidad, porque la clase que correspondía no estaba en el bundle compilado
y no se podía recompilar sin arrastrar trabajo ajeno sin commitear. Y de paso: **Tailwind
escanea texto plano**, así que nombrar la clase dentro de un comentario de Blade —que nunca
llega al HTML— alcanza para volver a meterla en el bundle.

## 3.5bis LLEGÓ LA HUINCHA: las cuatro cajas medidas, y el 204 vuelve a 200 (11-08-2026)

El dueño midió el **interior** de las cuatro cajas de carga y pidió sembrar dos camiones que
faltaban. Esto reemplaza en la práctica a §3.5, que queda abajo como historia de cómo se
llegó al 204 y por qué se lo dejó marcado como pendiente.

| Camión | Medidas útiles (m) | De pie | Estado |
|---|---|---|---|
| Contenedor 40' | 12,03 × 2,35 × 2,39 | 1.620 | sin cambios |
| HINO 500 (FC 1118) | 7,97 × 2,60 × 2,66 | 1.500 | **confirmado** por huincha |
| Chevy 3 (NQR 919 · H3) | 7,90 × 2,20 × 2,30 | 960 | **vuelve al catálogo** |
| Hyundai HD35 | 4,30 × **2,00** × 2,20 | 420 | ancho **corregido de vuelta** |

**Los CUATRO cupos de referencia del 04-08 salen exactos** — 1.620 / 1.500 / 960 / 420 — con
las medidas de cinta. Es la validación más fuerte que tiene el módulo: dos fuentes
independientes (lo que él contó cargando y lo que dio la huincha) llegando al mismo número por
cuatro caminos distintos. Y el **960 del Chevy 3 estaba huérfano** desde la venta: era un cupo
de la lista original sin camión al que corresponder. Que al volver dé exactamente 960 confirma
que la lista estaba bien tomada.

### El 480 acostado queda SIN EXPLICAR, y no se persigue

La huincha dio **200**, así que el ancho vuelve y el cupo acostado del HD35 baja de 480 a
**360**. Era la salida escrita de antemano en §3.5: *«si la medición da menos de 204, los 480
no son alcanzables y hay que volver a 200 — y entonces el número de terreno es el que hay que
revisar»*.

Con 200 entran **3** columnas acostadas (3 × 51 = 153, sobran 47 cm) y no 4. **Prometer 480 en
una caja que mide 200 sería exactamente el pecado del §2.** El 480 pasa a ser un dato de
terreno pendiente de revisar, no una meta que el motor deba alcanzar. Hipótesis para cuando se
vuelva a contar: que esa carga fuera **mixta** (parte de pie, parte acostada) y no toda
acostada. El motor no puede adivinarlo y no debe.

Es un buen recordatorio de por qué el 204 se documentó como provisorio: **una deducción que
reproduce los datos no es una medición.** Reproducía los dos números y aun así estaba mal.

### «Chevy 3» y «H3» son EL MISMO CAMIÓN

Entró como dos filas y se unificó el mismo día, antes de llegar a producción. Lo destaparon
las fotos: el camión que el dueño mandó como «H3» lleva pintado **NQR 919** —el identificador
del `Chevy 3 (NQR 919)` que se había dado por vendido el 05-08— y él lo confirmó con el jefe:
*«es el mismo camión, solo que le dicen de las dos formas»*. **Nunca se vendió.**

**El nombre lleva los dos**: `Chevy 3 (NQR 919 · H3)`. El selector es la única parte buscable
de la pantalla, y quien lo conoce como «H3» no reconocería una fila que dice solo «Chevy 3».
El paréntesis respeta la convención del catálogo (`HINO 500 (FC 1118)`) y el visor lo descarta
para la chapa trasera, que queda en «CHEVY 3».

**Y la constante `VENDIDOS` pasó a llamarse `FUERA_DEL_CATALOGO`**, porque quedarse con el
nombre viejo era afirmar en el código que este camión se vendió — lo que resultó falso. Sigue
borrando la fila `Chevy 3 (NQR 919)`, pero ahora por otro motivo: si sobrevive, el **mismo
furgón aparece dos veces** en el selector, con dos nombres y dos juegos de medidas, y el
vendedor no sabe cuál es el bueno. Candado:
`test_la_fila_con_el_nombre_viejo_se_borra_y_no_deja_el_camion_duplicado`.

**LAS DOS MEDIDAS NO PUEDEN SER LAS DOS.** Vinieron como «chevy 3: 8,00 × 2,30 × 2,45» y «h3:
7,90 × 2,20 × 2,30», las dos presentadas como interiores. Se toma **el juego menor**, por dos
razones:

1. La diferencia es uniforme —10 cm en largo y ancho, 15 en alto—, que es exactamente lo que
   se espera entre **exterior e interior**. El juego chico es el que parece medido por dentro.
2. Y aunque no lo fuera, manda el credo (§2). Los dos reproducen igual el cupo de referencia de
   **960 de pie**, así que no se pierde nada verificado; para el resto del catálogo el chico es
   el que menos promete — con el grande serían **570 cajas de tapas contra 525**, y **1.080
   bolsas acostadas contra 960**.

**Pendiente:** que el dueño confirme cuál se midió por dentro. Si es el grande, se sube — el
error queda del lado seguro mientras tanto.

### La rueda de repuesto viaja ADENTRO de la caja (11-08-2026, pendiente)

Las primeras fotos del **interior** del Chevy 3 muestran la rueda de repuesto parada y
amarrada en el rincón derecho del fondo. Son unos **28 cm de ancho**, y en este camión el
ancho no tiene ese margen:

| Ancho útil | Bolsas a lo ancho | Cupo de pie |
|---|---|---|
| 2,20 m (caja vacía) | 8 (208 cm) | **960** |
| 1,92 m (con la rueda) | 7 (182 cm) | **840** |

**No se descuenta todavía**, y el motivo es que los dos datos se contradicen: los 960 los
dictó el dueño como cupo **real** el 04-08, así que o la rueda sale para cargar, o el 960 es
teórico. Es una pregunta abierta, no un dato — y descontar 120 botellones por una foto sería
el error simétrico al del §2: quedarse corto también hace perder viajes. Si confirma que la
rueda va siempre, el camino ya existe (`pasillo_cm` reserva espacio) y el cupo pasa a 840.

Los **listones de madera** de las paredes, en cambio, **no cuestan nada**: con 2,12 m de ancho
útil el cupo no se mueve (960 de pie y 525 cajas de tapas, idénticos). Verificado con el motor,
no supuesto.

Con sus fotos estrenó `camion_nqr` (§4.1decies), así que el catálogo vuelve a cumplir §4.1sexies
**entero**: cuatro camiones, cuatro cabinas propias, ninguna compartida. La genérica `camion`
queda de respaldo para uno que llegue sin fotos — como estuvo este unas horas.

### 3.5ter Los tonelajes oficiales, y el único que NO se tomó (11-08-2026)

| Camión | Carga máxima | |
|---|---|---|
| Contenedor 40' | **28.800 kg** | de la placa — el dueño pasó 30.000, ver abajo |
| HINO 500 | 11.000 kg | coincide con el catálogo |
| Chevy 3 (NQR 919 · H3) | **6.430 kg** | dato nuevo: estuvo unas horas en «sin dato» |
| Hyundai HD35 | 1.500 kg | coincide con el catálogo |

Tres de los cuatro confirmaron lo que ya había. El Chevy cerró el único hueco donde **el error
iba hacia arriba**: sin tonelaje el motor no recorta por peso y decía que entraba carga que el
camión no puede llevar.

**El contenedor se deja en 28.800 y no en los 30.000 que él pasó**, hasta que lo confirme:

1. La **placa** (42G1, NET 28.800 kg) es una fuente física y específica de ESE contenedor; el
   30.000 es un número redondo que puede ser de otro o de memoria.
2. Un 40' típico tiene bruto máximo ~30.480 kg y tara ~3.700, así que **30.000 se parece al
   BRUTO** (contenedor + carga), no a la carga sola, que es lo que este campo significa.
   Tomarlo prometería ~1.200 kg de más — y en peso pasarse no es un viaje a medias: es una
   multa.

## 3.6 EL CARTEL DE SOBREPESO (11-08-2026)

Pedido del dueño: *«que cuando se pase el límite de carga aparezca un cartel de advertencia que
indique límite de carga o sobrepasa la carga, aunque el camión no esté lleno completamente»*.

**Por qué no existía, que es lo interesante:** el motor ya recortaba por kilos desde el primer
día, así que el resultado **nunca** se pasa. Mirando la pantalla, el peso cargado siempre entra
— y el aviso parecía innecesario. Lo que faltaba no era el control, era **decirlo**.

Con carga pesada el camión se llena de kilos mucho antes que de metros, y ahí la pantalla
mostraba: 27% de ocupación, un dibujo con el furgón casi vacío, un renglón de peso discreto y
un «quedan N afuera». **Todo eso se lee como que sobra camión.** Y sobra, pero no sirve.

Dos carteles, uno por modo:

- **Carga mixta** — «Se pasa de la carga máxima»: *lo que pediste pesa 8.160 kg y el camión
  aguanta 6.430: 1.730 kg de más*, más la frase que el dueño pidió explícitamente («por eso
  queda camión libre, 27% ocupado»).
- **¿Cuánto entra?** — «Se llena de kilos antes que de espacio»: *por espacio entrarían 192,
  el peso deja 63*. Sin eso, «entran 63» se lee como que el camión es chico y la decisión que
  sale de ahí —mandar uno más grande— es la equivocada: el problema son los kilos.

**El dato que hubo que agregar es el PESO DE LO PEDIDO** (`mixta.peso.pedido_kg`). Lo cargado
siempre entra, así que es el único número que dice de cuánto te pasaste. Se calcula sobre las
cantidades pedidas y no sobre las colocadas.

En el modo de un producto no hizo falta ningún dato nuevo: `cupo()` calcula la rejilla y
**después** recorta por kilos, así que la rejilla que devuelve sigue siendo la del espacio y
multiplicarla da los «192 que habrían entrado».

Y hay un tercer estado, más suave: **«Al filo de la carga máxima»** cuando va sobre el 90% sin
pasarse. Una caja más y el viaje es ilegal, y eso tampoco se ve en el dibujo. La barra del peso
—que hasta ahora era el único número sin barra— se pone roja en ese punto.

Candados: `test_avisa_cuando_se_pasa_de_peso_aunque_sobre_espacio` (con la ocupación por debajo
del 25%, o el caso no probaría lo que dice probar), `test_sin_pasarse_de_peso_no_hay_cartel`
—un aviso que está siempre deja de leerse— y
`test_el_cupo_maximo_dice_cuantos_entrarian_si_el_peso_no_cortara`.

## 3.5 El ancho del HD35: 204 y no 200 (07-08-2026) — SUPERADO por §3.5bis

Reporte del dueño mirando el hueco de arriba de la carga: *«ahí se pueda cargar
bidones… en el HD35 ingresan acostados y en total 480 en el camión completo»*.
El cálculo daba **360**. La diferencia no era del motor: era el **ancho del
catálogo**.

Una bolsa acostada de costado ocupa 51 cm de ancho. Con **200 cm** entran **3** a
lo ancho (153, sobran 47); con **204** entran **4** (204 justos). **Cuatro
centímetros valen 120 botellones**, porque hacen entrar una columna entera — es la
naturaleza de la rejilla exacta, y la razón por la que una medida «casi bien» no
sirve.

**De dónde sale el 204, que no es una ficha ni un número inventado.** Se buscaron
por fuerza bruta todas las cajas enteras que reproducen **a la vez** sus dos cupos
de terreno —420 de pie y 480 acostado—. El resultado acota el ancho a **204–207**
y deja largo (430) y alto (220) del catálogo **dentro** de su rango: el único dato
que estaba afuera era el ancho. Se toma el **extremo bajo (204)** porque todo el
rango da los mismos cupos de botellones y, para los demás productos, es el que
menos promete (§2, el credo).

Es el mismo método con el que se verificaron los otros dos camiones: **contra sus
cupos, no contra una ficha**. Y hay que insistir en eso porque la ficha de fábrica
del HD35 *parece* contradecirlo y no lo hace: sus **1,76 m son el ancho de la
CABINA**, y el furgón carrozado la desborda por los costados (se ve en las fotos
del propio dueño — §4.1quinquies). Sus 5,31 m son el chasis entero y sus «3,4–3,7 m
carrozables» son largo de bastidor, no interior de caja. **Ninguna medida de esa
hoja es el interior del furgón**, que es carrozado aparte.

**Por qué la corrección es creíble y no un ajuste a medida:** un ancho elegido para
arreglar un número habría roto el otro. Con 204 salen los dos. El candado
`CalculoDeCargaTest::test_el_hd35_da_420_de_pie_y_480_acostado_con_la_misma_caja`
lo fija por los dos lados, y está mutado: con **200** se pone rojo en el acostado
(360 ≠ 480) y con **208** se pone rojo en el de pie (480 ≠ 420, entraría una octava
columna parada).

**Para llegar a 480 hay que apilar 8, no 6.** El tope del catálogo sigue siendo 6
(§3.4): son 8 × 26 = 208 cm de 220, así que la altura da — lo que no da el código
es la respuesta a «¿aguanta la bolsa de abajo?», que es dato de terreno. Se sube
desde el control de apilado de la simulación.

**Pendiente de confirmar con huincha:** el ancho interior real, de pared a pared.
Cualquier valor entre 204 y 207 da los mismos cupos de botellones, pero **sí cambia
para los demás productos** (una caja de 46 cm entra 4 veces en 204 y en 207 igual,
pero otras medidas no). Si la medición da menos de 204, los 480 no son alcanzables
y hay que volver a 200 — y entonces el número de terreno es el que hay que revisar.

## 3.7 CARGAS REALES: el historial simulado vs. real (lote 4, 11-08-2026)

Pantalla propia bajo LOGÍSTICA (`admin.cargas-reales.index`, mismo permiso `simular carga`).
Se anota una carga que YA se hizo —fecha, camión, producto, estiba, lo que dijo el simulador
y lo que entró de verdad— y la pantalla calcula el factor.

**Por qué es la pieza que faltaba.** El motor promete un TECHO y lo dice en todas partes:
«la estiba real no es una rejilla perfecta». `CalculoDeCarga::conFactor()` está escrito desde
el primer día para castigar ese techo, y **nunca se usó**, porque el factor se calibra
contando una carga real y no había dónde anotarla.

**Lo que cuesta no tenerla quedó demostrado esta misma semana.** El 07-08 el dueño reportó
480 botellones acostados en el HD35 contra 360 calculados. Sin ningún lugar donde poner ese
número, se convirtió en una **corrección de medidas**: se dedujo un ancho de 204 cm que
sobrevivió cuatro días hasta que la huincha dio 200 (§3.5bis). Con esta pantalla ese 480
habría sido **una fila** con su fecha y su estiba —comparable, discutible, y visiblemente
sola frente a los otros tres cupos que sí cerraban— en vez de una medida inventada.

Decisiones que importan:

1. **El factor NO se guarda.** Es una división de dos columnas que ya están en la tabla, y un
   número derivado persistido se desactualiza en silencio el día que se corrige un dato.
2. **Se agrupa por camión + producto + ESTIBA.** La misma bolsa da 420 de pie y 360 acostada
   en el mismo camión: promediar entre estibas daría un factor que no describe a ninguna. Por
   eso la estiba es obligatoria en el formulario aunque en el simulador tenga default.
3. **Un factor MAYOR a 1 se marca en rojo y se explica**: no es un error de tipeo, es la
   señal más valiosa de la tabla — significa que alguna medida del catálogo está corta. Es
   exactamente lo que habría gritado el caso del HD35.
4. **Con menos de 3 cargas el promedio se muestra igual, pero avisa que no alcanza.**
   Esconderlo sería peor (nadie sabría que hay algo anotado); presentarlo como factor sería
   llamarle promedio a una anécdota.
5. **Las observaciones son el campo más útil cuando los números no cuadran**, porque explican
   el POR QUÉ —«iba media carga de pie y media acostada»— que ningún número dice solo. Es,
   justamente, la hipótesis que quedó abierta sobre los 480.

### El lazo de vuelta: el simulador muestra lo medido

Sin esto el historial sería un cuaderno. Cuando hay cargas anotadas para la combinación que
se está mirando, la tarjeta del cupo agrega **«En terreno entraron 480 (133% de lo
calculado)»** con enlace al historial; cuando no hay ninguna, el pie invita a anotar la
primera.

**NO corrige el cupo, lo acompaña.** Reemplazar un número verificable por el promedio de dos
anécdotas sería perder información; mostrar los dos deja ver el hueco, que es el dato.
Aplicar el factor al número que se le muestra al cliente es un paso aparte y **deliberado**:
primero hay que juntar cargas suficientes, y eso lo decide el dueño mirando esta tabla.

Candados en `CargasRealesTest` (9), incluidos el agrupamiento por estiba, el aviso de
«entró más de lo calculado», que lo medido **no se mezcla** entre estibas ni entre camiones,
y que el cupo teórico sigue intacto al lado del medido.

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

#### 4.1sexies-bis El rompeviento del techo y los detalles de la puerta (11-08-2026)

Tres fotos más del mismo camión y un pedido: **«creale ese techo arriba de la cabina, me
imagino que es como un rompeviento, y algunos detalles más en los laterales de las puertas
o los espejos retrovisores»**. Lo que se agregó, todo dentro de `cabinaHino()`:

1. **El rompeviento** (deflector de techo). Es lo que faltaba para que se pareciera al de
   la flota: entre el techo de la cabina y el frente del furgón —que le gana ~75 cm—
   quedaba un escalón vacío, y ese hueco es justo lo que el deflector tapa en el camión
   real. Tres cosas lo hacen leer como deflector y no como un cajón:
   - es una **rampa** (baja adelante, alta atrás). Un prisma parejo se lee como el
     dormitorio de un tracto, que este camión no tiene. Se agregó la primitiva `rampa()`
     al visor: `cuna()` corre el techo en x pero lo deja horizontal;
   - **no llega al techo del furgón**: su alto sale de `veh.alto`, no de un número fijo,
     así que en una caja más baja se achica en vez de asomar por encima;
   - va **separado del techo**, con el bastidor a la vista en el hueco — en las fotos se
     ve el aire por debajo, y es lo que delata que es una pieza agregada.
2. **Espejos de dos tubos + convexo.** El soporte real son dos: uno casi al ras del techo
   y otro que sale del parante de la puerta. Con un solo brazo la paleta parecía flotar al
   costado, sobre todo de costado, donde el brazo de arriba se ve de canto. Y cuelga un
   convexo chico debajo de la paleta: son **dos espejos por lado**, no uno.
3. **Detalles del costado**: estribo de **dos peldaños** (se sube en dos pasos; con una
   sola tabla la puerta quedaba a la altura de la nada) y el **repetidor ámbar** adelante
   de la junta de la puerta, que en la vista de costado es el único punto de color de toda
   la chapa.
4. Del frente: los **dos limpiaparabrisas** y los **intermitentes ámbar** en las puntas
   del paragolpes (en la foto están encendidos).

**NUNCA la patente.** En las fotos está pintada en las dos puertas y en el paragolpes, y
el repositorio es PÚBLICO (D-012): la placa se dibuja como un rectángulo claro y vacío.

Candados: `SiluetaHinoTest` (rampa, medida contra `veh.alto`, las cuatro piezas del
espejo, y que el rompeviento **no** se le pegue al HD35, al NQR ni al tracto — sus fotos
no lo muestran, y el del tracto es otra pieza, fina y plana). Los tres verificados por
mutación.

#### 4.1sexies-ter El link compartido dibujaba un recuadro VACÍO (11-08-2026)

Encontrado al abrir el link firmado para revisar el rompeviento: la página pública traía
el visor y todos sus controles, pero **no** el `<script id="carga3d-datos">` con la
escena, y `montarCarga3d()` (app.js) sale sin hacer nada si no lo encuentra. O sea que el
link mostraba el recuadro vacío **desde el día que se publicó** (10-08), y lo único que se
comparte ahí es el dibujo.

Ninguno de los seis candados del link lo vio porque **todos preguntan por texto**, y el
texto estaba bien: nombre del camión, medidas, tabla de productos, aviso de vencimiento.
La lección: cuando lo que entrega una pantalla es un DIBUJO, un `assertSee` no la cubre —
hay que afirmar sobre lo que el dibujo necesita para existir. Candado nuevo:
`test_la_pantalla_compartida_manda_la_escena_al_lienzo`, que exige el `<script>` **y** que
traiga el vehículo adentro, en las dos pantallas.

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
por el orden de dibujo. El furgón llevó **puerta lateral** de dos hojas entre el 06 y el 07-08, y **se sacó**:
*«sacame la puerta de la caja que no queda bien»* (dueño, 07-08). El motivo se ve en el
lienzo y vale como aprendizaje: dibujada **translúcida sobre una pared que ya deja ver la
carga**, no se leía como una puerta sino como una mancha sobre los bultos. Opaca habría
sido peor —taparía justo lo que se vino a mirar—, así que no había versión buena: en un
visor donde las paredes son transparentes a propósito, un detalle plano sobre la pared
compite con la carga en vez de ambientarla. El detalle del costado lo dan los **nervios**,
que sí se quedan. El candado quedó **dado vuelta**
(`test_el_costado_no_lleva_puerta_pero_si_nervios`): vigila que la puerta no vuelva sola
—es el tipo de detalle que alguien reintroduce «para que se vea más real»— y que el
costado no quede liso.

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

### 4.1nonies UN MENÚ LATERAL, no botones por toda la pantalla (06-08-2026)

Pedido del dueño: *«organizá los botones en un menú, como las imágenes de EasyCargo, en un
lateral… y que no tenga tantos botones por toda la pantalla, siento que genera confusión»*.

Los controles vivían en las **cuatro esquinas** del lienzo (vistas arriba a la izquierda,
zoom arriba a la derecha, pasos de carga abajo a la izquierda, códigos y nombres abajo a la
derecha) más el cubicaje flotando. Cada pedido nuevo sumaba una esquina — el problema no era
ningún botón en particular, era que no había **un lugar** donde ponerlos.

Ahora hay un `<aside>` con secciones: Vista · Acercar · Cargar · Rótulos · Cubicaje · Traer
carga. Decisiones que importan:

1. **Es una barra que le COME ANCHO al lienzo, no un panel flotante encima.** Flotando
   taparía el camión, y agrandar el camión fue justamente el pedido de dos días antes.
2. **En celular arranca cerrado** (14 rem sobre 342 px serían media pantalla) y lo abre un
   ☰. En escritorio arranca abierto.
3. **El lienzo se reacomoda al abrir y cerrar.** Un `resize` de window no se enteraría, así
   que hay un `ResizeObserver` sobre el lienzo. Reencuadra en los **ángulos actuales** y no
   en los de la vista fija: si el usuario venía girando a mano, volver a la vista le pegaría
   un salto de cámara solo por abrir el menú.
4. **`reacomodar()` es SÍNCRONO, sin `requestAnimationFrame`.** La primera versión coalescía
   con rAF y tenía un modo de falla feo: en una pestaña que no está pintando el rAF no corre
   nunca, el flag de «ya hay uno agendado» quedaba puesto para siempre y el visor no volvía
   a reencuadrar jamás. Coalescer no hacía falta — un `ResizeObserver` ya avisa como máximo
   una vez por frame.
5. El zoom sigue viviendo en un envoltorio `hidden lg:block`: **la regla de «zoom solo en
   escritorio» no cambió**, solo se mudó de esquina.

Candado: `test_los_controles_viven_en_un_solo_menu_y_no_en_las_cuatro_esquinas`, que exige
que **todos** los ids `carga3d*` estén dentro del `<aside>` y que el menú no sea `absolute`.
Es lo que se rompe sin darse cuenta cuando alguien agrega «rápido» el próximo botón en una
esquina.

**Ampliaciones del 06-08 (pedido del dueño):**

- **Cada sección es un DESPLEGABLE** (`<details>` nativo, sin JS): Vista y Cargar abren
  abiertos —son los que se tocan todo el tiempo—, el resto cerrado.
- **Pasos de carga simétricos**: −10/−5/−1 y +1/+5/+10, más Todo y Vaciar. Con −1 solo,
  bajar de a mucho era un botón repetido veinte veces. **Reducidos a dos el 07-08 — ver
  §4.1nonies-bis.**
- **Sección Pallet en el menú**, con los dos tipos estándar (Industrial 120 × 100 y
  EUR/EPAL 120 × 80) como enlaces al modo Sobre pallet conservando camión y producto.
- **Cantidad a probar** en «¿Cuánto entra?»: *«¿me entran 50?»* además del máximo. No toca
  el motor — capa el dibujo a lo pedido y el veredicto sale de comparar contra el máximo;
  vacío = el máximo, como siempre. Candado:
  `test_la_cantidad_a_probar_capa_el_resultado_y_el_dibujo`.

> Nota para quien verifique esto en el navegador de las herramientas: con la pestaña oculta
> **ni `requestAnimationFrame` ni `ResizeObserver` corren** (no hay ciclo de pintado). Para
> probar el reencuadre hay que disparar `window.dispatchEvent(new Event('resize'))` a mano
> después de abrir o cerrar el menú, y esperar un tick a que Alpine aplique el `x-show`.

### 4.1nonies-bis Los pasos de carga son DOS (07-08-2026)

Pedido del dueño mirando el menú: *«cambiar esos botones de carga, dejar dos que sea
para + (agregar) y − (sacar), porque es mucho número»*. Salen los seis
(−10/−5/−1/+1/+5/+10) y quedan **− y +**, más Todo y Vaciar.

**Esto revierte lo del 06-08, y hay que entender por qué se puede.** Los seis pasos
existían por un problema real: con un paso fijo de a uno, llenar el contenedor de 324
bultos era repetir el clic 324 veces. Lo que hace que ahora sobren no es que el problema
desapareciera, sino que **− y + se pueden MANTENER APRETADOS y aceleran**: el paso arranca
en 1, sube a 5 y después a 10. El recorrido largo se hace igual de rápido y el corto sigue
siendo exacto.

**Sin la repetición, esto sería un retroceso — no la saques.** El candado
`test_los_pasos_de_carga_son_dos_y_se_mantienen_apretados` vigila las dos mitades juntas:
que los cuatro botones viejos no vuelvan al HTML **y** que sigan estando `pasoRepetible`,
el `pointerdown` y los tres cortes.

**Y en el medio se ESCRIBE la cantidad** (pedido del dueño el mismo día: *«dame la opción
de agregar números para hacer más exacta la carga»*). Queda `− [caja] +`: los pasos
ajustan de a poco y para llegar a 137 se tipea, que con toques eran 137. Dos detalles:

- La caja refleja el número **venga de donde venga** (pasos, Todo/Vaciar, la animación)
  porque se actualiza dentro de `dibujar()`, el único lugar por el que pasa todo. **Salvo
  mientras la están tipeando**: pisarle el valor al usuario en medio de un número la
  vuelve inusable (escribe el «1» de «150» y se lo reemplazan por el valor actual).
- Escucha `input` y no `change`: con `change` el dibujo recién se movería al salir del
  campo, y lo que se quiere es ver la carga mientras se escribe. El campo vacío **no** se
  fuerza a 0 mientras se edita —borrarlo para escribir otro número es normal—; se acomoda
  al salir.

**Y una BARRA DESLIZANTE** (pedido del dueño 07-08 mirando el pallet cargado de
EasyCargo). Quedan **tres controles del MISMO número, no tres estados**: arrastrar da el
barrido rápido y la sensación de «llenar», el campo da el número exacto, los pasos ajustan
de a uno. Los tres entran por `fijar()` —que capa contra 0 y el tope y corta la
animación— y los dos que muestran valor se sincronizan dentro de `dibujar()`, el único
lugar por el que pasa todo. Si alguno se cableara por su cuenta, la pantalla mostraría un
número y dibujaría otro: el defecto que nadie reporta pero que hace desconfiar de la
herramienta. Candado: `test_los_tres_controles_de_cantidad_mueven_el_mismo_numero`.

La barra **se deshabilita con tope 0**, que pasa de verdad: en Sobre pallet con la bolsa de
botellones no entra ni un bulto (mide 130 cm y el pallet 120, §3.3 punto 5), y una barra
que no se puede mover confunde más de lo que informa.

Detalles de implementación que importan:

- **`pointerdown` y no `mousedown`**: un solo handler sirve para dedo y para mouse.
- **El primer paso se aplica AL APRETAR**, no al soltar: un toque suelto tiene que mover
  exactamente uno.
- **Se corta con `pointerup`, `pointercancel` y `pointerleave`.** Sin el `leave`, sacar el
  dedo del botón sin levantarlo dejaba el contador corriendo solo.
- **No se usa `setPointerCapture`**: capturaría el puntero y `pointerleave` no dispararía
  nunca.
- Los handlers van sobre los BOTONES, no sobre el lienzo, así que **no contradicen**
  «zoom solo en escritorio» (`test_el_visor_no_registra_gestos_tactiles` protege el canvas).

### 4.1decies El H3, moldeado sobre sus fotos: `camion_nqr` (11-08-2026)

Cuarta cabina propia. Las fotos son de un **Chevrolet NQR (Isuzu N-Series)** con furgón, y lo
que hay que capturar es que es un **cab-over puro**: no tiene morro ni cuña, la cara es un
plano vertical. Lo que lo distingue de las otras tres, en orden de cuánto se nota:

1. **Parabrisas de una pieza, enorme** — se come el 44% de la cara y baja hasta cerca del
   paragolpes. En el HINO y el HD35 el vidrio es una franja con panel y parrilla debajo; acá
   el panel es finito.
2. **La cara es LISA**: ni marco plateado ni parrilla de listones. Solo el moño dorado al
   centro y una ranura negra angosta.
3. **Dos espejos por lado**: la paleta rectangular sobre brazo tubular Y un **convexo redondo
   adelantado**, a la altura del capó. Ese redondo asomando por delante de la cara es la firma
   del camión de frente.
4. **Techo liso, SIN visera** — el HINO y el HD35 sí la llevan. No se le pone porque las fotos
   no la muestran, mismo criterio que sacó la puerta lateral del furgón (§4.1septies-bis).
5. Faros grandes y claros integrados abajo, en las esquinas del paragolpes.

Verificado por **diferencia de render**: mismo encuadre de costado, el H3 contra el Chevy 3
(que sigue con la genérica) → **17,6% de los píxeles del tercio delantero difieren**. Si la
cabina no se hubiera cableado, caería en la genérica y la diferencia sería ~0.

**Candado nuevo, y es el que faltaba:** una silueta se declara en TRES lugares que tienen que
coincidir —la constante del modelo, el mapa de ejes del controlador y la rama del visor— y si
falta la del visor **no pasa nada visible**: el camión cae en la cabina genérica y se dibuja
como cualquier otro. `test_toda_silueta_declarada_tiene_su_rama_en_el_visor_y_sus_ejes` exige
las tres. (La genérica `camion` se exceptúa a propósito: es el `else` del despacho.)

**Estas fotos resolvieron el enredo de nombres.** Llegaron rotuladas como «H3» y el camión
lleva pintado **«NQR 919»** (patente SXDB-69), que era el identificador del `Chevy 3 (NQR 919)`
dado por vendido el 05-08. En vez de deducir, se preguntó — y el dueño lo confirmó con el jefe:
es **un solo camión con dos nombres**, y nunca se vendió. Ver §3.5bis.

Vale como método: la contradicción se **anotó y se preguntó** en lugar de resolverse a ojo. Es
el reverso del error que costó el ancho del HD35, donde una deducción que reproducía los datos
se tomó por medida.

### 4.1nonies-quinquies El plan se COMPARTE por link, sin login (10-08-2026)

Pedido del dueño mirando el link compartible de EasyCargo: mandarle al cliente o al
conductor una URL con el 3D, sin darle una cuenta. Botón **Copiar link para compartir**
en la sección Descargar.

**No hay tabla ni token guardado, y es la decisión que lo hace barato.** El simulador es
una **función pura de su query string**: los mismos parámetros dan siempre el mismo plan.
Así que **el link ES el escenario**, firmado con la app key. Eso evita una migración, un
modelo, y sobre todo una tabla de links viejos que hay que limpiar y que nadie limpia.

Es la **única pantalla del simulador que se sirve sin login**, así que lo que importa no
es que funcione sino que no se convierta en una puerta:

1. **Firmado** (`signed`, el mismo mecanismo del QR del taller). Sin firma no se puede
   fabricar, y **retocar un parámetro invalida la URL entera** — nadie convierte el link
   de un cliente en otra simulación.
2. **Vence** a los 7 días (`PlanCargaPublicoController::DIAS_VIGENCIA`). Un link eterno es
   una filtración esperando su turno; siete días cubren el ciclo de una cotización.
3. **Solo lectura**: el simulador es una calculadora, no escribe nada.
4. **Sin los controles que navegan hacia adentro.** La bandera `$publico` del partial del
   visor apaga Descargar, Camiones, Pallet y Traer carga. Rebotarían por permiso igual,
   pero mostrarle al cliente que existen no aporta nada. Lo que queda —vistas, cargar,
   rótulos— es mirar el dibujo, que es para lo que se comparte.
5. Con `throttle`, como el resto de lo público.

**Sale del MISMO cálculo que la pantalla interna** (invoca `index()` y lee sus datos), por
el mismo motivo que el Excel: un plan público que difiere del que el vendedor miró antes
de mandarlo es exactamente lo que no puede pasar.

`guest-layout` estrenó un **ancho por token** (`formulario` por defecto, `listado` para
esto): un visor 3D dentro de los 448 px del card de invitado no se puede mirar. Un token
desconocido **revienta**, igual que en `app-layout`.

Y la página **dice que es una referencia y cuándo vence**: afuera de la app, un número sin
contexto se lee como una promesa.

Candados en `PlanCargaCompartidoTest` (13), la mitad de ellos sobre la seguridad: sin firma
no entra, un link retocado deja de valer, el link vence, y la página no ofrece controles
internos.

### 4.1nonies-quinquies-bis El link se compartía ROTO, y los 8 candados no lo vieron (11-08-2026)

Reporte del dueño con captura: *«cuando comparto el link no se ve el detalle específico de
lo que se cargó, no se ve el camión»*. **Dos defectos independientes, apilados, con el mismo
síntoma** — y el link estuvo así desde el día que se publicó.

1. **Faltaba el `<script>` con la escena.** `montarCarga3d` (app.js) sale sin hacer nada si
   no encuentra `#carga3d-datos`; la pantalla interna lo emitía y la pública se olvidó. El
   recuadro salía **vacío**.
2. **El card salía en 448 px.** `GuestLayout` no declaraba la propiedad `$ancho`, así que
   Blade trataba `ancho="listado"` como un **atributo HTML suelto**, la variable nunca
   llegaba al layout, y su `?? 'formulario'` la resolvía al default **en silencio** —
   exactamente lo que el `throw_unless` de al lado decía evitar. El token, el mapa de clases
   y el guard estaban escritos desde el 10-08; lo que faltaba era el enchufe.

**Por qué ninguno de los 8 candados lo vio, que es la lección:** todos preguntaban por
TEXTO —que el título esté, que el aviso de vencimiento esté, que los controles internos no
estén— y el texto estaba perfecto. Ninguno preguntaba si la página podía **dibujar**. Un
test de «la pantalla responde 200 y dice lo que tiene que decir» no prueba una pantalla
cuyo único contenido es un dibujo.

Los dos candados nuevos van contra el mecanismo y no contra el texto: que el JSON de la
escena viaje **y traiga el vehículo adentro** (comparado contra la pantalla interna, para
que no se pueda satisfacer con un `<script>` vacío), y que el card rendee `max-w-6xl` sin
ensanchar de paso el login. Medido en el navegador: **de 33.519 píxeles dibujados a
428.549**.

### 4.1nonies-quinquies-ter Dos vistas del mismo link (11-08-2026)

Pedido del dueño: *«que la otra persona lo pueda ver pero no editar, y si jefatura lo pueda
editar»*.

**La diferencia la hace QUIÉN abre, no la URL**, y eso es lo que la mantiene segura: un
segundo link «editable» sería una puerta al simulador sin login para cualquiera que tenga la
dirección, y tiraría abajo los cinco puntos que protegen el link. El cliente ve exactamente
la página de siempre; quien **tiene el permiso** ve además una banda que le avisa «así lo ve
el cliente» y el atajo **Abrir en el simulador para editar**, que lleva el mismo escenario a
la ruta interna — donde el permiso se vuelve a chequear.

Dos detalles: se pregunta por el **permiso** y no por «estar logueado» (un técnico con
cuenta no puede editar planes y el botón lo mandaría a un 403), y el atajo **no arrastra la
firma** — pegada a una URL interna no sirve, y en un historial compartido es el secreto del
link viajando de más. Candados: `test_quien_no_tiene_permiso_solo_mira`,
`test_quien_puede_simular_ve_el_atajo_para_editarlo` y
`test_el_atajo_lleva_a_una_ruta_que_sigue_pidiendo_permiso`.

### 4.1nonies-quater El plan de carga se BAJA (10-08-2026)

Pedido del dueño: que el resultado deje de vivir solo en la pantalla y sirva para el
andén, el conductor y la cotización. Botón **Plan de carga (Excel)** en la sección
**Descargar** del menú.

**Lo que justifica la planilla no son los números** —esos ya están en pantalla— **sino el
ORDEN DE CARGA**: qué bloque va contra la cabina y cuál contra la puerta. Es el dato que
el andén no puede deducir sin mirar el dibujo, y el que convierte una simulación en una
instrucción. Sale de los bloques de la escena, que ya vienen ordenados fondo → puerta, así
que la planilla **numera lo que el motor decidió**, no reordena nada.

**Sale del MISMO cálculo que la pantalla, literalmente.** `excel()` invoca a `index()` y
lee los datos que le pasó a la vista, sin renderizarla. Es la lección del Excel de la
flota —«el listado y la descarga por el MISMO método»— llevada al extremo que este caso
permite: no hay «un método compartido» que alguien pueda dejar de usar, hay **una sola
ruta de cálculo**. Se puede porque `index()` es una calculadora: valida, calcula y no
escribe nada, así que invocarla no tiene efectos.

Y el enlace **arrastra la query actual entera**, así que baja exactamente lo que se está
mirando: camión, producto, estiba, apilado y las líneas de la carga mixta.

**Sin librerías nuevas**: se apoya en `App\Services\Excel\EscritorXlsx` + `FilasXlsx`, el
escritor compartido extraído el 04-08. Es el tercer Excel que sale de ahí.

La planilla **repite el aviso de que el cupo es un techo** (pasillo 0, factor 1, sin
calibrar). Sacarlo del papel sería prometer, fuera de la app, más de lo que el propio
motor dice que sabe — y el papel es justamente lo que circula por correo.

Candados en `PlanDeCargaExcelTest` (9): permiso, nombre y content-type, partes mínimas,
**XML bien formado en TODAS las partes** con caracteres a escapar, que el cupo coincida
con el de la pantalla, que la mixta viaje con lo que falta **y por qué**, el orden de
carga, el aviso del techo, y que el botón esté DENTRO del menú lateral. Verificado además
**abriendo el archivo con Excel de verdad** (COM): abre sin reparaciones.

### 4.1nonies-ter «¿En cuál conviene?» — multi-camión (10-08-2026)

La pregunta real de Comercial no es *«¿entra en este camión?»* sino **«¿en cuál conviene
mandarlo?»**, y hasta ahora había que cambiar de camión y recalcular de a uno para
saberlo. Ahora una sección **Camiones** del menú responde la MISMA pregunta que se está
haciendo, para toda la flota a la vez:

| Modo | Qué compara |
|---|---|
| ¿Cuánto entra? | unidades que entran en cada camión |
| ¿Cabe esta carga? | si cabe todo, y cuántas unidades entraron |
| Sobre pallet | unidades totales apilando pallets |

**Cada modo compara lo suyo a propósito**: son preguntas distintas y mezclarlas daría una
tabla que no significa nada.

**No hay motor nuevo.** Es el mismo `cupo()`/`carga()` verificado corrido N veces; con tres
camiones y rejilla entera cuesta microsegundos, así que se calcula siempre y no detrás de
un botón. Hoy da: Contenedor 40' 1.620 · HINO 500 1.500 · HD35 420.

Tres decisiones:

1. **Ordenado de mayor a menor**, que es el orden en que se toma la decisión. A igual
   número gana el camión **más chico**: mandar el grande a medio llenar es peor negocio
   aunque quepa lo mismo.
2. **Cada fila es un enlace que conserva todo lo demás** (producto, estiba, apilado y las
   líneas de la carga mixta), así comparar no cuesta rearmar la pantalla.
3. **Con un solo camión no se dibuja.** Una tabla de una fila no ayuda a elegir.

Va **en el menú lateral** y no suelta en la pantalla, como el resto de los controles
(§4.1nonies). Candados: `test_compara_todos_los_camiones_y_ordena_por_lo_que_entra`
—que además exige que la tabla esté DENTRO del `<aside>`— y
`test_con_un_solo_camion_no_se_muestra_la_comparativa`.

### 4.1decies Traer la carga de una planilla (06-08-2026)

Pedido: *«un botón de importar en Excel para que se pueda generar una ruta con facturas,
cargar y hacer una prueba si alcanza todo o no»*.

**Se PEGA, no se sube un archivo.** Al copiar celdas de Excel el portapapeles trae las
columnas separadas por tabuladores, así que se lee sin parsear `.xlsx` y sin pedirle al
usuario que guarde el archivo, lo busque y lo suba. Todo el trabajo es en el cliente
(`importar()` en el `x-data` de la pantalla): normaliza sin tildes ni mayúsculas, busca el
producto en el catálogo, arma las líneas y envía el formulario de carga mixta.

Dos reglas:

- **Lo que no se pudo leer se MUESTRA tal cual vino.** Descartarlo en silencio dejaría al
  usuario creyendo que cargó todo, con un veredicto calculado de menos — el peor error
  posible acá.
- **La pantalla dice lo que NO hace todavía**: no lee facturas ni arma la ruta. Eso engancha
  con Hojas de ruta y es otra pieza. Un botón que promete más de lo que hace se descubre en
  el peor momento. Candado:
  `test_importar_de_excel_esta_ofrecido_y_dice_que_no_lee_facturas`.

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

### 4.3 Dónde vive cada texto de la pantalla (10-08-2026)

Pedido del dueño, dibujado con marcador sobre una captura: **«la descripción del
camión la quiero adentro del cuadrado donde está el camión, para poder usar mejor
el espacio y mejorar la interfaz con tanto texto»**, y el resultado partido en
**dos tarjetas**.

1. **Los datos del camión son una franja del recuadro del visor**, no una tarjeta
   aparte debajo. Adentro se ahorran un borde, una sombra y el hueco entre tarjetas,
   y los datos quedan pegados al dibujo que describen. Va como franja y **no flotando
   sobre el lienzo**: un panel encima taparía el camión — la misma doctrina del menú
   lateral (§4.1nonies). Fondo `neutral-50/70`, el del menú, para que se lea como
   parte del visor y no como contenido metido adentro. *(Estaba AL PIE; desde el
   12-08 va ARRIBA — ver §4.3bis.)*
2. **Nada se dice dos veces.** El rótulo del lienzo repetía el nombre del camión y
   el piso libre, que ya están en la franja: quedó solo la ayuda de manejo
   («arrastrá para girar»). En el plan compartido por link pasaba lo mismo con el
   encabezado, y también se recortó.
3. **El cupo máximo son DOS tarjetas**: a la izquierda **el número** que se vino a
   buscar (entran N, unidades, ocupación con su barra), a la derecha **de dónde
   sale** (cómo viaja, qué se agota primero, rejilla, peso, el aire que queda
   arriba). En una sola tarjeta el dato principal encabezaba seis filas
   etiqueta-valor en una columna angosta, y al lado de un dibujo que ocupa todo el
   ancho se veía como una tira de texto. La **ocupación se fue con el número**
   —la barra es el camión llenándose— y de paso dejó de estar escrita dos veces
   (una fila con el % y abajo una barra sin rótulo).

La lección, que ya se repitió en esta pantalla: cuando el dueño dice «hay mucho
texto» casi nunca sobra un dato, sobra una **repetición** o falta una **agrupación**.
Antes de borrar información, buscar qué está dicho dos veces.

### 4.3bis La respuesta va ARRIBA del dibujo (12-08-2026)

Otro marcador sobre otra captura, y las dos flechas apuntan al mismo lado: *«subir
dentro de la pantalla la descripción del chevy con sus medidas, volumen, carga máxima
y piso libre en la puerta, y el mensaje "NO CABE TODO" arriba, que aparezca cuando no
entra todo»*.

El orden del recuadro del visor pasa a ser, de arriba abajo:

1. **La ficha del camión** (nombre, medidas útiles, volumen, carga máxima, pasillo si
   lo hay, piso libre en la puerta). Es lo que se está mirando: se lee ANTES del
   dibujo. Al pie quedaba **después del tablero de acomodo**, o sea a dos pantallazos
   del camión que describe.
2. **El veredicto**, pegado al borde de arriba del lienzo. Vivía en una tarjeta DEBAJO
   del visor: había que mirar el camión, bajar, y recién ahí enterarse de que no
   entraba. Es la única línea de la pantalla que cambia una decisión comercial.
3. El lienzo, y debajo los avisos de acomodo y el tablero.

**El «cabe» va sobrio y el «no cabe» va rojo a todo el ancho.** Una franja verde
gigante para la respuesta esperada entrena a ignorar la franja — y entonces la roja
tampoco se ve.

**UNA respuesta por pantalla.** Al subir el cartel había que sacarlo de los otros dos
lugares donde ya estaba, o la pantalla lo diría dos veces (que es justo lo que el
§4.3 vino a arreglar): se borró la tarjeta de «Cabe todo / No cabe todo» de
`index.blade.php`, la línea «No cabe todo en un viaje» del plan compartido, y el
recuadro de la prueba («tus 50 entran») del modo cupo máximo.

**En «¿cuánto entra?» el cartel lleva los NÚMEROS**, no la frase pelada: la pregunta
fue «¿me entran 500?», así que la respuesta es *«de tus 500 entran 420, quedan 80
afuera»*. Un «no cabe todo» a secas obligaría a bajar a buscar el número que se vino a
buscar. En cambio, el cupo máximo **sin** cantidad a probar y el armado del pallet **no
muestran cartel**: no responden sí/no.

**El link compartido dice lo mismo con otras palabras.** La versión interna cierra con
«con eso se negocia», que es una instrucción para el vendedor; del otro lado del link
hay un cliente o un conductor y ahí suena a que se está calculando cuánto apretarlo.
Público: *«Queda carga afuera. Abajo, producto por producto.»* Candado:
`PlanCargaCompartidoTest::test_el_link_dice_que_no_cabe_pero_no_habla_de_negociar`.

Los candados miden **posición contra el `<canvas>`** y no la mera presencia del texto:
un `assertSee` seguiría verde con el cartel de vuelta abajo
(`test_el_cartel_de_no_cabe_todo_va_arriba_del_lienzo`,
`test_la_ficha_del_camion_va_arriba_del_lienzo`, `test_el_veredicto_se_dice_una_sola_vez`).

### 4.3ter SACAR UN PRODUCTO TIENE QUE VERSE (12-08-2026)

Reclamo del dueño, textual: *«quiero un botón o una opción para quitar productos porque
siempre comienza la opción con bidones pero no encuentro ninguna opción para quitar o
eliminar»*.

**El botón existía.** Estaba adentro de la tarjeta del producto, al lado de «Duplicar», así
que para descubrir que una línea se podía sacar había que abrirla primero. Es la tercera vez
que pasa lo mismo en esta pantalla —la pestaña de varios productos (§1), el pallet enterrado
en un desplegable (§3.3bis)— y la regla ya no se discute: **una función que no se ve, no
existe**. Cuando el dueño dice «no hay opción para X», antes de agregar X hay que buscar
dónde está X escondido; agregar un segundo camino deja dos.

Ahora el tacho está en los **dos lugares donde se lee la lista de la carga**:

1. **La cabecera de cada producto** del formulario, junto al chevrón — lo que se ve con la
   tarjeta cerrada. Va con `@click.stop`, porque la cabecera entera abre y cierra la tarjeta.
2. **La lista «En el camión»** del panel de cubicar, que es donde él fue a buscarlo: si ahí
   se agrega de a un bulto, ahí se saca.

**Con una sola línea no se ofrece.** Una carga sin ningún producto no es una carga: el
formulario no tendría qué calcular y el validador la rechazaría, así que sería un botón que
solo sabe fallar.

**Sacar desde el panel RECALCULA**, igual que agregar, y con `cubicar=1` para volver con el
panel abierto (§ el pedido de «que no salga todo»). Si la lista se actualizara sola, la lista
diría una cosa y el camión dibujado otra: el dibujo es el último resultado del **servidor**, y
el servidor no se enteró de que la línea se fue. En la cabecera del formulario, en cambio, el
botón solo **ensucia** —«Recalcular · hay cambios»—, que es la convención de todo ese
formulario.

Los candados (`QuitarProductoTest`) **no miran si `quitar` está definido**, que era justo lo
que estaba bien: cortan la pantalla en el límite entre la cabecera y el cuerpo desplegable, y
exigen el control del lado que siempre se ve. El corte se verifica a sí mismo (el trozo
«cabecera» no puede contener el «Duplicar» del cuerpo), y se probó al revés rompiendo el
`@click` a mano: el candado se pone rojo.

**La trampa del nombre**, anotada porque cuesta un assert y se paga una vez: el `x-data` del
panel de cubicar **tapa** el del formulario (por eso conviven dos `agregar`), así que un
método llamado `quitar` ahí adentro se llamaría a sí mismo hasta desbordar la pila. Se llama
`quitarDelCamion` y hay un candado que lo fija.

## 4quater. EL CAMIÓN QUE SALE A MEDIO CARGAR (lote 5, 12-08-2026)

Pasa todo el tiempo: el camión vuelve de un reparto con carga arriba, o se le suma un
pedido a uno que ya está armado. Hasta acá eso se simulaba **a ojo eligiendo un camión
más chico**, que da un número parecido por la razón equivocada — y que además deja el
tope de kilos del camión chico, que no es el que va a viajar.

Dos campos en la carga mixta, detrás de un botón (**«El camión ya lleva carga»**, porque
el caso normal es el camión vacío y dos campos con 0 adentro estorban en cada simulación
para servir en una de cada diez):

- **Piso ya ocupado (cm)** — se descuenta del largo útil, y **corre el arranque** de las
  regiones. La carga vieja va contra la **cabina** porque se subió primero; restarla sin
  correr el origen dibujaría lo nuevo encima de lo que ya viaja.
- **Kilos ya cargados** — salen del tope de peso.

**LOS DOS VAN JUNTOS, y esa es la regla.** Descontar el espacio sin descontar los kilos
deja el **cartel de sobrepeso en verde con el camión pasado**, que es peor que no tener
la función: con 1.200 kg ya arriba de un HD35 (1.400), un pedido de 300 kg se pasa, y
contra el tope entero habría dado verde. Por eso viajan como **un solo parámetro** de
`CamionSimulacion::paraCalculo($ocupadoCm, $ocupadoKg)` y no como dos campos sueltos que
alguien pueda completar a medias. Candado:
`test_los_kilos_que_ya_viajan_salen_del_tope_o_el_cartel_miente`.

**El visor lo dibuja en gris**, translúcido y hasta el techo, y **siempre** —aunque el
lienzo esté en 0 bultos, porque no depende de la animación: ya estaba arriba antes de
empezar. Sin dibujarlo, la carga nueva aparece flotando a dos metros de la cabina y el
hueco se lee como un error del acomodo, que es exactamente lo contrario de lo que pasa.
Va hasta el techo a propósito: no sabemos cómo está estibada la carga vieja, solo que ese
pedazo de camión no está disponible — dibujarla bajita sugeriría lugar que el motor no
está ofreciendo.

**Se dice en los tres lados**: la franja del camión («Ya lleva 2,50 m · 900 kg —
descontado»), el dibujo y el **Excel** («El camión YA SALE CON CARGA… las medidas de
arriba son las del camión vacío»). Un cupo más chico sin decir por qué se lee como un
error del cálculo.

**El recorte al largo del camión se hace en el controlador, no en la validación**: el
tope real depende de CUÁL camión es, y ponerlo en las reglas dejaría el formulario
inválido de golpe al cambiar de camión. Se recorta y la pantalla lo dice (`recortado`).

### 4quater-bis CÓMO CAE EL PESO ENTRE LOS EJES (12-08-2026)

Los datos llegaron el 12-08 y la función se construyó ese día. **Responde la otra mitad
de la pregunta del peso:** los kilos totales dicen si te pasás de la carga máxima; esto
dice si están puestos donde corresponde. Un camión puede ir **por debajo del tope y aun
así llevar el eje trasero pasado** — y si la carga va muy atrás, el eje delantero se
aliviana, que es un problema de dirección y de frenos, no de multa.

**DOS NÚMEROS Y UNA SOLA REFERENCIA.** Todo el simulador mide desde el **frente de la
caja de carga** (el x = 0 del motor y del visor), así que los ejes se anotan contra ese
mismo punto: `entre_ejes_cm` y `eje_trasero_cm`. El eje delantero **no se guarda**: sale
de restar, y da negativo porque está bajo la cabina. Un tercer número podría contradecir
a los otros dos. Mezclar referencias —uno desde el paragolpes, otro desde la cabina— es
la forma segura de que el brazo de palanca salga mal y nadie lo note.

**La física es una palanca de dos apoyos** y es exacta para lo que se pregunta: se toma
el CENTRO de cada bloque —adentro del bloque la carga es una rejilla pareja, eso lo
garantiza el motor— y se reparte en proporción inversa a la distancia a cada eje. La
fracción **no se acota a [0, 1]**: si la carga queda detrás del eje trasero, el delantero
recibe negativo, y eso no es un error de cuenta sino el camión levantando la trompa. Se
avisa en rojo en vez de esconderlo con un `min()`.

**Lo que el cálculo NO incluye, y la pantalla lo dice:**

- **El peso del camión vacío.** Reparte solo la carga. La tara y cómo apoya no están
  medidas, y sumarlas de memoria convertiría un número exacto en una estimación
  disfrazada. Sirve igual para lo que se usa: comparar dos formas de acomodar lo mismo.
- **El peso del camión vacío** (ver arriba).

#### ¿Se pasa de un eje? (12-08-2026)

Pedido del dueño: *«decime cuánto aguanta cada eje y si me pasé, para evitar una multa,
que salga un mensaje en rojo»*. **En la balanza no se pesa el camión entero: se pesa eje
por eje**, así que un camión por debajo de su carga útil total puede tener el trasero
pasado y lo paga igual. El aviso va con el mismo peso visual que el «No cabe todo»,
porque es la misma clase de noticia: algo que hay que cambiar antes de salir.

Dos campos por camión: `eje_delantero_max_kg` y `eje_trasero_max_kg`.

**`null` NO es `false`.** «No sé cuánto aguanta» y «entra» son cosas distintas: si sin el
tope se devolviera `false`, la pantalla mostraría verde sobre un camión que puede estar
pasado — justo la multa que esto viene a evitar. Sin el dato solo se muestra el reparto,
y el pie dice dónde buscarlo. Candado: `test_sin_el_tope_del_eje_no_se_afirma_que_entra`.

**No se siembra ningún valor por defecto, y es deliberado aunque deje la función
esperando el dato.** El límite que manda es el **menor** entre el máximo LEGAL por tipo
de eje (lo que mira la balanza) y el máximo del FABRICANTE para ese eje. Sembrar el legal
«para que funcione» daría verde a un camión chico con el eje pasado de fábrica; sembrar
el del fabricante de memoria es peor todavía. Los dos están escritos en el **padrón / la
revisión técnica** de cada vehículo, que es la misma fuente que usa quien fiscaliza.

**Los pesos del catálogo se completaron el 12-08** (dueño): bolsa de 10 L **2 kg** (400 g
por botellón — los dos datos cierran entre sí), caja de soportes **6 kg**, caja de tapas
**5,5 kg**. Con eso ya no queda ningún bulto del catálogo sin peso, así que cualquier
carga se puede repartir entre los ejes y disparar el sobrepeso.

**UN PRODUCTO SIN PESO NO HACE DESAPARECER LA SECCIÓN: LA EXPLICA.** La mitad del
catálogo tiene `peso_kg` en null a propósito. La primera versión devolvía `null` y la
sección se esfumaba — se veía en una carga y no en la otra, sin ninguna pista. Ahora
vuelve con el nombre del que falta pesar («no se puede repartir esta carga» si no hay
ninguno con peso, «el reparto deja afuera X» si es parcial). El hueco apareció probando
en el navegador, no en la suite.

**Solo el Chevy 3 tiene los dos datos** y es el único que muestra el reparto. Los otros
tres quedaron sin medir a propósito y sus `notas` en el seeder dicen exactamente qué
falta; el candado `test_el_chevy_sembrado_es_el_unico_con_los_ejes_medidos` se pone rojo
el día que alguien los complete «para que funcione».

| Camión | Qué llegó | Qué falta |
|---|---|---|
| **Chevy 3 (NQR 919)** | entre ejes 417,5 cm · posterior de cabina al eje trasero 360 cm (12-08) | nada (417,5 → 418: el módulo trabaja en cm enteros y medio cm sobre 4 m es 0,12%) |
| HINO 500 FC 1118 | entre ejes 435 cm (12-08) · **frente de la caja al eje trasero 499 cm (13-08)** | **confirmar la distancia entre ejes**: los dos números no cierran (ver abajo). El 499 está sembrado; `entre_ejes_cm` sigue en null |
| Hyundai HD35 | «114,5 cm aprox» (12-08) · **ficha con silueta acotada (13-08)**: largo 6.110 · ancho 1.920 · alto 2.150 · entre ejes **3.415** · voladizos 1.075 y 1.620 mm | del frente de la caja al centro del eje trasero. La ficha da el entre ejes pero **no** dónde arranca la caja (ver abajo) |
| Contenedor 40' | la ficha del **Actros 2545 LS** (12-08) | esa ficha es del TRACTO, y la carga va sobre el SEMI (ver abajo) |

#### Los dos números del HINO no cierran, y el que manda es el 499 (13-08-2026)

`499 − 435 = +64`: con esos dos datos el eje **delantero** caería 64 cm **adentro** de la
caja de carga. En un cab-over la cabina va sobre el eje delantero, así que ese eje está
siempre **adelante** del frente de la caja — en el Chevy 3, el único medido, da **−58 cm**.
Y el error no sería neutro: un +64 le saca kilos al eje **trasero**, que es justo el que se
pasa. Falso verde, que es lo que este cálculo existe para evitar.

El 499 es además el que cuadra con el resto: la caja mide 797 cm, así que detrás del eje
trasero quedan `797 − 499 = 298 cm` de voladizo. Sobre 435 cm entre ejes eso es el **68%**
—arriba del límite legal del 60%—; sobre ~557 da 53%, normal. Un HINO 500 con 5.530 mm
entre ejes es una versión de catálogo. Así que **se sembró el 499** y falta confirmar el
entre ejes en el padrón o la revisión técnica.

#### La ficha del HD35 contradice el «281» y no contesta lo que falta

La silueta acotada que llegó el 13-08 da **3.415 mm entre ejes**, no los 281 cm que estaban
anotados como «ya sabemos» (ese número no tenía fuente en el repo). Y el 3.415 es el que
cuadra con la caja de 430 cm: con 281 el voladizo trasero saldría arriba del 80% del entre
ejes, imposible.

Lo que la ficha **no** dice es dónde arranca la caja, que es de donde se mide. Se puede
deducir con un supuesto: si el fondo de la caja va al ras del final del chasis, entonces
`frente de la caja → eje trasero = largo exterior de la caja − 162 ≈ 440 − 162 = 278 cm`.
Y 278 ≈ 281 — lo más probable es que **el «281» sea justamente esta medida**, anotada por
error como distancia entre ejes. No se siembra por probable: lo resuelve una pregunta
(¿de qué a qué se midió el 281?) o una huincha.

**El contenedor es otra cuenta, no la misma con otros números.** En un tracto + semi la
carga se parte entre los ejes del semi y la **quinta rueda**, y recién de ahí baja a los
ejes del tracto: no es la palanca de dos apoyos que resuelve `RepartoPorEje`. Hacen falta
dos medidas del semi que no están —del frente de la caja a la quinta rueda, y de la
quinta rueda al centro del tren de ejes—. Cargar ahí los números del Actros daría un
reparto con cara de exacto que describe otro vehículo. De la ficha sí sirve, para cuando
se haga: capacidades 7.500 (delantero) / 7.500 y 13.000 (traseros), tara 8.168 kg, PBV
25.000 y PBVC 45.000.

### Lo que NO entró del lote 5, y por qué

- **Traer las líneas desde Comercial.** Falta un puente que no existe: la fuente real de
  líneas es `DocumentoVenta` + `DocumentoVentaDetalle` (el espejo de Bsale, con producto
  y cantidad), pero **`TipoBulto` no tiene ninguna relación con `Producto`** — el
  simulador no sabe que «Botellón 20 L» del catálogo es la bolsa de 5. Hay que decidir
  primero cómo se mapea un producto a su bulto (y cuántas unidades entran en uno).

## 4quinquies. MULTI-DROP: lo que baja primero se carga último (lote 6, 12-08-2026)

Un reparto con varias paradas tiene una restricción que **no es una preferencia de
acomodo**: si la mercadería de la parada 3 viaja contra la puerta, en la parada 1 hay
que **bajarla a la vereda** para llegar a lo que sí se entrega ahí, volver a subirla, y
repetirlo en cada parada.

Cada línea lleva un campo **«Baja en la parada»** (1 a 20; vacío = una sola entrega, que
es el caso de siempre y no mueve ningún número). El motor ordena las paradas **al revés**:
la última se carga primero y queda contra la cabina, y la parada 1 termina junto a la
puerta, que es de donde se descarga.

**La parada manda sobre el volumen y sobre la base.** En el comparador de `orden()` va
antes que los dos: se puede negociar qué producto va abajo, no en qué orden se baja del
camión. Y como el criterio vive en `orden()`, **todos los planes** que prueba el motor
(§ el de «varios acomodos») respetan la secuencia por construcción — la base y el volumen
solo reordenan *dentro* de una parada.

**Va DESPUÉS de `abierta`, y eso tiene una consecuencia que la pantalla dice.** Una línea
«lo que quepa» se acomoda en lo que sobra, así que **siempre** termina contra la puerta,
sin importar a qué parada se la haya asignado. Lo mismo las líneas **sin parada**: caen al
final y salen en la primera entrega. El aviso lo dice con todas las letras («si van a
otra, ponéles el número») en vez de esconderlas dentro de la parada 1.

**Dos listas, dos lectores, dos órdenes** — y por eso no se unificaron:

| | Para quién | En qué orden |
|---|---|---|
| «El reparto, parada por parada» (pantalla) | El **chofer** | De ENTREGA: parada 1 primero |
| «Orden de carga» + columna «Baja en» (Excel) | El **andén** | De CARGA: del fondo hacia la puerta |

La columna «Baja en» del Excel **solo aparece si hay paradas**: vacía en toda carga normal
sería ruido en la hoja que se imprime.

**Los candados miden el ORDEN DE COLOCACIÓN, no la coordenada `x`.** Dos bloques angostos
entran uno al lado del otro y los dos arrancan en x = 0, así que comparar la x no prueba
nada — se compara la posición en la lista de bloques de la escena, que viene ordenada
fondo → puerta. Ver `SimuladorCargaMixtaPantallaTest::ordenDeCarga()`.

### Lo que falta para cerrar el lote 6

Traer las paradas **desde una Hoja de ruta** en vez de tipearlas. Está bloqueado por lo
mismo que las líneas desde Comercial (§4quater): `HojaDeRuta` ya tiene sus `paradas()`,
pero para convertir lo que se entrega en cada una en bultos hace falta el puente
**producto → tipo de bulto**, que todavía no existe.

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
