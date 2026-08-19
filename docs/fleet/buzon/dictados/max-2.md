# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-19 (v28 — MSG-2 EN PRODUCCIÓN: el chat ya se usa. GO MSG-3: el refresco automático). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ MSG-2 está EN PRODUCCIÓN (merge `5bf39df`, doble llave 19-ago)

Suite del Director sobre el árbol combinado con COM-2 de Max-1: **2238 / 15.574, CERO
rojos** — delta exacto +12/−1 como declaraste. Rama borrada. **El chat ya se puede usar
por URL.**

Lo que quedó fino: la mutación que DOCUMENTÓ la defensa en profundidad (cegar el gate
del controller y el del modelo atrapa igual — eso es un hallazgo de arquitectura, no
solo un test); los asserts mirando la PANTALLA y no el shell (los 3 rojos propios
cazados antes del commit); y el volcado móvil con el apóstrofo real en el fixture.

## 🔨 GO — Lote MSG-3: el refresco automático (S)

Tu diseño §5.4, el 4º uso del molde de poll:

1. **Ruta `GET /mensajes/conteo`** (`mensajes.conteo`) — ANTES de `{conversacion}`
   (el orden que dejaste reservado). Devuelve la firma barata del estado: propón la
   forma exacta (tu diseño hablaba de firma tipo vivo/cola-bodega — suma de contadores
   + último id o lo que argumentes; el contrato es «cambió algo → recarga»).
2. **Poll de 20s SOLO en las pantallas del chat** (lista + hilo), molde vivo/cola:
   `document.hidden` respeta (pestaña oculta no pega), y al detectar cambio recarga la
   pantalla (sin websockets, sin estados a medias — doctrina del refresco de la casa).
3. **La campanita/menú SIN poll** (doctrina vigente — el badge del menú llega en MSG-4
   por el patrón declarativo, no por poll).
4. **Si el molde de poll amerita extraerse a componente** (4º uso — tu propia nota del
   F0): tu criterio decide, declarándolo; si lo extraes, los 3 consumidores viejos
   migran con cero cambio de conducta y sus tests intactos (regla de oro).
5. **Candados**: conteo requiere auth+permiso (401/403 sin sesión/permiso — es endpoint
   nuevo); la firma cambia cuando llega mensaje y NO cambia cuando no pasa nada; el
   poll no corre fuera del chat (grep de la señal en otras vistas = 0); mutación tuya
   declarada.

### Verificación (invariante)
Rama `feature/msg-3-poll` desde main FRESCO (baseline: **2238 / 15.574** en `5bf39df`).
Suite COMPLETA antes. Si extraes componente de poll: batería sobre los 4 consumidores.
Bundle: si tocas JS/Blade con clases nuevas → I-06 declarado. Parte al buzón; espera
doble llave. NO arranques MSG-4.

## 📡 Después de MSG-3
MSG-4 (v29): el ítem «Mensajes» de primer nivel (menú 32→33, veredicto del dueño) +
badge de no-leídos declarativo + retiro del Volver de la huérfana temporal + candados
de MenuPrincipal/Sidebar/Volver derivando. ÚNICO cruce con Max-1: `MenuPrincipal` — el
Director secuencia. Tras MSG-4: QA del dueño del chat COMPLETO en celular.

## Estado
Max-1: módulo Comercial completo, en pausa hasta QA del dueño; luego F0-OPERACIÓN
(territorio disjunto del tuyo). Marcos activo. Trello espejando.

CIERRE: GO MSG-3. El chat que se refresca solo — sin websockets y sin drama. Fierro.
