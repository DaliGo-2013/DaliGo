# Parte — Max-1: dictado v26 ejecutado — ramas NOTIF cerradas, Max-1 disponible · 2026-07-27 (3º del día)

> De Max-1 (Forjador A) al Director. El buzón seguía en v26 al abrir sesión → aplica la
> instrucción condicional. Parte corto: es housekeeping.

## Hecho

- **`feature/notif-especificas-v2` BORRADA del remoto** (orden del v26). Verificado antes de
  borrar: `7cd5aff` —el rescate con TODO el contenido del lote— es ancestro de main vía tu
  merge `601ec7a`. Mi refresh posterior (`d52e741`) no alcanzó a entrar al merge, pero era un
  commit VACÍO de contenido propio (solo traía `3e03a9a`, que main ya tiene por su vía
  directa) — cero pérdida, verificado con `merge-base --is-ancestor` en ambos sentidos.
- **`feature/notif-especificas` (la vieja) BORRADA del remoto** — la orden venía del v25
  («cuando la v2 esté mergeada, no antes») y la condición se cumplió hoy.
- Locales limpiadas también. Queda en el remoto `feature/m15-notificaciones` (14d): tu cierre
  del 27-07 la marca «candidata a borrar» pero no me lo ordenó — la dejo a tu llave.

## Estado del stream 1

**NOTIF-1 en producción, territorio limpio, cero ramas abiertas de Max-1.** En STANDBY
disponible para lo que el dueño decida: #6 chips paramétricos (si entra, es mío según el
v26) · sublote C (de Marcos salvo decisión en contrario) · la incoherencia campanita-ancla
vs botón-del-correo (decisión de producto).

Nota para el QA visual del dueño cuando pase por staging: el aporte del lote se ve tocando
la campanita en ESCRITORIO (cuerpo con saltos + aterrizaje con anillo en la tarjeta exacta);
en el celular la campana va directo a la bandeja (decisión de main, no del lote) y ahí el
aporte visible es el cuerpo legible multi-línea.
