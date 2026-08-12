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

## B) Dónde cae el eje trasero — 2 medidas de huincha

Falta lo mismo en dos camiones: **del frente de la caja de carga** (la pared de atrás de la
cabina, donde empieza a apoyar la mercadería) **al centro de la rueda trasera**.

| Camión | Medida | Ya sabemos |
|---|---|---|
| Hyundai HD35 | | Distancia entre ejes 281 cm |
| HINO 500 FC 1118 | | Distancia entre ejes 435 cm |

**Cómo:** una huincha, 5 minutos por camión. Es una sola medida, en línea recta, por el
costado del camión.

> ⚠️ El **114,5 cm** que llegó del HD-35 el 12-08 es del **paragolpes al eje delantero**. Es
> otra medida y no sirve para esto: el cálculo necesita la distancia desde donde **empieza la
> carga**, no desde la trompa.

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
