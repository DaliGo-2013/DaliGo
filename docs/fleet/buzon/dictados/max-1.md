# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-07-14 (v9 — ventana reseteada; cola nueva post-M16). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño); OJO ledger: tu sesión M16 costó
USD 77.99 en Fable — para esta cola (tallas S/M) basta **Opus 4.8 · high**; reserva Fable para
diseño/planes. El dueño fija el selector.

CONTEXTO: M16-v0 YA ESTÁ EN PRODUCCIÓN (merge `4900d5b` por el Director con doble llave,
Deploy+Tests verdes, bundle `OhPY6b0u` verificado servido; M12 recompiló el bundle después —
interferencia descartada por el Director, 6/6 clases del dashboard presentes en el nuevo).
Max-2 avanza DESPACHOS (P-DSP-01 hecho, va por P-DSP-03) — NO toques nada de despachos.

## COLA (en orden):

### 1. Housekeeping M16 (S, un push, solo docs)
- P-M16-03 [x] + sello 01→07 en RUTA-MAESTRA → E10-v0 CERRADA.
- Tu rama `feature/m16-v0-dashboard` cumplió su ciclo — no trabajes más ahí.

### 2. M16-v0.1 — las 3 observaciones de TU gate R-31 (S/M, rama nueva desde main fresco)
Rama `feature/m16-v01-pulido`. Las 3 que tu propio gate dejó anotadas:
a. Extraer las fórmulas producido/merma%/avance a un helper compartido con `armarResumen`
   (hoy duplicadas línea a línea — eliminar el drift futuro).
b. Migrar los asserts de los tests de `$indicadores` plano a `secciones` y eliminar el dato
   muerto de la vista.
c. La card «Recepciones por confirmar»: alinear gate del `@can` con el middleware del href
   (hoy un rol futuro con SOLO `confirmar servicio tecnico` daría 403 al click) — resuélvelo
   con el gate más restrictivo, sin permisos nuevos.
Suite verde; si tocas Blade → npm install + build + grep bundle (las 6 clases del dashboard
+ `lg\:flex`/`lg\:hidden`).

### 3. Micro-backlog M15 (S/M — REASIGNADO desde Max-2, que está en DESPACHOS)
Rama `feature/m15-microbacklog` desde main fresco:
- Correo destino de notificaciones configurable en el panel (hoy hardcodeado — confirma dónde).
- Error SMTP sin truncar en la vista/log de notificación fallida.
- Endurecer `test_campanita_visible_en_el_nav` (o el que corresponda) contra flaky.

### Recordatorio E2·M14 (no bloquea, depende del dueño)
Cuando Mauricio confirme el QA de celular → P-M14-07 [x] + sello en RUTA (mismo push que el
housekeeping si coincide).

Los merges a main de 2 y 3 esperan doble llave (parte al buzón cuando estén listos).
CIERRE por paso: parte a docs/fleet/buzon/partes/ + push.
