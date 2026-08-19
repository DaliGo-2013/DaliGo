# Parte de cierre — Max-2 · MSG-2 · Las pantallas del chat

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño)
TAREA: **MSG-2** — GO del dictado v27 (PLAN-MENSAJES §5.2/§5.7)
ESTADO: **HECHA** — pide doble llave
/usage INICIO → FIN: n/d (lo captura el dueño)
SIGUIENTE: doble llave de `feature/msg-2-pantallas` → GO MSG-3 (poll) cuando se dicte.

## EVIDENCIA

Rama **`feature/msg-2-pantallas`** desde main fresco (`5831889`), pusheada — 1 commit
de código (las pantallas son una unidad: el controller no corre sin vistas), suite
COMPLETA verde:

| Commit | Qué | Suite |
|---|---|---|
| `d533031` | Rutas bajo `permission:usar mensajes` · MensajeController · 3 vistas (lista/hilo/nuevo) · `ultimoMensaje()` latestOfMany · retiro del candado temporal · bundle · 12 tests de pantalla | **2238 / 15.574** (baseline 2227 / 15.528 — delta EXACTO +12 nuevos −1 fusionado) |

**Los candados del dictado, verificados:**
1. Lista solo MIS conversaciones, orden por último mensaje, no-leídos por
   marcador ACCESIBLE «mensajes sin leer» (doctrina CampanitaTest).
2. Hilo ajeno: GET → redirect + aviso (D-014), POST → 403 crudo y el mensaje del
   intruso NO se crea.
3. Abrir el hilo baja MI contador; responder crea el mensaje vía Mensajeria y la
   RÁFAGA de MSG-1 sigue verde ENCIMA de la pantalla real.
4. **Campanita navegable**: el guard de MSG-1 se encendió SOLO al registrar
   `mensajes.show` — sin tocar Notificacion (el candado estrella del dictado).
5. **XSS**: mensaje `<script>alert(1)</script>` escapado; el crudo jamás en el HTML.
6. Nuevo mensaje: el selector me excluye (por viewData) y el POST a mí mismo se
   rechaza por validación.
7. Paginación 50: página 1 = lo más reciente, verificada en el PAGINADOR.
8. Volver de la HUÉRFANA TEMPORAL al Inicio (`data-dg-volver` + href — precedente
   P-NAV-06; MSG-4 lo quita cuando el ítem entre al menú).
9. Online-only: la composición avisa «necesitas señal» y conserva lo escrito.
10. Texto >1000 desde el form = error de validación, jamás excepción cruda.

**MUTADO (hallazgo incluido):** cegar SOLO el `abort_unless` del controller dejó el
test VERDE — el cinturón del modelo (`marcarLeida` trae su propio gate) atrapa al
intruso: defensa en profundidad real, no decorativa. Cegando AMBAS capas → rojo
exacto → `git checkout --` → verde. La mutación documentó que las dos capas existen
y cuál es la red de cada una.

## Desviaciones declaradas

- **Candado temporal de MSG-1 retirado** (lo ordena v27): los 2 tests de la ruta
  runtime se fusionaron en UNO contra la ruta real (−1 test; su premisa murió al
  registrar `mensajes.show`).
- **Reserva del orden del poll**: comentario en las rutas — el `conteo` de MSG-3
  se registra ANTES de `{conversacion}` (doctrina despachos/vivo).
- **Bundle recompilado y commiteado** (árbol LIMPIO sobre main, precedente
  `81606c5`): superset por tokens verificado (0 pérdidas, +1 `justify-start`);
  los hashes de JS cambiaron SIN fuentes JS tocadas (no-determinismo del entorno
  de build — declarado).
- **Los asserts miran la PANTALLA, no el shell** (3 rojos propios cazados ANTES
  del commit): la campanita muestra el extracto del primer mensaje (la ráfaga
  disparó con él), la sidebar pinta el nombre del usuario logueado en toda
  página, y «sin leer» vive en el aria de la campanita → marcador propio
  «mensajes sin leer» + exclusiones por viewData (doctrina verde-engañoso, en
  las dos direcciones).

## Verificación adicional (gate propio)

- **Volcado 375/768 de las 3 pantallas** (BD en memoria + URL::forceRootUrl):
  lista con tinte + badge accesible + avatar + Volver→Inicio; hilo cronológico
  con lados correctos, burbuja al tope EXACTO del 85% (292px a 375), hora
  `enChile`, botón 48px, Alpine vivo; nuevo con el selector excluyéndome (y un
  apóstrofo real en el nombre del fixture sin romper nada). Cero overflow en
  todos los anchos.
- Batería dirigida 141 tests / 1.988 aserciones: Mensajes + Notificaciones +
  Volver + Sidebar + MenuPrincipal.
- Incidente de proceso declarado: una corrida de la suite quedó con output
  vacío y se relanzó una segunda en paralelo por diagnóstico apresurado — se
  mató la duplicada, la original terminó 2238/15.574. Cero efecto en el árbol.
