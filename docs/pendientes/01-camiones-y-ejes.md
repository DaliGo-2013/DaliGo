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

**Dónde está:** en la **revisión técnica**. ⚠️ **En el padrón NO está** — lo verifiqué el
14-08-2026 con los padrones del HINO y del HD35 que mandó el dueño: traen carga, peso bruto,
motor, chasis y VIN, pero **ninguna línea de ejes**. La factura del HD35 trae la disposición
(`S(2)-S(4)`: eje simple adelante, simple con rueda doble atrás) pero no cuánto aguanta cada uno.

**Quién:** quien tenga la carpeta de revisiones técnicas. Es copiar 8 números.

> 💡 Los padrones **no fueron un viaje perdido**: destaparon que el HINO tenía cargados los
> 11.000 kg del **peso bruto** en vez de los **8.000 kg de carga** — el simulador prometía tres
> toneladas de más. Ya está corregido (ver `docs/reglas/simulador-de-carga.md` §3.5quater).

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
| Hyundai HD35 | ¿el «281 cm»? ❓ | ¿el «281 cm»? ❓ | **saber qué era el 281** (ver abajo) |

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

### ⚠️ HD35: la ficha acotada del 13-08 NO es de este camión

El padrón y la factura (14-08) dicen que es un **HD 35 2.5, peso bruto 3.500 kg, carga 1.500,
carrocería furgón, 4×2**. Un HD35 de 3,5 t mide unos 5,0–5,6 m de largo con 2,6–3,0 m entre
ejes. La ficha que llegó el 13-08 dice **6.110 mm de largo y 3.415 entre ejes**: ese es un
camión bastante más grande (clase HD65/HD72, 6,5–7,5 t).

**Conclusión: no se usan esos 3.415 mm.** Y de paso, el «281 cm» que estaba anotado como
distancia entre ejes vuelve a ser plausible — 2.815 mm es una distancia real de HD35 con la
batalla larga. Así que la pregunta de abajo sigue siendo la que hay que contestar, y con más
razón: no se sabe si el 281 era el entre ejes o la medida que falta.

Para el registro, la ficha del 13-08 decía: largo total **6.110 mm**, ancho **1.920**, alto
**2.150**, entre ejes **3.415**, del paragolpes al eje delantero **1.075** y del eje trasero al
final del chasis **1.620**. **Se descarta** por lo de arriba: describe un camión más grande. Lo
único que confirma es que el **114,5 cm** del 12-08 era del paragolpes al eje delantero (la
ficha da 1.075 mm), o sea otra medida y no la que hace falta.

### La pregunta que cierra el HD35

**¿El «281 cm» lo midieron del frente de la caja (la pared de atrás de la cabina) al centro de
la rueda trasera, o es la distancia entre ejes?**

- Si es del **frente de la caja a la rueda**: entra tal cual como `eje_trasero_cm` y solo falta
  el entre ejes (que en un HD35 2.5 es de fábrica: 2.615 o 2.815 mm según la batalla).
- Si es el **entre ejes**: entonces falta la otra, y es una huincha de 5 minutos por el costado
  del camión.

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
