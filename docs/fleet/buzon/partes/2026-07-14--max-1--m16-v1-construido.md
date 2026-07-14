# Parte — Max-1 · M16-v1 «Pulso del día» CONSTRUIDO — merge espera doble llave

> De: Max-1 · Para: Director / Mauricio
> **Registro de la llave del gate:** el visto bueno del dueño a la **Opción A** llegó DIRECTO
> en mi sesión (Mauricio respondió «a» a la pregunta A/B/C del plan). Con esa llave — que es
> LA llave que el PLAN-M16-V1 §7 exigía antes de codear — construí. El Director valida ahora
> el resultado; el push a main sigue el protocolo de doble llave de siempre.

## Lo construido — rama `feature/m16-v1-pulso` @ `05fb025`

**P-M16V1-01+02 en un commit (`3f8b98f`)** — los tests renderizan la vista, separarlos dejaba
un commit rojo; suite verde por commit respetada:

- **① Excepciones (andon):** solo lo desviado, cada ítem con **cantidad + edad del más viejo
  + destino** — Reportes por aprobar («el más antiguo espera hace 2 días»), devueltos,
  asignaciones de hoy sin reporte, recepciones por confirmar (regla anti-403 de v0.1
  preservada), aprobaciones pendientes (espejo de bandeja, con espera), notificaciones caídas
  (SOLO terminales: las que aún reintentan se resuelven solas). Sin desviaciones →
  **«Operación al día»** quieto en neutral.
- **② Pulso:** producción como **medida directa** (producido/asignadas + % en UNA barra,
  merma con su promedio de los 7 días PREVIOS como referencia — sin días previos no se
  inventa, queda sin referencia) + mini-serie de 7 días (CSS puro, hoy destacado); taller
  con **aging 0-7/8-30/30+** (barra segmentada en intensidades neutral, oscuro = más viejo)
  y flujo semanal entraron/salieron.
- **③ Zócalo:** los 11 accesos del v0 INTACTOS + Notificaciones y Aprobaciones (permisos
  alineados a sus middleware), como chips compactos al final.
- **Capa de datos:** `seriePorDia`/`asignadasPorDia` movidas a estáticos de modelo (byte-
  idénticas, el panel del jefe delega — cero duplicación); aging **portable SQLite/MySQL 5.7**
  (límites en PHP + `whereDate`, cero DATEDIFF/window functions).
- **13 tests nuevos** (edad exacta con freezeTime, aging, serie con ceros, terminales vs en
  reintento, visibilidad por rol, zócalo filtrado; factory de estado aleatorio FIJADA en todo
  assert). **Suite 593 verdes (1.887 aserciones).** Bundle `app-yisIA29E.css`, grep 9/9.
  Responsive verificado en preview 375/768/1280 (sin overflow; barra + 7 mini-barras vivas).

## Gate R-31 (P-M16V1-03, auditoría adversarial 2 lentes): 14/14 OK

**APROBADO CON OBSERVACIONES** — ninguna bloquea. Las que valen para la doble llave:
1. **Pregunta de producto para el QA:** la card «Cotización (espera cliente)» era DECISIÓN en
   la auditoría del plan y no tiene sustituto directo en el home (el sketch aprobado no la
   incluía). Validar con el dueño en el QA de 5 segundos; si la extraña, entra como excepción
   con edad en un tweak S.
2. Menores anotadas: chip de notificaciones cuenta terminales pero el destino lista todas las
   fallidas (superset, sin ítems fantasma); `min(enviado_at)` puede subestimar edad si
   coexisten históricos sin ese campo; redondeo 99-101% en la barra segmentada (cosmético);
   pill/chips como candidatos a componente si se reutilizan.

## Lo que falta (doble llave, como siempre)

1. Endoso del Director a este parte + visto bueno de Mauricio al push → **merge a main = deploy**.
2. **QA de 5 segundos** (el criterio de aceptación del plan): Mauricio abre el Inicio en su
   teléfono y en 5 segundos responde «¿está todo bien o qué tengo que ir a mirar?». De paso:
   ¿echas de menos «Cotización» en el home?
3. P-M16V1-03 [x] en RUTA (ya viaja en la rama con 01+02 sellados) → E10-v1.0 CERRADA.

Pendiente vivo sin cambio: confirmación del QA de celular de M14 para sellar P-M14-07.

— Max-1
