# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-07-14 (v3 — P-DSP-02 verificado, DESPACHOS bloqueado en el shape, micro-backlog aprobado). Manda sobre lo anterior.

MODELO: Fable 5 disponible hasta el 19-07 (decisión del dueño usarlo); si no, Opus 4.8 · high.

✅ **P-DSP-00 (código) y P-DSP-02 (zonas) VERIFICADOS por el Director**, spot-check propio sobre
`3a79b69` y `0a4d063`: comando `bsale:explore` extendido read-only con valores redactados ✓
(correcto no ejecutarlo local sin token) · 3 migraciones aditivas + `Cliente::zonaEfectiva()`
con precedencia cliente-explícito > vendedor > null (el ajuste del dueño "siempre hay
excepciones" aplicado tal como se dictó) · 6 tests, suite 536 verde en ese punto, 576 verde
tras refrescar con M14 (`efcde04`, conflicto único en `AuditController::MODELOS` resuelto por
unión — correcto). Buen criterio adelantar P-DSP-02 sin esperar el shape.

## Sigues BLOQUEADO en P-DSP-01/03 — la pelota está en la cancha de Mauricio
Tu delegación a IA-cPanel (parte `2026-07-13--max-2--p-dsp-00-delegacion.md`) sigue sin
respuesta. El Director se la señala a Mauricio en este informe; no hay nada más que puedas
hacer tú para destrabarla — no reintentes ni inventes el shape.

## ✅ APROBADO: micro-backlog M15 mientras esperas
Tu propuesta es correcta: rama nueva `feature/m15-microbacklog` desde main FRESCO (NO mezclar
con `feature/despachos-v1` — provenance limpia, ya lo planteaste bien). Alcance (§3 de tu
dictado anterior, talla S/M):
- Correo destino configurable en el panel de notificaciones (hoy hardcodeado — confirma dónde).
- Error SMTP sin truncar en el log/vista de notificación fallida.
- Endurecer `test_campanita_visible_en_el_nav` (o el que corresponda) contra flaky.
Suite verde por commit. Parte al buzón por ítem. Si mientras tanto llega la respuesta del
shape, PAUSA el micro-backlog y vuelve a P-DSP-01 (prioridad: destrabar la unidad grande).

CIERRE por ítem: parte a docs/fleet/buzon/partes/ + push.
