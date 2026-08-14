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
   | El Mirador (`MIRADOR`) | 10 días hábiles | repara ahí mismo (casa matriz) |
   | Coquimbo (`COQUIMBO`) | 15 días hábiles | manda el equipo a Mirador |
   | Abate Molina (`ABATE-MOLINA`) | 15 días hábiles | manda el equipo a Mirador |

   Vive en `config/servicio_tecnico.php` → `dias_reparacion` (por **código** de
   sucursal) + `dias_reparacion_default` (15). Un número fijo en la plantilla
   prometería 10 días en una sucursal que tarda 15. Ver
   [traslado-maquinas-a-reparar.md](traslado-maquinas-a-reparar.md) para el viaje que
   explica esos 5 días de diferencia.

3. **Sin sucursal no se promete plazo** (ingreso por ruta): se omite la línea en vez
   de inventar un número.

4. **La fecha estimada NO se eliminó, se dejó de prometer.** `ordenes_servicio.fecha_entrega`
   sigue calculándose y guardándose: la usan el flujo de salidas del Inicio y el
   informe de gestión, y el taller la ve en la ficha. Lo que cambia es a quién se le
   muestra — adentro sí, al cliente no. El campo de la ficha lo dice en su ayuda, para
   que nadie la prometa por teléfono.

5. **El plazo se dice con "hasta"** ("hasta 10 días hábiles"), y el correo agrega que
   se cuenta en días hábiles y puede variar según el diagnóstico.

## Candados

`tests/Feature/PlazoSinFechaPrometidaTest.php` (6): las tres superficies del cliente
no llevan la fecha y sí el plazo, los tres plazos por sucursal son los que dictó el
dueño, y la ficha interna **sigue** mostrando la fecha estimada — ese último es el que
distingue "no se le manda al cliente" de "se eliminó de la app".

`tests/Feature/Admin/InformacionImportanteCorreoTest.php` cubre el plazo dentro del
bloque «INFORMACIÓN IMPORTANTE» del correo de ingreso, incluido el caso sin sucursal.
