# 02 — Bultos por medir

**Qué enciende:** poder simular la carga de las máquinas. Hoy **no están en el catálogo** de
bultos, así que no se pueden meter en un camión desde la lista.

**Salida por ahora:** se pueden cargar igual con **«Bulto a medida»** (el panel de CUBICAR),
escribiendo las medidas en el momento. Sirve para una cotización puntual; lo que no da es
tenerlas guardadas para elegirlas de una lista.

---

## Las seis que faltan

| Jaula | Largo | Ancho | Alto | Peso |
|---|---|---|---|---|
| Llenadora 100 botellones | | | | |
| Llenadora 200 botellones | | | | |
| Planta de ósmosis 500 | | | | |
| Planta de ósmosis 1 tera | | | | |
| Lavadora de botellones | | | | |
| Sopladora | | | | |

---

## Cómo se miden

- **Se mide la jaula de madera CON el pallet**, no la máquina desnuda. Lo que viaja en el
  camión es el bulto completo.
- El **alto** se toma hasta el punto más alto de la jaula, incluido el pallet de abajo.
- El **peso** puede ser el de la etiqueta de despacho si lo tiene.

## Lo que ya está decidido para ellas

Estas dos cosas no hace falta preguntarlas de nuevo, ya se sabe y quedan escritas para cuando
se carguen:

- **No soportan peso encima** — el rotulado de fábrica dice que la tapa puede colapsar. Nada
  se apila arriba de una jaula.
- **Orientación fija** — van a lo largo, pegadas a un costado y amarradas. El motor no las
  puede girar para hacerlas entrar.

---

## Dónde está anotado en el código

`database/seeders/TiposBultoSeeder.php`, bloque de comentario «PENDIENTE DE MEDIR» al final.
No se siembran con medidas estimadas a propósito.
