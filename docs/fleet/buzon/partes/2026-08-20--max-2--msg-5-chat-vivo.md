# Parte de cierre — Max-2 · MSG-5 · El chat vivo (CIERRA el plan)

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **MSG-5** — GO del dictado v31 (el hallazgo de viveza del QA del dueño)
ESTADO: **HECHA** — pide doble llave
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: doble llave de `feature/msg-5-chat-vivo` → QA del dueño del gesto
exacto (hilo en el teléfono, enviar desde el PC → burbuja sola en ~4 s con el
texto intacto) → **acta de cierre de PLAN-MENSAJES**.

## EVIDENCIA

Rama **`feature/msg-5-chat-vivo`** desde main fresco (`180b949`), pusheada —
1 commit de código, suite COMPLETA verde:

| Commit | Qué | Suite |
|---|---|---|
| `997d223` | Hilo que appendea SIN reload (endpoint `nuevos` + partial compartido) · lista a 4 s · tick inmediato al volver (`visibilitychange` en el componente) · +5 tests netos | **2284 / 15.934** (baseline **2279 / 15.912** en `180b949` — más que la dictada 2272: main avanzó con OPE-2, cifra fresca recontada; delta EXACTO +5) |

**Los candados del dictado, verificados:**
1. `nuevos?desde=X` devuelve SOLO lo posterior, PINTADO por el server; `ultimo`
   correcto; sin novedad → html vacío y el mismo puntero.
2. **Marcar-leído vía el request de nuevos**: baja MI contador cuando trae;
   SIN novedad NO escribe (cero writes cada 4 s — observado con un contador
   reconstruido a mano que el tick vacío no toca).
3. Gates del endpoint: guest JSON → 401 · sin permiso → 403 · tercero → 403 ·
   whereNumber heredado del grupo.
4. XSS: `<script>` escapado también en el JSON (el partial es EL MISMO del
   render inicial — cero divergencia por construcción).
5. **Vivo y cola conducta IDÉNTICA**: candado estructural — cero `intervalo`
   en sus vistas (20 s default) + sus baterías intactas; el componente solo
   ganó el tick-al-volver (misma semántica: nadie recarga si la firma no
   cambió).
6. La firma sigue saliendo de LA MISMA función (candado ajustado a
   lista+conteo — ver desviaciones).
7. **MUTADO**: cegar el `> desde` del endpoint (devolver todos) → rojo exacto
   en «el html NO contiene el viejo» → `git checkout --` → verde.

## E2E DEL GESTO — medido en browser real (no argumentado)

Serve descartable (SQLite propio + ruta temporal de auto-login, jamás
commiteada — precedente P-M11-20): hilo abierto como Ana con **«texto a medio
escribir que NO debe perderse»** en el composer → Beto envía por atrás →
resultado medido: **burbuja nueva apareció (1→2), texto y lado correctos,
composer INTACTO, cero reloads** (1 sola navegación en performance). El guard
de visibilidad quedó probado por su efecto: con el pane oculto, CERO appends
espontáneos en 5 s+ (el tick no gasta red oculto); el cuerpo del tick se
ejecutó tal cual para medir la cadena completa.

## Desviaciones y decisiones declaradas

- **El hilo SALIÓ de `<x-poll-recarga>`** (un reload le perdía el composer al
  que escribe — el bug UX real del QA): script propio de append. El candado
  estructural de MSG-3 se AJUSTÓ: el componente vive en {vivo, cola, lista} y
  los scripts inline con visibilityState son {ST, mensajes/show} — cada uno
  OTRA conducta a propósito.
- **Candado misma-función ajustado a lista+conteo**: el hilo ya no hornea
  firma (migró a fetch-append); la lista y el conteo siguen con la fuente
  única.
- **Append solo en la página 1** (`onFirstPage()`): en las históricas se lee,
  no se conversa — candado propio.
- **Marcar-leído solo-cuando-trae**: sin novedad no hay nada que leer y no se
  escribe cada 4 s.
- **Render server-side del partial** (la opción declarada que pidió v31):
  `_burbuja.blade.php` compartido entre show y el endpoint.
- **Herencia del tick-al-volver**: los 3 consumidores del componente lo ganan
  como cero-cambio-de-conducta (solo se adelanta el próximo tick).
- **Bundle intacto** (hash idéntico al de main, git limpio en public/build).
- Needle de un candado corregido en la primera corrida (el refactor
  `comprobar()` cambió la forma del setInterval — el assert siguió a su código).

## PLAN-MENSAJES tras este lote

Motor (ráfaga) → pantallas → refresco → menú+badge → **chat vivo**. El gesto
que el dueño pidió está medido acá; su QA en celular tiene la última palabra.
