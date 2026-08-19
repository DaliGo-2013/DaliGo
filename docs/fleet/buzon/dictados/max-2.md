# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-19 (v27 — MSG-1 EN PRODUCCIÓN con la llave pre-otorgada del dueño. GO MSG-2: las pantallas del chat). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ MSG-1 está EN PRODUCCIÓN (merge `c89867e`, 19-ago)

El dueño había PRE-OTORGADO la llave («te doy el ok a msg1») condicionada a
verificación limpia — y tu lote la cumplió entera: suite del Director **2221 / 15.470,
CERO rojos**, delta exacto +13, cero cifras viejas cambiadas. Rama borrada.

Lo que quedó fino (ya es doctrina):
1. **El guard `Route::has('mensajes.show')`** — la rama nace apagada y se enciende sola
   con MSG-2. Exactamente cómo se maneja una dependencia hacia adelante: sin él, una
   campanita de mensaje daba 500 HOY. Y el gotcha del `refreshNameLookups` en el test
   de ruta runtime, cazado y declarado.
2. **La ráfaga POR LADO** (extra no dictado que suma): responder sin leer también avisa
   al otro — cada dirección con su contador.
3. **El contrato de RoleMatrix movido CON su seeder** (member deja de ser rol-vacío) y
   la categoría nueva en config/permissions para no caer en «Generales» — cero amoldes
   crudos, todo con porqué.

Coordinación cerrada por el Director: COM-1 de Max-1 (tocaba tu mismo seeder) quedó
re-verificado ENCIMA de tu merge — auto-merge limpio, suite 2227/15.510.

## 🔨 GO — Lote MSG-2: las pantallas del chat (M)

Tu propio diseño §5.2, al pie de la letra — con lo ya construido en MSG-1 debajo:

1. **Lista de conversaciones** (`GET /mensajes`, molde exacto de la bandeja de
   notificaciones): tinte + punto brand con no-leídos, avatar, último mensaje truncado,
   `diffForHumans()`, badge por conversación. Orden `ultimo_mensaje_at` desc, sin
   paginación (equipo de decenas). Botón «Nuevo mensaje».
2. **Hilo** (`GET /mensajes/{conversacion}`, `whereNumber`): burbujas por lado
   (paleta-4: mías `bg-brand-50` derecha, del otro `bg-neutral-100` izquierda), hora
   con `enChile()` (P-TZ-02), `paginate(50)` descendente renderizado cronológico,
   composición al pie (molde kaizen: textarea rows=2 maxlength=1000 + botón h-12
   w-full + guarda de texto vacío + sin comillas dobles en x-data). Marcar leído en el
   GET (idempotente, riesgo prefetch nulo — declarado en tu diseño). Gate 403 si no
   soy participante.
3. **Nuevo mensaje** (`GET /mensajes/nuevo`): `<x-select>` de usuarios excluyéndome
   (molde notas/_form) + textarea; el POST canonicaliza y redirige al hilo.
4. **Rutas**: grupo `auth` + `permission:usar mensajes`, prefix `mensajes` — la ruta
   `conteo` del poll RESERVADA antes de `{conversacion}` (regístrala en MSG-3, pero
   deja el orden listo o decláralo). **Al registrar `mensajes.show`, el guard de
   MSG-1 se enciende SOLO**: candado explícito de que la campanita de un mensaje ahora
   NAVEGA al hilo (el destino deja de ser null — el test que en MSG-1 exigía ruta
   runtime ahora corre contra la ruta real).
5. **Online-only** (veredicto del dueño): el form avisa «necesitas señal» con
   `$store.red` (molde mi-reporte).
6. **Volver**: las 3 pantallas son hijas — `mensajes.index` es la madre… OJO: hasta
   MSG-4 el ítem del menú NO existe, así que `mensajes.index` es HUÉRFANA temporal.
   El precedente de la casa es P-NAV-06/08: pantalla fuera del menú lleva su `<x-volver>`
   (al Inicio) MIENTRAS no tenga ítem; en MSG-4 se lo quitas (con el candado de
   VolverTest derivando de la fuente única). Decláralo en el parte — nada de pantallas
   sin salida.
7. **Sin gateo por rol en las vistas**: el permiso ya gatea las rutas; adentro todos
   iguales.

### Candados (además de los tuyos de siempre)
- Lista: solo MIS conversaciones (la de terceros no aparece); orden por último mensaje;
  no-leídos pintados.
- Hilo: 403 de tercero; leer baja MI contador (y el candado de MSG-1 de la ráfaga
  sigue verde encima de la pantalla real).
- Campanita navegable: el aviso de mensaje ahora lleva al hilo (guard encendido).
- Nuevo mensaje: no puedo escribirme a mí mismo (el selector me excluye Y el POST
  rechaza).
- XSS: texto del mensaje escapado (burbujas con {{ }}, jamás {!! !!}) — candado con
  un mensaje `<script>`.
- Mutación: la de tu criterio, declarada.

### Verificación (invariante)
Rama `feature/msg-2-pantallas` desde main FRESCO (baseline: **2227 / 15.510** — COM-1
puede mergear antes que tu parte; recuenta como siempre). Suite COMPLETA antes.
Batería: Mensajes completo + Notificaciones + Volver/Sidebar/Navigation (la huérfana
temporal). Si tu lote trae Blade con clases nuevas → bundle: recompilar + superset
(I-06), decláralo. Parte al buzón; espera doble llave (esta vez SÍ — la pre-otorgada
era solo MSG-1). NO arranques MSG-3.

## Estado
Max-1: COM-1 verificado, espera llave del dueño; luego COM-2 higiene. Trello espejando
(Motor de mensajes ya en Terminadas; Pantallas en En Curso). Marcos activo.

CIERRE: GO MSG-2. El chat gana cara — que se sienta como la bandeja de siempre. Fierro.
