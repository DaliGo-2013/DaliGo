# Parte de cierre — Max-2 · F0-MENSAJES-2 · El catálogo de la fase 2

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **F0-MENSAJES-2** — GO del dictado v35 (el menú de propuestas, SOLO DOCS)
ESTADO: **HECHA** — solo docs, sin doble llave de código
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: veredictos del dueño sobre el catálogo → el Director dicta los
lotes de la fase 2.

## ENTREGA

**Anexo §7 «Fase 2 — candidatas» en `docs/planes/PLAN-MENSAJES.md`**: catálogo
de **12 candidatas** (las 7 semillas del Director + deudas del acta + 4 de mi
ojo de forjador), cada una con qué-es en palabras de negocio · qué toca ·
esfuerzo S/M/L · riesgos/deudas · recomendación con porqué. Tabla-menú de un
vistazo al tope para que el dueño decida con el catálogo en la mano. Orden
por VALOR, criterio declarado: primero pulir el gesto diario (conversar),
después el alcance (fotos, buscar), al final lo que pelea contra la
infraestructura (push, instantáneo).

**El catálogo se escribió sobre investigación FRESCA del árbol, no de
memoria**: 8 lectores paralelos sobre origin/main (`69d4aee`) barrieron el
motor del chat, la infraestructura de uploads, el SW/PWA, el motor M15, los
moldes de PARAMETRICOS, la viabilidad de búsqueda (MySQL 5.7 vs SQLite) y la
UX real de las 4 vistas. Todo hallazgo citado lleva file:line.

## Lo que mi ojo encontró (semilla 8 del dictado)

1. **El envío propio todavía recarga la página entera** (POST clásico +
   redirect) y el hilo ABRE ARRIBA sin scroll inicial — MSG-5 hizo vivo el
   recibir; el enviar quedó de formulario. Es la candidata #1.
2. **Una 422 en el hilo PIERDE el texto escrito**: el composer no siembra
   `old('texto')` (create.blade sí lo hace). Contradice la promesa que el QA
   del dueño acaba de verificar — va dentro de la candidata #1.
3. **El correo del chat no lleva botón «Abrir en DaliGo»**: `Mensajeria` no
   pasa `url` en el payload (grep = 0, verificado a mano) y la vista del
   correo pinta el botón solo con `filled($url)`. Candidata #2, S puro.
4. **El badge del menú es stale fuera del chat** (server-rendered, se refresca
   solo al navegar) — candidata #5, deslindada del proyecto-shell que el acta
   ya anotó.

## Recomendaciones (las 12, resumidas)

SÍ: #1 gesto completo del hilo (S/M) · #2 correo navegable (S) · #3 foto en el
hilo acotada (M) · #8 retención configurable (S). SÍ sin apuro: #4 buscar (M).
A MEDIAS: #5 título de pestaña sí, shell vivo es proyecto aparte. SEGÚN EL
DUEÑO: #6 «visto» (barato: `leido_hasta_id` por lado; carta social declarada);
«escribiendo» NO (sin canal digno por poll). NO: #7 difusión/grupos (M15 ya es
el canal de avisos; grupo real = L, medio plan) · #10 editar/borrar (la traza
honesta es feature de negocio + el chat vivo asume append-only) · #11 push PWA
(composer manual + VAPID + tabla + SW + gmp/bcmath no verificable + latencia
*/15 que mata el propósito + iOS solo en PWA instalada — se paga con el VPS,
no dos veces). CONSTANCIA: #12 instantáneo real = VPS, decisión de negocio.

Sugerencia de empaque si aprueba las recomendadas: MSG-6 = #1+#2 · MSG-7 = #3 ·
MSG-8 = #8+#4 según apetito.

## Desviaciones y decisiones declaradas

- **Cero código** (el dictado lo exige): la rama `docs/f0-mensajes-2` solo toca
  el plan y este parte. Sin suite, sin build (nada que compilar).
- **§7 sin §6**: el plan salta de §5.7 a los bloques del acta sin numerar; el
  dictado pidió «§7» y así quedó, al final del archivo.
- **Retención deslindada de PARAMETRICOS**: el acta la llamó «candidato
  nivel-1 de PARAMETRICOS», pero Mensajes quedó FUERA de ese plan (§4: entero
  de Max-1). Si se hace, es lote de ESTA fase 2 con los moldes de la casa
  (RANGOS + scheduler en grilla) — declarado en la propia candidata #8.
- **Difusión presentada DENTRO de grupos** con su costo real por separado
  (M vs L): el dictado pidió no vender fácil — el 1-a-1 está horneado en el
  unique del par, los contadores por lado y `entre()`; lo digo con file:line.
- El push local (Notification API) se evaluó y quedó FUSIONADO en #5/#11 con
  su límite duro: no cubre iOS y el guard de visibilidad del poll lo apaga
  justo cuando serviría.

## PLAN-MENSAJES tras este parte

v1 CERRADA («de diez») → **catálogo de fase 2 entregado**. La pelota queda en
los veredictos del dueño; el Director dicta los lotes.
