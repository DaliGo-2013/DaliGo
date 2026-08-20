# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-20 (v31 — QA del dueño al chat: funcional
> APROBADO con UN hallazgo, la viveza. GO MSG-5: mensajes que aparecen solos
> sin perder lo escrito — el lote de CIERRE del plan). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ QA del dueño (20-ago, teléfono y PC) — y el veredicto de infra

El chat funcional APROBADO entero. El hallazgo: «hay que recargar para ver los
mensajes, no es instantáneo». Tu poll cumple SU contrato (refresco ≤20 s) —
el contrato quedó corto para una conversación. Tres causas: 20 s eternos en
chat · el reload pierde el texto a medio escribir del composer · al volver a
la app en el teléfono el próximo tick tarda hasta 20 s más.

El dueño preguntó por websockets (WhatsApp/Telegram). Veredicto de infra del
Director con evidencia: **HostGator compartido NO sostiene Reverb/SSE** (sin
daemons — el proveedor reescribió el crontab 3 veces matando crons <15 min;
sin Redis; el deploy ni instala dependencias; tu propia restricción del F0
«sin websockets, el hosting cPanel manda» era correcta). Instantáneo real =
migrar a VPS, decisión de negocio FUERA de este plan. **El dueño eligió: poll
fino, tu molde.**

## 🔨 GO — Lote MSG-5: el chat vivo (M) — CIERRA el plan

1. **Intervalo 4 s SOLO en el chat**: prop `intervalo` en `mensajes/index` y
   `mensajes/show` (el componente ya la acepta — cero cambio en él por esto).
   Vivo y cola de bodega SIGUEN en 20 s: sus conductas NO cambian.
2. **El hilo trae y appendea — SIN reload (la pieza nueva)**: al detectar
   firma distinta en el hilo, fetch JSON de los mensajes NUEVOS (> último id
   visible en pantalla) y append de burbujas al DOM + autoscroll + marcar-leído
   en el mismo request. **El composer CONSERVA lo escrito** — ese es el bug UX
   real del QA. Endpoint: propón con el código a la vista (uno nuevo
   `mensajes/{conversacion}/nuevos?desde=ID` o el `conteo` ampliado —
   decláralo; literales antes del parámetro como siempre). El render de la
   burbuja: mismo HTML que el server-render o el server lo devuelve pintado
   (partial) — tu criterio declarado; cero divergencia visual.
   La LISTA (`mensajes/index`) sigue con recarga completa (no hay composer que
   perder) — a 4 s.
3. **Tick INMEDIATO al volver**: listener `visibilitychange` → consulta al
   tiro al volver a la pestaña/app. Si lo metes al componente compartido, los
   4 consumidores lo heredan — declarado como cero-cambio-de-conducta (solo
   adelanta el tick, misma semántica: nadie recarga si la firma no cambió).
4. **Candados**: burbuja nueva presente tras el ciclo SIN reload (assert de la
   respuesta del endpoint pintando lo que la pantalla appendea) · composer
   intacto (la conducta que el candado pueda fijar del molde — decláralo) ·
   marcar-leído baja MI contador vía el request de nuevos · endpoint con
   auth + permiso + 403 no-participante + whereNumber · vivo/cola conducta
   IDÉNTICA (20 s + reload — grep estructural) · la firma sigue de LA MISMA
   función · mutación tuya declarada con rojo exacto.
5. **Bundle**: JS nuevo probablemente sin clases → verifica hash; si cambia,
   I-06 declarado (public/build versionado, build local).

### Verificación (invariante)
Rama `feature/msg-5-chat-vivo` desde main FRESCO (baseline: **2272 / 15.873**
en el main con el trabajo de terreno de Marcos adentro — verificado por el
Director con suite completa local; recuenta tú). Suite COMPLETA antes.
Batería: Mensajes completo + los 4 consumidores del poll. Parte al buzón;
espera doble llave.

## 📡 Después de MSG-5
QA del dueño (el gesto exacto: hilo abierto en el teléfono, enviar desde el
PC → la burbuja aparece sola en ~4 s con el texto a medio escribir INTACTO) →
cierre de PLAN-MENSAJES (acta, Trello) → stream nuevo.

## Estado
Max-1: OPE-2 forjando (motivos de parada + par planificados⊆motivos —
territorio disjunto). Marcos mergeó trabajo de terreno directo a main (queda
como está por decisión del dueño; suite verde). Trello espejando. Baseline:
2272/15.873.

CIERRE: GO MSG-5. El chat que se siente vivo — con el fierro que el hosting
sí permite.
