# 04 — Traer la carga sin tipear

**El problema:** hoy la simulación se arma **línea por línea a mano**. Para un camión con
ocho tipos de bulto son ocho cargas manuales, cada vez.

---

## Primero, lo que hay hoy (para no confundirlo)

| Función | Existe | Qué hace |
|---|---|---|
| **Bajar el plan de carga en Excel** | ✅ Sí | Descarga el resultado: qué va, cuánto y en qué orden de estiba |
| **Compartir el plan por link** | ✅ Sí | Link firmado, sin login, para mandarle al conductor o al cliente |
| **Importar CSV de productos** | ✅ Sí | Está en **Catálogo de productos**, no en el simulador. Carga el maestro de SKU |
| **Subir un Excel de carga al simulador** | ❌ No | No existe |

> El simulador **baja** Excel, no **sube**. Son cosas distintas: hoy la planilla es la salida,
> no la entrada.

---

## Los dos caminos posibles

Son dos funciones distintas, con costos distintos. La diferencia está en **qué dice la lista
que se sube**.

### Camino A — subir o pegar una lista de BULTOS ✅ no espera nada

La lista viene con **bultos del catálogo** y cantidades:

```
Bolsa 5× botellón 20 L    120
Caja de soportes           40
Pallet de cajas de tapas    6
```

Como el bulto ya existe en el catálogo con sus medidas y su peso, **no hace falta ninguna
decisión previa**. Se puede construir cuando quieras.

- Sirve para: armar rápido una simulación que hoy se tipea a mano.
- No sirve para: partir de un pedido de un cliente, porque el cliente pide productos, no bultos.

### Camino B — traer desde un documento de venta o una hoja de ruta ⏸ espera la decisión

La lista viene con **productos** (botellón 20 L, dispensador, tapas…) porque así es como se
vende y como sale en la hoja de ruta.

Y ahí aparece el hueco: el sistema **no sabe en qué bulto viaja cada producto**.

- ¿120 botellones de 20 L son 24 bolsas de 5? ¿O van sueltos?
- ¿Las tapas van en caja de tapas? ¿Cuántas por caja?
- ¿El dispensador tiene bulto propio o va en jaula?

Sin eso, el sistema puede leer el pedido pero no sabe cuánto espacio ocupa.

---

## La decisión que falta (Camino B)

Es **una decisión de bodega, no una medida**. Para cada producto del catálogo que se despacha:

| Producto | ¿En qué bulto viaja? | ¿Cuántas unidades entran en uno? |
|---|---|---|
| Botellón 20 L | Bolsa 5× botellón 20 L | 5 |
| Botellón 10 L | Bolsa 5× botellón 10 L | 5 |
| Tapas | Caja de tapas | ? |
| Soportes | Caja de soportes | ? |
| … | | |

Los dos primeros son obvios y ya se pueden dar por hechos. El resto lo tiene que decir alguien
que cargue camiones.

**Quién:** vos o el encargado de bodega. No hace falta que esté completa de una: alcanza con
empezar por **los cinco productos que más se despachan**, y el resto queda para tipear a mano
hasta que se agreguen.

---

## Recomendación

**Si en algún momento se retoma esto, arrancar por el Camino A.** No espera a nadie, se hace
en una sesión, y resuelve el 80% de la molestia real (volver a tipear la misma carga).

El Camino B es más potente pero arrastra la decisión de arriba, y además toca datos que vienen
de Bsale — conviene hacerlo con la lista de bultos ya definida.

---

## Nota técnica

Los documentos de venta (`DocumentoVenta`, `DocumentoVentaDetalle`) y las hojas de ruta
(`HojaDeRuta`, `HojaRutaParada`) **ya viven adentro de DaliGo**. Para el Camino B no hace falta
ningún Excel: sería exportar del sistema para volver a importar al mismo sistema. Lo que falta
es puramente el puente producto → bulto.
