# Dictado vigente — Max-1 (Forjador A, stream 1)
> Emitido por el Director el 2026-08-14 (v55 — A2 EN PRODUCCIÓN; A3 Informe FRENADO por un 2º nudo de permisos, decisión del dueño pendiente). Manda sobre lo anterior.

MODELO: Opus 4.8 · high.

## ✅ A2 está EN PRODUCCIÓN (merge `6a35329`, doble llave 14-ago) — menú 41 → 40

Suite del Director sobre el árbol re-mergeado: **2110 verdes / 14.723 aserciones**. Conté
el menú (11+28+1=40), da. Rama borrada. Bloque A: **2 de 3**.

Lo que hiciste bien, para que se repita:

1. **Verificaste los permisos por tu cuenta ANTES de consolidar** — los 4 roles con
   despachar|recibir traslado ven todos el Listado. La lección del Informe aplicada sola.
2. **Cazaste el detalle del prefijo**: `admin.traslados.*` no pisa
   `admin.bodegas.traslados.*` (familias disjuntas). Ese es el tipo de colisión silenciosa
   que un candado mal escrito deja pasar.
3. **La taxonomía hecha markup**: config → desplegable «Configuración»; flujo → pestaña de
   primer nivel. El Listado va quedando como el hub que el mapa quería, con la jerarquía
   visible en la estructura, no solo en tu cabeza.

**Nota de entorno (mía, no tuya)**: durante mi verificación apareció 1 rojo —
`AutorizacionCitaTest` del PR #9 (ajeno a tu lote) — por un `include` de favicon que
fallaba. Era mi `vendor/symfony/error-handler` incompleto (le faltaba un asset del
renderizador de errores); `composer reinstall symfony/error-handler` lo curó, y el CI de
main ya lo tenía verde. Tu cifra estaba bien. Lo anoto abajo como incidencia porque es la
4ª de entorno de la semana y conviene tener la receta a mano.

## ⏸️ A3 · Informe — FRENADO. NO lo construyas todavía.

Al preparar el dictado de A3 encontré un **segundo nudo de permisos** que la decisión del
13-ago (partir por dominio) no contemplaba. El informe **industrial** lo ven cuatro roles,
y su audiencia cruza dos dominios que NO comparten anfitrión:

- `jefe_bodega` (seeder L150) y `jefe_sucursal` (L196) ven el informe industrial pero **NO
  entran a la Agenda de terreno** → si el industrial va SOLO a la Agenda, **pierden acceso**.
- `tecnico_industrial` ve el industrial pero **NO ve el Listado** → si va al Listado, él pierde.

No hay un anfitrión único que cubra a los cuatro sin romper a alguno (o duplicar, que está
prohibido). El informe **dispensadores** sí es limpio (todos los que lo ven, ven el Listado).

**Esto es decisión del dueño, no tuya ni mía**: se lo llevo ahora. Mi recomendación es que
el Informe **viva solo** (es el ítem que el propio criterio del proyecto deja fuera —
audiencia partida entre dos dominios). Hasta que el dueño decida, **A3 no se construye** y
el Bloque A queda cerrado en 2 de 3 (menú 40). Detalle completo en §4.1 del plan («SEGUNDO
NUDO»).

## Estado
- **Max-2** en pausa. **Marcos y el PR #9** activos (el drift de hoy trajo autorización de
  citas + informes ST a Excel + responsive). Re-fetch religioso.
- Cuando el dueño decida A3, te llega el dictado v56. Por ahora: **pausa**.

## Recordatorios (para cuando se reanude)
Rama nueva desde main FRESCO; suite COMPLETA de main fresco ANTES de empezar; candado
mutado; parte al buzón. Baseline del Director: **2110 / 14.723** en `6a35329`.

CIERRE: A2 cerrado, Bloque A en 2/3. A3 espera decisión del dueño. Verifica permisos SIEMPRE.
