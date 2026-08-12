# 03 — Números que no cierran

Dos datos que llegaron por caminos distintos y **se contradicen**. No son urgentes: en los dos
casos el sistema se quedó con el número **más conservador**, que es el que no hace prometer de
más. Pero conviene confirmarlos alguna vez.

---

## A) El contenedor: ¿28.800 kg o 30.000 kg?

| Fuente | Número | Fecha |
|---|---|---|
| **Placa del contenedor** (42G1, CU.CAP. 67,7 m³, NET) | **28.800 kg** | — |
| Lista de «tonelaje oficial» del dueño | 30.000 kg | 11-08-2026 |

**El sistema usa 28.800**, por dos razones:

1. Es un número **leído de la placa**, no de memoria. 30.000 es redondo, y los números
   redondos suelen ser aproximaciones o el dato de otro equipo.
2. 30.000 se parece mucho al **peso bruto** (contenedor + carga), no a la carga neta. Si fuera
   así, usarlo como carga máxima haría prometer 1.200 kg que no existen.

**Cómo se confirma:** mirar la placa metálica del contenedor y leer la línea que dice `NET` o
`MAX CARGO`. Si dice 30.000, se cambia y listo.

**Riesgo de no hacerlo:** se está dejando capacidad sobre la mesa (hasta 1.200 kg por viaje)
si el número bueno resultara ser el otro. Nunca al revés — no se puede pasar por esto.

---

## B) El HD35 acostado: ¿360 o 480 bolsas?

| Fuente | Número | Fecha |
|---|---|---|
| **Cálculo con las medidas de huincha** (ancho 200 cm) | **360 bolsas** | 11-08-2026 |
| Reporte del dueño | 480 bolsas | 07-08-2026 |

**El sistema dice 360.** La cuenta cierra sola: la bolsa acostada mide 51 cm de ancho, y en
200 cm entran 3 columnas (153 cm). La cuarta pediría 204 cm — faltan 4 cm.

Para que dieran 480 tendrían que entrar **4 columnas**, o sea que el camión midiera al menos
204 cm de ancho útil.

**Las tres explicaciones posibles**, y todas se resuelven mirando el camión una vez:

1. El ancho útil es un poco mayor que los 200 cm medidos (¿se midió entre los arcos y no en el
   piso?).
2. Las bolsas de esa carga iban acomodadas de otra forma, no todas acostadas igual.
3. El 480 fue un conteo de memoria de una carga particular.

**Cómo se confirma:** medir el ancho del piso del HD35 en el punto más angosto. Si da 204 o
más, el simulador pasa a decir 480.

**Estado:** el número quedó sin explicar y **el simulador no lo persigue** — está escrito así
en el candado `CalculoDeCargaTest::test_el_hd35_medido_da_420_de_pie_y_360_acostado`. No se
ajustó el motor para llegar a 480, porque eso sería mover la cuenta hasta que dé el resultado
esperado.

> El **420 de pie** sí coincide con lo que el dueño verificó en terreno. Es la referencia que
> confirma que las medidas de huincha son buenas: la discrepancia es solo en el acostado.
