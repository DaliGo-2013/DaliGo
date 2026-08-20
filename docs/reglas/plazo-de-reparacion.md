# Plazo de reparación: al cliente se le dice el plazo, no una fecha

> Decisión del dueño, 14-08-2026. Motivo textual: *"no quiero que la app lo calcule,
> solo diga 15 días hábiles o 10 días, después que el cliente lo calcule por sí solo,
> porque ha pasado casos que el técnico se enferma o está de vacaciones y si uno
> promete una fecha y no cumple es mucho compromiso y hay quejas y reclamos"*.

## El problema que resuelve

La app calculaba la fecha de entrega (ingreso + N días hábiles, saltando fines de
semana y feriados) y la escribía en el correo de ingreso y en las dos pantallas de
"listo" del QR. Una fecha escrita es un **compromiso**: el taller es una sola
persona, y cuando el técnico se enferma o sale de vacaciones el compromiso no se
cumple. El reclamo no es por el atraso, es por la fecha prometida.

Un plazo en días hábiles dice lo mismo sin fijar el día: el cliente lo cuenta.

## La regla

1. **Al cliente se le informa el PLAZO en días hábiles**, nunca una fecha calculada.
   Son tres superficies y las tres dicen lo mismo: el correo de ingreso, la pantalla
   de listo del QR y la del ingreso por cantidad.

2. **El plazo lo pone la sucursal que recibe**, no el texto de la plantilla:

   | Sucursal | Plazo | Por qué |
   |---|---|---|
   | Mirador (`MIRADOR`) | 10 días hábiles | repara ahí mismo (casa matriz) |
   | Coquimbo (`COQUIMBO`) | 15 días hábiles | manda el equipo a Mirador |
   | Abate Molina (`ABATE-MOLINA`) | 15 días hábiles | manda el equipo a Mirador |

   Vive en `config/servicio_tecnico.php` → `dias_reparacion` (por **código** de
   sucursal) + `dias_reparacion_default` (15). Un número fijo en la plantilla
   prometería 10 días en una sucursal que tarda 15. Ver
   [traslado-maquinas-a-reparar.md](traslado-maquinas-a-reparar.md) para el viaje que
   explica esos 5 días de diferencia.

3. **El código de la sucursal es una LLAVE, no un rótulo** (14-08-2026). El plazo se
   busca por código, y esa búsqueda es un índice de array de PHP: distingue mayúsculas.
   En producción el código de la casa matriz estaba guardado como `Mirador` —lo retipeó
   alguien al editar la ficha, junto con `Coquimbo`— y con eso Mirador caía al default de
   15 días hábiles: **el correo prometía 15 donde la regla dice 10**, que es la diferencia
   exacta del correo real que mostró el dueño (ingreso 06-08 → entrega 27-08 en vez del
   20-08). Nadie lo vio porque todo lo demás que usa el código pasa por SQL (`whereIn`) y
   en MySQL eso es case-insensitive por colación: la sucursal aparecía en el selector del
   QR y funcionaba todo, menos el número que el cliente recibe por escrito.
   Queda cerrado por tres lados: el formulario normaliza al guardar
   (`Sucursal::normalizaCodigo`), el accessor compara normalizado, y una migración one-shot
   arregló los códigos ya guardados.

   Y por un cuarto, que es el que evita la próxima variante: **el listado de Sucursales
   muestra el plazo de cada una** («Taller: hasta 10 días hábiles») con una ⓘ que dice que lo
   decide el código. Antes esa pantalla mostraba el código **sin su consecuencia**, y por eso
   el defecto vivió siete semanas a la vista de todos. Solo se muestra en las que **reciben**
   taller (`sucursales_recepcion`): en Buzeta sería un número que no se usa. Si una sucursal
   no tiene plazo propio en config, el listado lo dice — **«(por defecto)»** — porque un plazo
   heredado se ve igual que uno decidido.

4. **Sin sucursal no se promete plazo** (ingreso por ruta): se omite la línea en vez
   de inventar un número.

5. **La fecha estimada NO se eliminó, se dejó de prometer.** `ordenes_servicio.fecha_entrega`
   sigue calculándose y guardándose: la usan el flujo de salidas del Inicio y el
   informe de gestión, y el taller la ve en la ficha. Lo que cambia es a quién se le
   muestra — adentro sí, al cliente no. El campo de la ficha lo dice en su ayuda, para
   que nadie la prometa por teléfono.

6. **El plazo se dice con "hasta"** ("hasta 10 días hábiles"), y el correo agrega que
   se cuenta en días hábiles y puede variar según el diagnóstico.

## Candados

`tests/Feature/PlazoSinFechaPrometidaTest.php` (6): las tres superficies del cliente
no llevan la fecha y sí el plazo, los tres plazos por sucursal son los que dictó el
dueño, y la ficha interna **sigue** mostrando la fecha estimada — ese último es el que
distingue "no se le manda al cliente" de "se eliminó de la app".

`tests/Feature/Admin/InformacionImportanteCorreoTest.php` cubre el plazo dentro del
bloque «INFORMACIÓN IMPORTANTE» del correo de ingreso, incluido el caso sin sucursal.

Sobre el código como llave: `PlazoSinFechaPrometidaTest::test_el_plazo_no_depende_de_como_se_tipeo_el_codigo`
(`Mirador`, `mirador`, ` MIRADOR ` → los tres 10) y, en
`tests/Feature/Admin/SucursalManagementTest.php`, que el formulario guarda el código en
mayúsculas al crear y al editar, que la migración normaliza los ya guardados y que **no**
pisa un código ya ocupado (dos sucursales que difieren solo en mayúsculas son un duplicado
y se resuelve moviendo órdenes, no en una migración a ciegas).

Sobre el plazo a la vista, en el mismo archivo (4, mutados): el listado lo muestra en las que
reciben taller, **no** lo muestra en las que no, avisa «(por defecto)» cuando la sucursal no
tiene plazo propio y **no** lo avisa cuando sí lo tiene.
