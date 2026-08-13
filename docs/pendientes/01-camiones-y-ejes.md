# 01 — Camiones y ejes

**Qué enciende:** el reparto de peso entre eje delantero y trasero, y el **aviso rojo de eje
pasado**. En la balanza no se pesa el camión entero, se pesa eje por eje: se puede estar
dentro de la carga máxima y aun así ser multa.

**Estado hoy:** funciona solo en el **Chevy 3**, que es el único con los ejes medidos.

---

## A) Cuánto aguanta cada eje — 8 números

| Camión | Eje delantero (kg) | Eje trasero (kg) |
|---|---|---|
| Chevy 3 (NQR 919 · H3) | | |
| HINO 500 FC 1118 | | |
| Hyundai HD35 | | |
| Contenedor 40' (semi Tremac) | | |

**Dónde está:** en el **padrón** o en la **revisión técnica** de cada vehículo. Suele figurar
como «peso máximo por eje» o «capacidad por eje».

**Quién:** quien tenga la carpeta de documentos a mano. Es copiar 8 números.

**Nota técnica:** las columnas ya existen en la base (`eje_delantero_max_kg`,
`eje_trasero_max_kg`), vacías a propósito. Mientras estén vacías el sistema **no dice que
entra** — dice que no sabe. Eso es deliberado: un verde falso sobre un camión pasado es
justamente la multa que esto viene a evitar.

---

## B) Dónde cae el eje trasero

La medida es **del frente de la caja de carga** (la pared de atrás de la cabina, donde
empieza a apoyar la mercadería) **al centro de la rueda trasera**.

| Camión | Frente de la caja → eje trasero | Distancia entre ejes | Qué falta |
|---|---|---|---|
| HINO 500 FC 1118 | **499 cm** ✅ (13-08) | 435 cm ⚠️ **no cierra** | confirmar el entre ejes |
| Hyundai HD35 | — | 3.415 mm 📄 (ficha 13-08) | la medida de huincha |

**Cómo:** una huincha, 5 minutos. Es una sola medida, en línea recta, por el costado del
camión.

### ⚠️ HINO: los dos números no pueden ser los dos ciertos

`499 − 435 = 64`. Eso pondría el eje **delantero** 64 cm **adentro** de la caja de carga, y
en este tipo de camión la cabina va **sobre** el eje delantero: ese eje siempre queda
adelante de donde arranca la caja (en el Chevy 3, el único medido, queda 58 cm adelante).

Se guardó el **499** porque es el que cuadra con el resto del camión: la caja mide 797 cm, o
sea que detrás de la rueda trasera quedarían 298 cm de caja volando. Sobre 435 cm entre ejes
eso es el 68% —más de lo que permite la ley (60%)—; sobre unos 557 cm da 53%, que es normal.
Un HINO 500 con 5,53 m entre ejes es una versión de fábrica.

**Lo que hace falta:** la distancia entre ejes del HINO, del **padrón** o de la **revisión
técnica** (ahí figura). Mientras no llegue, el reparto de peso de este camión no se muestra.

### 📄 HD35: la ficha llegó, pero mide desde la trompa

La silueta acotada del 13-08 dice: largo total **6.110 mm**, ancho **1.920**, alto **2.150**,
entre ejes **3.415**, del paragolpes al eje delantero **1.075** y del eje trasero al final del
chasis **1.620**.

Dos cosas:

1. **El «281 cm entre ejes» que estaba anotado acá no era de este camión** (o no era el entre
   ejes): la ficha dice 3.415 mm, y es el que cuadra con una caja de 430 cm.
2. **La ficha sigue sin contestar lo que falta**, porque no dice dónde arranca la caja. Se
   puede deducir con un supuesto: *si el fondo de la caja va al ras del final del chasis*,
   entonces del frente de la caja al eje trasero hay `440 − 162 = 278 cm`. Y 278 se parece
   demasiado a 281 como para ser casualidad.

**Una pregunta lo resuelve sin salir de la oficina:** ¿el «281 cm» lo midieron **del frente
de la caja al centro de la rueda trasera**? Si sí, ya está: entra tal cual y el HD35 empieza a
repartir peso. Si no, es una huincha de 5 minutos.

> ⚠️ El **114,5 cm** del 12-08 es del **paragolpes al eje delantero** (la ficha lo confirma:
> 1.075 mm). Es otra medida y no sirve para esto: el cálculo necesita la distancia desde donde
> **empieza la carga**, no desde la trompa.

---

## C) El contenedor — 2 medidas del semirremolque

El contenedor va sobre el **semirremolque Tremac**, tirado por el Mercedes Actros. La carga
apoya sobre el semi, así que las medidas que importan son las del semi.

1. Del **frente de la caja** a la **quinta rueda** (el plato donde engancha el tracto): ____
2. De la **quinta rueda** al **centro del tren de ejes** (el conjunto de ruedas de atrás): ____

> ⚠️ La ficha del Actros que llegó el 12-08 (voladizo 90 cm, entre ejes 3.250 mm) describe el
> **camión que tira**, no el que lleva la carga. Sirve para otra cosa, no para esto.

---

## Cuando lleguen los datos

Se cargan en `database/seeders/CamionesSimulacionSeeder.php` y hay un candado
(`RepartoPorEjeTest::test_el_chevy_sembrado_es_el_unico_con_los_ejes_medidos`) que se pone en
rojo a propósito el día que alguien complete un camión — para que sea una decisión consciente
y no un relleno.
