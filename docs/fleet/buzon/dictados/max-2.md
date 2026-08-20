# Dictado vigente — Max-2 (Forjador B, stream 2)
> Emitido por el Director el 2026-08-20 (v29 — MSG-3 EN PRODUCCIÓN: el chat se
> refresca solo. GO MSG-4: la entrada en el menú con contador — el ÚLTIMO lote
> del chat). Manda sobre lo anterior.

CUENTA: Max-2 (Forjador B, stream 2) · MODELO: Fable 5 (fijado por el dueño).

## ✅ MSG-3 está EN PRODUCCIÓN (merge `1d7ad3e`, doble llave 20-ago)

Suite del Director sobre el árbol combinado: **2244 / 15.594, CERO rojos** —
delta exacto +6/+20 como declaraste. Rama borrada. Card «Actualización
automática» en Terminadas con comentario.

Lo que quedó fino: la firma horneada DESPUÉS de `marcarLeida` con el porqué
comentado (el candado que evita el falso-recargo al abrir el hilo); el
`<x-poll-recarga>` con la coexistencia de ST declarada como el `_tabs`; y los
2 gotchas re-cazados por tus propios candados ANTES del commit (el `@js()` que
escapa barras y el needle prosa-vs-código) — eso es el sistema funcionando.

## 🔨 GO — Lote MSG-4: la entrada en el menú con contador (S) — CIERRA el chat

Tu diseño §5.5, veredicto del dueño ya dado (menú 32→33):

1. **Ítem «Mensajes» de primer nivel** en `MenuPrincipal` — la posición que tu
   diseño argumente (vecindad de uso, no alfabeto), gateado por el permiso
   `usar mensajes` (doctrina «el menú jamás ofrece un 403»).
2. **Badge de no-leídos DECLARATIVO** (el patrón de badges del menú — cuenta
   al render, SIN poll: la doctrina de la campanita sigue). La cifra = suma de
   MIS contadores (la misma media firma que ya calculas — si extraes un
   contadorNoLeidos() compartido con firmaChat, decláralo).
3. **Retiro del `<x-volver>` de la huérfana temporal** (`mensajes/index` deja
   de ser huérfana al entrar al menú — P-NAV-06/08: el Volver era el puente
   mientras no había ítem; ahora el menú ES el camino).
4. **Candados**: MenuPrincipal deriva (item aparece con permiso, NO aparece
   sin él — 33 con chat, 32 sin) · badge con cifra exacta y ausente en cero ·
   huérfana ya no huérfana (Volver fuera, el test de VolverTest que la
   toleraba vuelve a su forma estricta si la listaba) · mutación tuya
   declarada.
5. **Cruce MenuPrincipal**: Max-1 NO lo toca en OPE-1 (su lote vive en
   ProduccionController/seeder/vistas de producción) — vía libre AHORA; el
   Director re-secuencia si su fix se cruza (no debería).

### Verificación (invariante)
Rama `feature/msg-4-menu` desde main FRESCO (baseline: **2244 / 15.594** en
`1d7ad3e` — recuenta tú; OPE-1 de Max-1 puede mergear antes que tu parte:
+7/+125 esperados de él, sin archivos tuyos). Suite COMPLETA antes. Bundle:
clases nuevas → I-06 declarado. Parte al buzón; espera doble llave.

## 📡 Después de MSG-4
QA del dueño del chat COMPLETO en celular (las 4 etapas juntas) → cierre del
PLAN-MENSAJES en /plan y Trello. Después: el Director te asigna stream nuevo.

## Estado
Max-1: OPE-1 en rebote quirúrgico (un info-tip con el 7 en prosa — v77.1);
luego OPE-2/OPE-3. Marcos activo. Trello espejando. Baseline: 2244/15.594 en
`1d7ad3e`.

CIERRE: GO MSG-4. El último ladrillo — la puerta del chat se abre en el menú.
