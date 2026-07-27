# Parte — Max-1 · Lote fix S del QA 15-07 listo (rama `fix/qa-aprobaciones-ux` @ `f040791`)

> De: Max-1 · Para: Director / Mauricio
> Continuación del acta `2026-07-15--max-1--acta-qa-m14-m15.md` (E2·M14 ya sellada en
> `fe18d19`). Este lote toma SOLO los 3 hallazgos de libro (texto/label/render de datos ya
> existentes) — territorio M14 propio, cero cambios de comportamiento del motor.

## Los 3 fixes (hallazgos #1, #3 y #7 del acta)

1. **#1 — Nav sin doble nombre:** el ítem del dropdown Administración pasa de «Aprobaciones»
   a **«Historial de aprobaciones»** (desktop + espejo móvil). La bandeja conserva su nombre.
2. **#3 — «Mis solicitudes» dice QUÉ se pide:** cada fila del solicitante muestra ahora su
   motivo + magnitud (espejo compacto de la tarjeta del aprobador).
3. **#7 — La tarjeta del aprobador muestra el CAMBIO exacto:** «Asignadas: 450 → 500» —
   render de `datos.anterior/nuevo` (el payload ya lo traía), solo los campos que difieren.

## Verificación

- **Suite 634 verdes (2.022 aserciones)** sobre main fresco (`fe18d19`, incluye el árbol
  nuevo de instalaciones-terreno).
- +2 tests y 2 asserts extendidos en `AprobacionBandejaTest` (nav distingue bandeja/historial;
  diff visible; motivo+magnitud en mias).
- Bundle regenerado `app-CQ-gaRIb.css`, grep 7/7 (`lg\:flex`, `lg\:hidden`, clases M16 + nuevas).
- Rama nacida de main fresco; diff = 3 Blades + 1 test + bundle.

## Para la doble llave

Los fixes fueron pedidos/validados por el dueño durante el QA guiado (protocolo del dictado:
lote al final del día, el Director mergea). **Quedan como fichas (NO en este lote):** #5 filas
de notificación clickeables · #8 plantillas ricas de correo · #2 timezone UTC · #6 chips
paramétricos · #9 canal database síncrono (territorio M15) · nota I-01 del crontab (A8).

Pendiente del acta que sigue vivo: confirmación del dueño de la reversión del checkbox de
correo (B4) — 1 clic en Perfil → Notificaciones si quedó desmarcado.

— Max-1
